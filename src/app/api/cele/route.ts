import { NextRequest, NextResponse } from "next/server";
import { db, celeRok, celeOkres, roki, okresy } from "@/db";
import { eq, and } from "drizzle-orm";
import { auth } from "@/lib/auth";

// GET - pobierz cele (rok lub okres)
export async function GET(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const rokId = searchParams.get("rok_id");
    const okresId = searchParams.get("okres_id");
    const type = searchParams.get("type") || "rok"; // 'rok' or 'okres'

    if (type === "okres" && okresId) {
      // Verify the okres belongs to user's rok
      const okres = await db.select().from(okresy)
        .innerJoin(roki, eq(okresy.rokId, roki.id))
        .where(and(eq(okresy.id, parseInt(okresId)), eq(roki.userId, userId)))
        .limit(1);

      if (okres.length === 0) {
        return NextResponse.json({ error: "Okres not found" }, { status: 404 });
      }

      const result = await db.select().from(celeOkres)
        .where(eq(celeOkres.okresId, parseInt(okresId)));
      return NextResponse.json(result);
    }

    if (rokId) {
      // Verify the rok belongs to user
      const rok = await db.select().from(roki)
        .where(and(eq(roki.id, parseInt(rokId)), eq(roki.userId, userId)))
        .limit(1);

      if (rok.length === 0) {
        return NextResponse.json({ error: "Rok not found" }, { status: 404 });
      }

      const result = await db.select().from(celeRok)
        .where(eq(celeRok.rokId, parseInt(rokId)));
      return NextResponse.json(result);
    }

    return NextResponse.json({ error: "rok_id or okres_id required" }, { status: 400 });
  } catch (error) {
    console.error("Error fetching cele:", error);
    return NextResponse.json({ error: "Failed to fetch cele" }, { status: 500 });
  }
}

// POST - utwórz nowy cel
export async function POST(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();
    const type = body.type || "rok";

    if (type === "okres") {
      // Verify the okres belongs to user's rok
      const okres = await db.select().from(okresy)
        .innerJoin(roki, eq(okresy.rokId, roki.id))
        .where(and(eq(okresy.id, body.okres_id), eq(roki.userId, userId)))
        .limit(1);

      if (okres.length === 0) {
        return NextResponse.json({ error: "Okres not found" }, { status: 404 });
      }

      const result = await db.insert(celeOkres).values({
        okresId: body.okres_id,
        kategoria: body.kategoria,
        cel: body.cel || "",
        status: body.status || null,
        osiagniety: body.osiagniety || false,
        uwagi: body.uwagi || null,
        pozycja: body.pozycja || 1,
      }).returning();

      return NextResponse.json(result[0], { status: 201 });
    }

    // Verify the rok belongs to user
    const rok = await db.select().from(roki)
      .where(and(eq(roki.id, body.rok_id), eq(roki.userId, userId)))
      .limit(1);

    if (rok.length === 0) {
      return NextResponse.json({ error: "Rok not found" }, { status: 404 });
    }

    const result = await db.insert(celeRok).values({
      rokId: body.rok_id,
      kategoria: body.kategoria,
      cel: body.cel || "",
      planowaneGodzinyDziennie: body.planowane_godziny_dziennie || "1.00",
    }).returning();

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error("Error creating cel:", error);
    return NextResponse.json({ error: "Failed to create cel" }, { status: 500 });
  }
}

// PUT - zaktualizuj cel
export async function PUT(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();
    const type = body.type || "rok";

    if (!body.id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    if (type === "okres") {
      // Verify ownership through joins
      const existing = await db.select().from(celeOkres)
        .innerJoin(okresy, eq(celeOkres.okresId, okresy.id))
        .innerJoin(roki, eq(okresy.rokId, roki.id))
        .where(and(eq(celeOkres.id, body.id), eq(roki.userId, userId)))
        .limit(1);

      if (existing.length === 0) {
        return NextResponse.json({ error: "Cel not found" }, { status: 404 });
      }

      const updateData: Record<string, unknown> = {};
      if (body.cel !== undefined) updateData.cel = body.cel;
      if (body.status !== undefined) updateData.status = body.status;
      if (body.osiagniety !== undefined) updateData.osiagniety = body.osiagniety;
      if (body.uwagi !== undefined) updateData.uwagi = body.uwagi;
      if (body.pozycja !== undefined) updateData.pozycja = body.pozycja;
      if (body.osiagniety === true) updateData.completedAt = new Date();

      const result = await db.update(celeOkres)
        .set(updateData)
        .where(eq(celeOkres.id, body.id))
        .returning();

      return NextResponse.json(result[0]);
    }

    // Verify ownership through joins
    const existing = await db.select().from(celeRok)
      .innerJoin(roki, eq(celeRok.rokId, roki.id))
      .where(and(eq(celeRok.id, body.id), eq(roki.userId, userId)))
      .limit(1);

    if (existing.length === 0) {
      return NextResponse.json({ error: "Cel not found" }, { status: 404 });
    }

    const updateData: Record<string, unknown> = {};
    if (body.cel !== undefined) updateData.cel = body.cel;
    if (body.planowane_godziny_dziennie !== undefined) {
      updateData.planowaneGodzinyDziennie = body.planowane_godziny_dziennie;
    }

    const result = await db.update(celeRok)
      .set(updateData)
      .where(eq(celeRok.id, body.id))
      .returning();

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error("Error updating cel:", error);
    return NextResponse.json({ error: "Failed to update cel" }, { status: 500 });
  }
}

// DELETE - usuń cel
export async function DELETE(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const id = searchParams.get("id");
    const type = searchParams.get("type") || "rok";

    if (!id) {
      return NextResponse.json({ error: "ID is required" }, { status: 400 });
    }

    if (type === "okres") {
      // Verify ownership
      const existing = await db.select().from(celeOkres)
        .innerJoin(okresy, eq(celeOkres.okresId, okresy.id))
        .innerJoin(roki, eq(okresy.rokId, roki.id))
        .where(and(eq(celeOkres.id, parseInt(id)), eq(roki.userId, userId)))
        .limit(1);

      if (existing.length === 0) {
        return NextResponse.json({ error: "Cel not found" }, { status: 404 });
      }

      await db.delete(celeOkres).where(eq(celeOkres.id, parseInt(id)));
      return NextResponse.json({ success: true });
    }

    // Verify ownership
    const existing = await db.select().from(celeRok)
      .innerJoin(roki, eq(celeRok.rokId, roki.id))
      .where(and(eq(celeRok.id, parseInt(id)), eq(roki.userId, userId)))
      .limit(1);

    if (existing.length === 0) {
      return NextResponse.json({ error: "Cel not found" }, { status: 404 });
    }

    await db.delete(celeRok).where(eq(celeRok.id, parseInt(id)));
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Error deleting cel:", error);
    return NextResponse.json({ error: "Failed to delete cel" }, { status: 500 });
  }
}
