"use client";

import { formatTime, formatMinutes, getStatusColor, getStatusLabel, cn } from "@/lib/utils";

interface Task {
  id: number;
  kategoria: string;
  dzien: string;
  zadanie: string;
  celTodo?: string | null;
  planowanyCzas?: number | null;
  faktycznyCzas?: number | null;
  status: string | null;
  godzinaStart?: string | null;
  godzinaKoniec?: string | null;
  jestCykliczne?: boolean | null;
}

interface TaskCardProps {
  task: Task;
  onStatusChange?: (id: number, status: string) => void;
  onDelete?: (id: number) => void;
}

const KATEGORIA_COLORS: Record<string, string> = {
  zapianowany: "border-l-blue-500",
  klejpan: "border-l-purple-500",
  marka_langer: "border-l-orange-500",
  marketing_construction: "border-l-green-500",
  fjo: "border-l-pink-500",
  obsluga_telefoniczna: "border-l-cyan-500",
  sprawy_organizacyjne: "border-l-gray-500",
};

export function TaskCard({ task, onStatusChange, onDelete }: TaskCardProps) {
  const handleStatusChange = async (newStatus: string) => {
    if (onStatusChange) {
      onStatusChange(task.id, newStatus);
    }
  };

  const borderColor = KATEGORIA_COLORS[task.kategoria] || "border-l-gray-400";
  const isCompleted = task.status === "zakonczone";

  return (
    <div
      className={cn(
        "bg-white rounded-lg shadow-sm border-l-4 p-4 transition-all hover:shadow-md",
        borderColor,
        isCompleted && "opacity-60"
      )}
    >
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            {task.godzinaStart && (
              <span className="text-sm text-gray-500 font-mono">
                {formatTime(task.godzinaStart)}
                {task.godzinaKoniec && ` - ${formatTime(task.godzinaKoniec)}`}
              </span>
            )}
            {task.jestCykliczne && (
              <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                cykliczne
              </span>
            )}
          </div>

          <h3 className={cn("font-medium text-gray-900", isCompleted && "line-through")}>
            {task.zadanie}
          </h3>

          {task.celTodo && (
            <p className="text-sm text-gray-600 mt-1">{task.celTodo}</p>
          )}

          <div className="flex items-center gap-3 mt-2 text-sm text-gray-500">
            <span className="capitalize">{task.kategoria.replace(/_/g, " ")}</span>
            {task.planowanyCzas && task.planowanyCzas > 0 && (
              <span>
                {formatMinutes(task.planowanyCzas)}
                {task.faktycznyCzas && ` / ${formatMinutes(task.faktycznyCzas)}`}
              </span>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2">
          <select
            value={task.status || "nowe"}
            onChange={(e) => handleStatusChange(e.target.value)}
            className={cn(
              "text-sm rounded-full px-3 py-1 border-0 cursor-pointer",
              task.status === "zakonczone" && "bg-green-100 text-green-800",
              task.status === "w_trakcie" && "bg-yellow-100 text-yellow-800",
              (!task.status || task.status === "nowe") && "bg-gray-100 text-gray-800"
            )}
          >
            <option value="nowe">Nowe</option>
            <option value="w_trakcie">W trakcie</option>
            <option value="zakonczone">Zakończone</option>
          </select>

          {onDelete && (
            <button
              onClick={() => onDelete(task.id)}
              className="text-gray-400 hover:text-red-500 transition-colors p-1"
              title="Usuń"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
