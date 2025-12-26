import { NextRequest, NextResponse } from "next/server";
import { db, zadania } from "@/db";
import { eq, and, gte, lte, desc } from "drizzle-orm";
import { auth } from "@/lib/auth";

// GET - pobierz zadania (opcjonalnie filtruj po dacie)
export async function GET(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const dzien = searchParams.get("dzien");
    const okresId = searchParams.get("okres_id");
    const status = searchParams.get("status");
    const dataOd = searchParams.get("data_od");
    const dataDo = searchParams.get("data_do");

    const conditions = [eq(zadania.userId, userId)];

    if (dzien) {
      conditions.push(eq(zadania.dzien, dzien));
    }
    if (okresId) {
      conditions.push(eq(zadania.okresId, parseInt(okresId)));
    }
    if (status) {
      conditions.push(eq(zadania.status, status));
    }
    if (dataOd) {
      conditions.push(gte(zadania.dzien, dataOd));
    }
    if (dataDo) {
      conditions.push(lte(zadania.dzien, dataDo));
    }

    const result = await db.select().from(zadania)
      .where(and(...conditions))
      .orderBy(zadania.godzinaStart, zadania.pozycjaHarmonogram, desc(zadania.dzien));

    return NextResponse.json(result);
  } catch (error) {
    console.error("Error fetching zadania:", error);
    return NextResponse.json({ error: "Failed to fetch zadania" }, { status: 500 });
  }
}

// POST - utwórz nowe zadanie
export async function POST(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();

    const result = await db.insert(zadania).values({
      userId,
      okresId: body.okres_id || null,
      kategoria: body.kategoria,
      dzien: body.dzien,
      zadanie: body.zadanie,
      celTodo: body.cel_todo || null,
      planowanyCzas: body.planowany_czas || 0,
      status: body.status || "nowe",
      godzinaStart: body.godzina_start || null,
      godzinaKoniec: body.godzina_koniec || null,
      pozycjaHarmonogram: body.pozycja_harmonogram || null,
      jestCykliczne: body.jest_cykliczne || false,
      recurringTemplateId: body.recurring_template_id || null,
    }).returning();

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating zadanie:", error);
    return NextResponse.json({ error: "Failed to create zadanie" }, { status: 500 });
  }
}

// PUT - zaktualizuj zadanie
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
    if (body.kategoria !== undefined) updateData.kategoria = body.kategoria;
    if (body.dzien !== undefined) updateData.dzien = body.dzien;
    if (body.zadanie !== undefined) updateData.zadanie = body.zadanie;
    if (body.cel_todo !== undefined) updateData.celTodo = body.cel_todo;
    if (body.planowany_czas !== undefined) updateData.planowanyCzas = body.planowany_czas;
    if (body.faktyczny_czas !== undefined) updateData.faktycznyCzas = body.faktyczny_czas;
    if (body.status !== undefined) updateData.status = body.status;
    if (body.godzina_start !== undefined) updateData.godzinaStart = body.godzina_start;
    if (body.godzina_koniec !== undefined) updateData.godzinaKoniec = body.godzina_koniec;
    if (body.pozycja_harmonogram !== undefined) updateData.pozycjaHarmonogram = body.pozycja_harmonogram;

    // Tylko zadania należące do użytkownika
    const result = await db.update(zadania)
      .set(updateData)
      .where(and(eq(zadania.id, body.id), eq(zadania.userId, userId)))
      .returning();

    if (result.length === 0) {
      return NextResponse.json({ error: "Task not found" }, { status: 404 });
    }

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating zadanie:", error);
    return NextResponse.json({ error: "Failed to update zadanie" }, { status: 500 });
  }
}

// DELETE - usuń zadanie
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

    // Tylko zadania należące do użytkownika
    await db.delete(zadania).where(and(eq(zadania.id, parseInt(id)), eq(zadania.userId, userId)));

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting zadanie:", error);
    return NextResponse.json({ error: "Failed to delete zadanie" }, { status: 500 });
  }
}
