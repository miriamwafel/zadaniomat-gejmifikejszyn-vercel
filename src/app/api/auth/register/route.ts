import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { db, users } from "@/db";
import { eq } from "drizzle-orm";

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { email, password, name } = body;

    if (!email || !password) {
      return NextResponse.json(
        { error: "Email i hasło są wymagane" },
        { status: 400 }
      );
    }

    // Sprawdź czy email już istnieje
    const existingUser = await db.select().from(users)
      .where(eq(users.email, email))
      .limit(1);

    if (existingUser.length > 0) {
      return NextResponse.json(
        { error: "Użytkownik z tym adresem email już istnieje" },
        { status: 400 }
      );
    }

    // Hash hasła
    const hashedPassword = await bcrypt.hash(password, 12);

    // Sprawdź czy to pierwszy użytkownik - jeśli tak, nadaj rolę super_admin
    const allUsers = await db.select().from(users);
    const role = allUsers.length === 0 ? "super_admin" : "user";

    // Utwórz użytkownika
    const result = await db.insert(users).values({
      email,
      password: hashedPassword,
      name: name || null,
      role,
      active: true,
    }).returning({
      id: users.id,
      email: users.email,
      name: users.name,
      role: users.role,
    });

    return NextResponse.json({
      success: true,
      user: result[0],
      message: role === "super_admin"
        ? "Konto super admina utworzone pomyślnie!"
        : "Konto utworzone pomyślnie!",
    }, { status: 201 });

  } catch (error) {
    console.error("Registration error:", error);
    return NextResponse.json(
      { error: "Wystąpił błąd podczas rejestracji" },
      { status: 500 }
    );
  }
}
