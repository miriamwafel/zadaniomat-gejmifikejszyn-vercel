import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { db, users } from "@/db";
import { eq } from "drizzle-orm";
import { auth } from "@/lib/auth";

// GET - pobierz użytkowników (tylko dla adminów)
export async function GET() {
  try {
    const session = await auth();

    if (!session?.user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    if (session.user.role !== "admin" && session.user.role !== "super_admin") {
      return NextResponse.json({ error: "Forbidden" }, { status: 403 });
    }

    const allUsers = await db.select({
      id: users.id,
      email: users.email,
      name: users.name,
      role: users.role,
      active: users.active,
      createdAt: users.createdAt,
    }).from(users);

    return NextResponse.json(allUsers);
  } catch (error) {
    console.error("Error fetching users:", error);
    return NextResponse.json({ error: "Failed to fetch users" }, { status: 500 });
  }
}

// PUT - aktualizuj użytkownika (tylko dla adminów)
export async function PUT(request: NextRequest) {
  try {
    const session = await auth();

    if (!session?.user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    if (session.user.role !== "admin" && session.user.role !== "super_admin") {
      return NextResponse.json({ error: "Forbidden" }, { status: 403 });
    }

    const body = await request.json();
    const { id, email, name, role, active, password } = body;

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    // Sprawdź czy użytkownik istnieje
    const existingUser = await db.select().from(users)
      .where(eq(users.id, id))
      .limit(1);

    if (existingUser.length === 0) {
      return NextResponse.json({ error: "User not found" }, { status: 404 });
    }

    // Tylko super_admin może zmieniać role na admin/super_admin
    if (role && (role === "admin" || role === "super_admin") && session.user.role !== "super_admin") {
      return NextResponse.json({ error: "Only super admin can assign admin roles" }, { status: 403 });
    }

    // Nie można zdegradować ostatniego super_admina
    if (existingUser[0].role === "super_admin" && role && role !== "super_admin") {
      const superAdmins = await db.select().from(users).where(eq(users.role, "super_admin"));
      if (superAdmins.length <= 1) {
        return NextResponse.json({ error: "Cannot remove the last super admin" }, { status: 400 });
      }
    }

    const updateData: Record<string, unknown> = { updatedAt: new Date() };
    if (email !== undefined) updateData.email = email;
    if (name !== undefined) updateData.name = name;
    if (role !== undefined) updateData.role = role;
    if (active !== undefined) updateData.active = active;
    if (password) {
      updateData.password = await bcrypt.hash(password, 12);
    }

    const result = await db.update(users)
      .set(updateData)
      .where(eq(users.id, id))
      .returning({
        id: users.id,
        email: users.email,
        name: users.name,
        role: users.role,
        active: users.active,
      });

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating user:", error);
    return NextResponse.json({ error: "Failed to update user" }, { status: 500 });
  }
}

// DELETE - usuń użytkownika (tylko dla super_admin)
export async function DELETE(request: NextRequest) {
  try {
    const session = await auth();

    if (!session?.user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    if (session.user.role !== "super_admin") {
      return NextResponse.json({ error: "Forbidden" }, { status: 403 });
    }

    const searchParams = request.nextUrl.searchParams;
    const id = searchParams.get("id");

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    // Nie można usunąć samego siebie
    if (id === session.user.id) {
      return NextResponse.json({ error: "Cannot delete yourself" }, { status: 400 });
    }

    // Sprawdź czy to nie ostatni super_admin
    const userToDelete = await db.select().from(users).where(eq(users.id, parseInt(id))).limit(1);
    if (userToDelete.length > 0 && userToDelete[0].role === "super_admin") {
      const superAdmins = await db.select().from(users).where(eq(users.role, "super_admin"));
      if (superAdmins.length <= 1) {
        return NextResponse.json({ error: "Cannot delete the last super admin" }, { status: 400 });
      }
    }

    await db.delete(users).where(eq(users.id, parseInt(id)));

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting user:", error);
    return NextResponse.json({ error: "Failed to delete user" }, { status: 500 });
  }
}
