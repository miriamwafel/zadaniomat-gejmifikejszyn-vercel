import { NextRequest, NextResponse } from "next/server";
import { db, kategorie } from "@/db";
import { eq } from "drizzle-orm";

// Domyślne kategorie
const DEFAULT_KATEGORIE = [
  { klucz: "zapianowany", nazwa: "Zapianowany", typ: "wszystkie" },
  { klucz: "klejpan", nazwa: "Klejpan", typ: "wszystkie" },
  { klucz: "marka_langer", nazwa: "Marka Langer", typ: "wszystkie" },
  { klucz: "marketing_construction", nazwa: "Marketing Construction", typ: "wszystkie" },
  { klucz: "fjo", nazwa: "FJO (Firma Jako Osobowość)", typ: "wszystkie" },
  { klucz: "obsluga_telefoniczna", nazwa: "Obsługa telefoniczna", typ: "wszystkie" },
  { klucz: "sprawy_organizacyjne", nazwa: "Sprawy Organizacyjne", typ: "zadania" },
];

// GET - pobierz kategorie
export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const typ = searchParams.get("typ"); // 'cele', 'zadania', 'wszystkie'

    let result = await db.select().from(kategorie).where(eq(kategorie.aktywne, true));

    // Jeśli brak kategorii, zwróć domyślne
    if (result.length === 0) {
      return NextResponse.json(DEFAULT_KATEGORIE);
    }

    // Filtruj po typie
    if (typ && typ !== "wszystkie") {
      result = result.filter(k => k.typ === typ || k.typ === "wszystkie");
    }

    return NextResponse.json(result);
  } catch (error) {
    console.error("Error fetching kategorie:", error);
    // W przypadku błędu (np. brak tabeli) zwróć domyślne
    return NextResponse.json(DEFAULT_KATEGORIE);
  }
}

// POST - utwórz kategorię
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    const result = await db.insert(kategorie).values({
      klucz: body.klucz,
      nazwa: body.nazwa,
      typ: body.typ || "wszystkie",
      aktywne: true,
    }).returning();

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating kategoria:", error);
    return NextResponse.json({ error: "Failed to create kategoria" }, { status: 500 });
  }
}

// PUT - zaktualizuj kategorię
export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();

    if (!body.id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    const updateData: Record<string, unknown> = {};
    if (body.klucz !== undefined) updateData.klucz = body.klucz;
    if (body.nazwa !== undefined) updateData.nazwa = body.nazwa;
    if (body.typ !== undefined) updateData.typ = body.typ;
    if (body.aktywne !== undefined) updateData.aktywne = body.aktywne;

    const result = await db.update(kategorie)
      .set(updateData)
      .where(eq(kategorie.id, body.id))
      .returning();

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating kategoria:", error);
    return NextResponse.json({ error: "Failed to update kategoria" }, { status: 500 });
  }
}

// DELETE - usuń kategorię (soft delete)
export async function DELETE(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const id = searchParams.get("id");

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    await db.update(kategorie)
      .set({ aktywne: false })
      .where(eq(kategorie.id, parseInt(id)));

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting kategoria:", error);
    return NextResponse.json({ error: "Failed to delete kategoria" }, { status: 500 });
  }
}

// Inicjalizacja domyślnych kategorii
export async function initDefaultKategorie() {
  try {
    const existing = await db.select().from(kategorie);
    if (existing.length === 0) {
      for (const kat of DEFAULT_KATEGORIE) {
        await db.insert(kategorie).values(kat);
      }
    }
  } catch (error) {
    console.error("Error initializing kategorie:", error);
  }
}
