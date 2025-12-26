import { NextRequest, NextResponse } from "next/server";
import { db, roki, celeRok } from "@/db";
import { eq, and, lte, gte, desc } from "drizzle-orm";
import { auth } from "@/lib/auth";

// GET - pobierz roki
export async function GET(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const current = searchParams.get("current");

    if (current === "true") {
      const today = new Date().toISOString().split("T")[0];
      const result = await db.select().from(roki)
        .where(and(
          eq(roki.userId, userId),
          lte(roki.dataStart, today),
          gte(roki.dataKoniec, today)
        ))
        .limit(1);
      return NextResponse.json(result[0] || null);
    }

    const result = await db.select().from(roki)
      .where(eq(roki.userId, userId))
      .orderBy(desc(roki.dataStart));
    return NextResponse.json(result);
  } catch (error) {
    console.error("Error fetching roki:", error);
    return NextResponse.json({ error: "Failed to fetch roki" }, { status: 500 });
  }
}

// POST - utwórz nowy rok
export async function POST(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();

    const result = await db.insert(roki).values({
      userId,
      nazwa: body.nazwa,
      dataStart: body.data_start,
      dataKoniec: body.data_koniec,
    }).returning();

    // Opcjonalnie: utwórz domyślne cele dla każdej kategorii
    if (body.kategorie && Array.isArray(body.kategorie)) {
      for (const kat of body.kategorie) {
        await db.insert(celeRok).values({
          rokId: result[0].id,
          kategoria: kat,
          cel: "",
          planowaneGodzinyDziennie: "1.00",
        });
      }
    }

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating rok:", error);
    return NextResponse.json({ error: "Failed to create rok" }, { status: 500 });
  }
}

// PUT - zaktualizuj rok
export async function PUT(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();

    if (!body.id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    const updateData: Record<string, unknown> = {};
    if (body.nazwa !== undefined) updateData.nazwa = body.nazwa;
    if (body.data_start !== undefined) updateData.dataStart = body.data_start;
    if (body.data_koniec !== undefined) updateData.dataKoniec = body.data_koniec;

    const result = await db.update(roki)
      .set(updateData)
      .where(and(eq(roki.id, body.id), eq(roki.userId, userId)))
      .returning();

    if (result.length === 0) {
      return NextResponse.json({ error: "Not found or unauthorized" }, { status: 404 });
    }

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating rok:", error);
    return NextResponse.json({ error: "Failed to update rok" }, { status: 500 });
  }
}

// DELETE - usuń rok
export async function DELETE(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const id = searchParams.get("id");

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    const result = await db.delete(roki)
      .where(and(eq(roki.id, parseInt(id)), eq(roki.userId, userId)))
      .returning();

    if (result.length === 0) {
      return NextResponse.json({ error: "Not found or unauthorized" }, { status: 404 });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting rok:", error);
    return NextResponse.json({ error: "Failed to delete rok" }, { status: 500 });
  }
}
