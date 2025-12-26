import { NextRequest, NextResponse } from "next/server";
import { db, kategorie } from "@/db";
import { eq } from "drizzle-orm";
import { auth } from "@/lib/auth";

// Domyślne kategorie
const DEFAULT_KATEGORIE = [
  { klucz: "zapianowany", nazwa: "Zapianowany", typ: "wszystkie", isStrategic: true, color: "#6366f1" },
  { klucz: "klejpan", nazwa: "Klejpan", typ: "wszystkie", isStrategic: true, color: "#22c55e" },
  { klucz: "marka_langer", nazwa: "Marka Langer", typ: "wszystkie", isStrategic: true, color: "#f59e0b" },
  { klucz: "marketing_construction", nazwa: "Marketing Construction", typ: "wszystkie", isStrategic: false, color: "#ec4899" },
  { klucz: "fjo", nazwa: "FJO (Firma Jako Osobowość)", typ: "wszystkie", isStrategic: false, color: "#8b5cf6" },
  { klucz: "obsluga_telefoniczna", nazwa: "Obsługa telefoniczna", typ: "wszystkie", isStrategic: false, color: "#14b8a6" },
  { klucz: "sprawy_organizacyjne", nazwa: "Sprawy Organizacyjne", typ: "zadania", isStrategic: false, color: "#64748b" },
];

// GET - pobierz kategorie
export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const typ = searchParams.get("typ"); // 'cele', 'zadania', 'wszystkie'
    const strategic = searchParams.get("strategic"); // 'true' to filter only strategic

    let result = await db.select().from(kategorie).where(eq(kategorie.aktywne, true));

    // Jeśli brak kategorii, zwróć domyślne
    if (result.length === 0) {
      let defaultKats = DEFAULT_KATEGORIE;
      if (strategic === "true") {
        defaultKats = defaultKats.filter(k => k.isStrategic);
      }
      if (typ && typ !== "wszystkie") {
        defaultKats = defaultKats.filter(k => k.typ === typ || k.typ === "wszystkie");
      }
      return NextResponse.json(defaultKats);
    }

    // Filtruj po strategic
    if (strategic === "true") {
      result = result.filter(k => k.isStrategic === true);
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

// POST - utwórz kategorię (tylko admin)
export async function POST(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.role || !["admin", "super_admin"].includes(session.user.role)) {
      return NextResponse.json({ error: "Admin access required" }, { status: 403 });
    }

    const body = await request.json();

    const result = await db.insert(kategorie).values({
      klucz: body.klucz,
      nazwa: body.nazwa,
      typ: body.typ || "wszystkie",
      isStrategic: body.is_strategic || false,
      color: body.color || "#6366f1",
      aktywne: true,
    }).returning();

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating kategoria:", error);
    return NextResponse.json({ error: "Failed to create kategoria" }, { status: 500 });
  }
}

// PUT - zaktualizuj kategorię (tylko admin)
export async function PUT(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.role || !["admin", "super_admin"].includes(session.user.role)) {
      return NextResponse.json({ error: "Admin access required" }, { status: 403 });
    }

    const body = await request.json();

    if (!body.id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    const updateData: Record<string, unknown> = {};
    if (body.klucz !== undefined) updateData.klucz = body.klucz;
    if (body.nazwa !== undefined) updateData.nazwa = body.nazwa;
    if (body.typ !== undefined) updateData.typ = body.typ;
    if (body.is_strategic !== undefined) updateData.isStrategic = body.is_strategic;
    if (body.color !== undefined) updateData.color = body.color;
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

// DELETE - usuń kategorię (soft delete, tylko admin)
export async function DELETE(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.role || !["admin", "super_admin"].includes(session.user.role)) {
      return NextResponse.json({ error: "Admin access required" }, { status: 403 });
    }

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
