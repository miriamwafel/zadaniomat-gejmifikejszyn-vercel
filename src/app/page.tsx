"use client";

import { useState, useEffect, useCallback } from "react";
import { useSession, signOut } from "next-auth/react";
import Link from "next/link";
import { TaskCard } from "@/components/TaskCard";
import { GamificationPanel } from "@/components/GamificationPanel";
import { AddTaskForm } from "@/components/AddTaskForm";
import { formatDate } from "@/lib/utils";

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

interface Kategoria {
  klucz: string;
  nazwa: string;
}

interface GamificationStats {
  totalXp: number;
  currentLevel: number;
  level: number;
  currentLevelXP: number;
  nextLevelXP: number;
  prestige: number;
  freezeDaysAvailable: number;
}

export default function Home() {
  const { data: session } = useSession();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [kategorie, setKategorie] = useState<Kategoria[]>([]);
  const [gamificationStats, setGamificationStats] = useState<GamificationStats | null>(null);
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().split("T")[0]);
  const [showAddForm, setShowAddForm] = useState(false);
  const [loading, setLoading] = useState(true);
  const [dbInitialized, setDbInitialized] = useState<boolean | null>(null);
  const [initializingDb, setInitializingDb] = useState(false);

  const isAdmin = session?.user?.role === "admin" || session?.user?.role === "super_admin";

  // Sprawdź stan bazy danych
  const checkDb = useCallback(async () => {
    try {
      const res = await fetch("/api/init-db");
      const data = await res.json();
      setDbInitialized(data.status === "connected");
    } catch {
      setDbInitialized(false);
    }
  }, []);

  // Inicjalizuj bazę danych
  const initDb = async () => {
    setInitializingDb(true);
    try {
      const res = await fetch("/api/init-db", { method: "POST" });
      const data = await res.json();
      if (data.success) {
        setDbInitialized(true);
        loadData();
      } else {
        alert("Błąd inicjalizacji bazy: " + (data.error || "Unknown error"));
      }
    } catch (error) {
      alert("Błąd połączenia z bazą danych. Sprawdź konfigurację POSTGRES_URL.");
    } finally {
      setInitializingDb(false);
    }
  };

  // Załaduj dane
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [tasksRes, kategorieRes, gamificationRes] = await Promise.all([
        fetch(`/api/zadania?dzien=${selectedDate}`),
        fetch("/api/kategorie"),
        fetch("/api/gamification"),
      ]);

      if (tasksRes.ok) {
        const tasksData = await tasksRes.json();
        setTasks(tasksData);
      }

      if (kategorieRes.ok) {
        const kategorieData = await kategorieRes.json();
        setKategorie(kategorieData);
      }

      if (gamificationRes.ok) {
        const gamificationData = await gamificationRes.json();
        setGamificationStats(gamificationData);
      }
    } catch (error) {
      console.error("Error loading data:", error);
    } finally {
      setLoading(false);
    }
  }, [selectedDate]);

  useEffect(() => {
    checkDb();
  }, [checkDb]);

  useEffect(() => {
    if (dbInitialized) {
      loadData();
    }
  }, [dbInitialized, loadData]);

  // Dodaj zadanie
  const handleAddTask = async (taskData: {
    kategoria: string;
    dzien: string;
    zadanie: string;
    cel_todo?: string;
    planowany_czas?: number;
    godzina_start?: string;
  }) => {
    try {
      const res = await fetch("/api/zadania", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(taskData),
      });

      if (res.ok) {
        setShowAddForm(false);
        loadData();
      }
    } catch (error) {
      console.error("Error adding task:", error);
    }
  };

  // Zmień status zadania
  const handleStatusChange = async (id: number, status: string) => {
    try {
      const res = await fetch("/api/zadania", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, status }),
      });

      if (res.ok) {
        // Dodaj XP za ukończenie zadania
        if (status === "zakonczone") {
          await fetch("/api/gamification", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              xp_amount: 10,
              xp_type: "task_complete",
              description: "Ukończono zadanie",
              reference_id: id,
              reference_type: "zadanie",
            }),
          });
        }
        loadData();
      }
    } catch (error) {
      console.error("Error updating task:", error);
    }
  };

  // Usuń zadanie
  const handleDeleteTask = async (id: number) => {
    if (!confirm("Czy na pewno chcesz usunąć to zadanie?")) return;

    try {
      const res = await fetch(`/api/zadania?id=${id}`, {
        method: "DELETE",
      });

      if (res.ok) {
        loadData();
      }
    } catch (error) {
      console.error("Error deleting task:", error);
    }
  };

  // Zmień datę
  const changeDate = (days: number) => {
    const date = new Date(selectedDate);
    date.setDate(date.getDate() + days);
    setSelectedDate(date.toISOString().split("T")[0]);
  };

  // Ekran inicjalizacji bazy
  if (dbInitialized === false) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
          <h1 className="text-2xl font-bold text-gray-900 mb-4">Zadaniomat OKR</h1>
          <p className="text-gray-600 mb-6">
            Baza danych nie jest jeszcze zainicjalizowana. Kliknij poniżej, aby utworzyć tabele.
          </p>
          <button
            onClick={initDb}
            disabled={initializingDb}
            className="w-full bg-purple-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-purple-700 disabled:opacity-50 transition-colors"
          >
            {initializingDb ? "Inicjalizowanie..." : "Zainicjalizuj bazę danych"}
          </button>
          <p className="text-sm text-gray-500 mt-4">
            Upewnij się, że zmienne środowiskowe POSTGRES_URL są poprawnie skonfigurowane.
          </p>
        </div>
      </div>
    );
  }

  // Ekran ładowania
  if (dbInitialized === null || loading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
      </div>
    );
  }

  const completedTasks = tasks.filter((t) => t.status === "zakonczone").length;
  const totalTasks = tasks.length;

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-gray-900">Zadaniomat OKR</h1>
          <div className="flex items-center gap-4">
            {isAdmin && (
              <Link
                href="/admin"
                className="text-sm text-purple-600 hover:text-purple-800 font-medium"
              >
                Panel Admina
              </Link>
            )}
            <span className="text-sm text-gray-600">
              {session?.user?.name || session?.user?.email}
            </span>
            <button
              onClick={() => signOut()}
              className="text-sm text-gray-500 hover:text-gray-700"
            >
              Wyloguj
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-4 py-6 space-y-6">
        {/* Gamification Panel */}
        <GamificationPanel stats={gamificationStats} />

        {/* Date Navigation */}
        <div className="bg-white rounded-xl shadow-sm p-4">
          <div className="flex items-center justify-between">
            <button
              onClick={() => changeDate(-1)}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <div className="text-center">
              <h2 className="text-lg font-semibold text-gray-900">
                {formatDate(selectedDate)}
              </h2>
              <p className="text-sm text-gray-500">
                {completedTasks} / {totalTasks} ukończonych
              </p>
            </div>

            <button
              onClick={() => changeDate(1)}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div className="flex justify-center gap-2 mt-3">
            <button
              onClick={() => setSelectedDate(new Date().toISOString().split("T")[0])}
              className="text-sm text-purple-600 hover:text-purple-800 font-medium"
            >
              Dziś
            </button>
          </div>
        </div>

        {/* Add Task Button */}
        {!showAddForm && (
          <button
            onClick={() => setShowAddForm(true)}
            className="w-full bg-white rounded-xl shadow-sm p-4 border-2 border-dashed border-gray-300 text-gray-500 hover:border-purple-400 hover:text-purple-600 transition-colors flex items-center justify-center gap-2"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Dodaj zadanie
          </button>
        )}

        {/* Add Task Form */}
        {showAddForm && (
          <AddTaskForm
            kategorie={kategorie}
            selectedDate={selectedDate}
            onAdd={handleAddTask}
            onCancel={() => setShowAddForm(false)}
          />
        )}

        {/* Tasks List */}
        <div className="space-y-3">
          {tasks.length === 0 ? (
            <div className="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
              <p>Brak zadań na ten dzień</p>
              <p className="text-sm mt-1">Kliknij "Dodaj zadanie" aby rozpocząć</p>
            </div>
          ) : (
            tasks.map((task) => (
              <TaskCard
                key={task.id}
                task={task}
                onStatusChange={handleStatusChange}
                onDelete={handleDeleteTask}
              />
            ))
          )}
        </div>
      </main>
    </div>
  );
}
