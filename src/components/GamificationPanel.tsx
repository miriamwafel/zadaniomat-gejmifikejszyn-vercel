"use client";

interface GamificationStats {
  totalXp: number;
  currentLevel: number;
  level: number;
  currentLevelXP: number;
  nextLevelXP: number;
  prestige: number;
  freezeDaysAvailable: number;
}

interface GamificationPanelProps {
  stats: GamificationStats | null;
}

export function GamificationPanel({ stats }: GamificationPanelProps) {
  if (!stats) {
    return (
      <div className="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-6 text-white">
        <div className="animate-pulse">
          <div className="h-6 bg-white/20 rounded w-1/3 mb-4"></div>
          <div className="h-4 bg-white/20 rounded w-2/3"></div>
        </div>
      </div>
    );
  }

  const level = stats.level || stats.currentLevel;
  const progress = stats.nextLevelXP > 0
    ? (stats.currentLevelXP / stats.nextLevelXP) * 100
    : 100;

  return (
    <div className="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-6 text-white">
      <div className="flex items-center justify-between mb-4">
        <div>
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl font-bold">
              {level}
            </div>
            <div>
              <h2 className="text-xl font-bold">Poziom {level}</h2>
              <p className="text-purple-200 text-sm">
                {stats.totalXp.toLocaleString()} XP
              </p>
            </div>
          </div>
        </div>

        {stats.prestige > 0 && (
          <div className="text-right">
            <span className="text-yellow-300 text-2xl">★</span>
            <span className="text-sm ml-1">Prestiż {stats.prestige}</span>
          </div>
        )}
      </div>

      <div className="mb-2">
        <div className="flex justify-between text-sm text-purple-200 mb-1">
          <span>Postęp do poziomu {level + 1}</span>
          <span>{stats.currentLevelXP} / {stats.nextLevelXP} XP</span>
        </div>
        <div className="h-3 bg-white/20 rounded-full overflow-hidden">
          <div
            className="h-full bg-yellow-400 rounded-full transition-all duration-500"
            style={{ width: `${Math.min(progress, 100)}%` }}
          />
        </div>
      </div>

      {stats.freezeDaysAvailable > 0 && (
        <div className="mt-4 flex items-center gap-2 text-sm">
          <span className="text-2xl">❄️</span>
          <span>Zamrożenia: {stats.freezeDaysAvailable}</span>
        </div>
      )}
    </div>
  );
}
