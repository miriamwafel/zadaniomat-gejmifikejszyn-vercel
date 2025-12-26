import { NextRequest, NextResponse } from "next/server";
import { db, okresy, celeOkres } from "@/db";
import { eq, and, lte, gte, desc } from "drizzle-orm";

// GET - pobierz okresy
export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const rokId = searchParams.get("rok_id");
    const current = searchParams.get("current");

    if (current === "true") {
      const today = new Date().toISOString().split("T")[0];
      const result = await db.select().from(okresy)
        .where(and(lte(okresy.dataStart, today), gte(okresy.dataKoniec, today)))
        .limit(1);
      return NextResponse.json(result[0] || null);
    }

    if (rokId) {
      const result = await db.select().from(okresy)
        .where(eq(okresy.rokId, parseInt(rokId)))
        .orderBy(okresy.dataStart);
      return NextResponse.json(result);
    }

    const result = await db.select().from(okresy).orderBy(desc(okresy.dataStart));
    return NextResponse.json(result);
  } catch (error) {
    console.error("Error fetching okresy:", error);
    return NextResponse.json({ error: "Failed to fetch okresy" }, { status: 500 });
  }
}

// POST - utwórz nowy okres
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    const result = await db.insert(okresy).values({
      rokId: body.rok_id,
      nazwa: body.nazwa,
      dataStart: body.data_start,
      dataKoniec: body.data_koniec,
    }).returning();

    // Opcjonalnie: utwórz domyślne cele dla każdej kategorii
    if (body.kategorie && Array.isArray(body.kategorie)) {
      for (const kat of body.kategorie) {
        await db.insert(celeOkres).values({
          okresId: result[0].id,
          kategoria: kat,
          cel: "",
        });
      }
    }

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating okres:", error);
    return NextResponse.json({ error: "Failed to create okres" }, { status: 500 });
  }
}

// PUT - zaktualizuj okres
export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();

    if (!body.id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    const updateData: Record<string, unknown> = {};
    if (body.nazwa !== undefined) updateData.nazwa = body.nazwa;
    if (body.data_start !== undefined) updateData.dataStart = body.data_start;
    if (body.data_koniec !== undefined) updateData.dataKoniec = body.data_koniec;

    const result = await db.update(okresy)
      .set(updateData)
      .where(eq(okresy.id, body.id))
      .returning();

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating okres:", error);
    return NextResponse.json({ error: "Failed to update okres" }, { status: 500 });
  }
}

// DELETE - usuń okres
export async function DELETE(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const id = searchParams.get("id");

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    await db.delete(okresy).where(eq(okresy.id, parseInt(id)));

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting okres:", error);
    return NextResponse.json({ error: "Failed to delete okres" }, { status: 500 });
  }
}
