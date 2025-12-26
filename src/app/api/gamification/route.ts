import { NextRequest, NextResponse } from "next/server";
import { db, gamificationStats, streaks, xpLog, achievements, dailyChallenges, comboState } from "@/db";
import { eq, desc, and, sql } from "drizzle-orm";
import { auth } from "@/lib/auth";

// Konfiguracja poziomów
const LEVEL_CONFIG = {
  baseXP: 100,
  multiplier: 1.5,
  maxLevel: 100,
};

function getXPForLevel(level: number): number {
  return Math.floor(LEVEL_CONFIG.baseXP * Math.pow(LEVEL_CONFIG.multiplier, level - 1));
}

function getLevelFromXP(totalXP: number): { level: number; currentLevelXP: number; nextLevelXP: number } {
  let level = 1;
  let xpRequired = 0;

  while (level < LEVEL_CONFIG.maxLevel) {
    const nextLevelXP = getXPForLevel(level);
    if (xpRequired + nextLevelXP > totalXP) {
      return {
        level,
        currentLevelXP: totalXP - xpRequired,
        nextLevelXP,
      };
    }
    xpRequired += nextLevelXP;
    level++;
  }

  return { level: LEVEL_CONFIG.maxLevel, currentLevelXP: 0, nextLevelXP: 0 };
}

// GET - pobierz statystyki gamifikacji
export async function GET(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const searchParams = request.nextUrl.searchParams;
    const type = searchParams.get("type");

    // Pobierz lub utwórz statystyki
    let stats = await db.select().from(gamificationStats)
      .where(eq(gamificationStats.userId, userId))
      .limit(1);

    if (stats.length === 0) {
      const newStats = await db.insert(gamificationStats).values({
        userId,
        totalXp: 0,
        currentLevel: 1,
        prestige: 0,
        freezeDaysAvailable: 0,
      }).returning();
      stats = newStats;
    }

    const playerStats = stats[0];
    const levelInfo = getLevelFromXP(playerStats.totalXp);

    if (type === "full") {
      // Pobierz wszystkie dane
      const playerStreaks = await db.select().from(streaks)
        .where(eq(streaks.userId, userId));

      const playerAchievements = await db.select().from(achievements)
        .where(eq(achievements.userId, userId));

      const today = new Date().toISOString().split("T")[0];
      const todayChallenges = await db.select().from(dailyChallenges)
        .where(and(
          eq(dailyChallenges.userId, userId),
          eq(dailyChallenges.challengeDate, today)
        ));

      const todayCombo = await db.select().from(comboState)
        .where(and(
          eq(comboState.userId, userId),
          eq(comboState.comboDate, today)
        ));

      const recentXP = await db.select().from(xpLog)
        .where(eq(xpLog.userId, userId))
        .orderBy(desc(xpLog.earnedAt))
        .limit(20);

      return NextResponse.json({
        stats: {
          ...playerStats,
          ...levelInfo,
        },
        streaks: playerStreaks,
        achievements: playerAchievements,
        dailyChallenges: todayChallenges,
        combo: todayCombo[0] || { currentCombo: 0, maxComboToday: 0 },
        recentXP,
      });
    }

    return NextResponse.json({
      ...playerStats,
      ...levelInfo,
    });
  } catch (error) {
    console.error("Error fetching gamification:", error);
    return NextResponse.json({ error: "Failed to fetch gamification" }, { status: 500 });
  }
}

// POST - dodaj XP
export async function POST(request: NextRequest) {
  try {
    const session = await auth();
    if (!session?.user?.id) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }
    const userId = parseInt(session.user.id);

    const body = await request.json();
    const { xp_amount, xp_type, description, multiplier = 1, reference_id, reference_type, condition_text } = body;

    if (!xp_amount || !xp_type) {
      return NextResponse.json({ error: "xp_amount and xp_type are required" }, { status: 400 });
    }

    const finalXP = Math.floor(xp_amount * multiplier);

    // Zapisz log XP
    await db.insert(xpLog).values({
      userId,
      xpAmount: finalXP,
      xpType: xp_type,
      multiplier: String(multiplier),
      description: description || null,
      conditionText: condition_text || null,
      referenceId: reference_id || null,
      referenceType: reference_type || null,
    });

    // Zaktualizuj statystyki
    const updated = await db.update(gamificationStats)
      .set({
        totalXp: sql`${gamificationStats.totalXp} + ${finalXP}`,
        updatedAt: new Date(),
      })
      .where(eq(gamificationStats.userId, userId))
      .returning();

    if (updated.length === 0) {
      // Utwórz nowe statystyki jeśli nie istnieją
      await db.insert(gamificationStats).values({
        userId,
        totalXp: finalXP,
        currentLevel: 1,
      });
    }

    // Sprawdź czy nastąpił level up
    const stats = await db.select().from(gamificationStats)
      .where(eq(gamificationStats.userId, userId))
      .limit(1);

    const levelInfo = getLevelFromXP(stats[0].totalXp);

    if (levelInfo.level > stats[0].currentLevel) {
      await db.update(gamificationStats)
        .set({ currentLevel: levelInfo.level })
        .where(eq(gamificationStats.userId, userId));
    }

    return NextResponse.json({
      xp_added: finalXP,
      total_xp: stats[0].totalXp,
      level: levelInfo.level,
      level_up: levelInfo.level > stats[0].currentLevel,
    });
  } catch (error) {
    console.error("Error adding XP:", error);
    return NextResponse.json({ error: "Failed to add XP" }, { status: 500 });
  }
}
