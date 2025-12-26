"use client";

import { useState, useEffect, useCallback, useRef } from "react";
import { useSession, signOut } from "next-auth/react";
import Link from "next/link";
import { GamificationPanel } from "@/components/GamificationPanel";
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
  pozycjaHarmonogram?: number | null;
  jestCykliczne?: boolean | null;
}

interface Kategoria {
  id?: number;
  klucz: string;
  nazwa: string;
  typ?: string;
  isStrategic?: boolean;
  color?: string;
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

interface Cel {
  id: number;
  kategoria: string;
  cel: string;
  planowaneGodzinyDziennie?: string;
}

// Generate time slots for 15-minute schedule (6:00 - 22:00)
const generateTimeSlots = () => {
  const slots = [];
  for (let hour = 6; hour <= 22; hour++) {
    for (let min = 0; min < 60; min += 15) {
      const time = `${hour.toString().padStart(2, "0")}:${min.toString().padStart(2, "0")}`;
      slots.push(time);
    }
  }
  return slots;
};

const TIME_SLOTS = generateTimeSlots();

export default function Home() {
  const { data: session } = useSession();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [kategorie, setKategorie] = useState<Kategoria[]>([]);
  const [strategicKategorie, setStrategicKategorie] = useState<Kategoria[]>([]);
  const [cele, setCele] = useState<Cel[]>([]);
  const [gamificationStats, setGamificationStats] = useState<GamificationStats | null>(null);
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().split("T")[0]);
  const [showAddForm, setShowAddForm] = useState(false);
  const [loading, setLoading] = useState(true);
  const [dbInitialized, setDbInitialized] = useState<boolean | null>(null);
  const [initializingDb, setInitializingDb] = useState(false);
  const [activeView, setActiveView] = useState<"lista" | "harmonogram" | "cele">("lista");
  const [draggedTask, setDraggedTask] = useState<Task | null>(null);
  const [currentRok, setCurrentRok] = useState<{ id: number; nazwa: string } | null>(null);

  // Form state
  const [newTask, setNewTask] = useState({
    zadanie: "",
    kategoria: "",
    cel_todo: "",
    planowany_czas: 30,
    godzina_start: "",
  });

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
    } catch {
      alert("Błąd połączenia z bazą danych. Sprawdź konfigurację POSTGRES_URL.");
    } finally {
      setInitializingDb(false);
    }
  };

  // Załaduj dane
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [tasksRes, kategorieRes, strategicRes, gamificationRes, rokRes] = await Promise.all([
        fetch(`/api/zadania?dzien=${selectedDate}`),
        fetch("/api/kategorie"),
        fetch("/api/kategorie?strategic=true"),
        fetch("/api/gamification"),
        fetch("/api/roki?current=true"),
      ]);

      if (tasksRes.ok) {
        const tasksData = await tasksRes.json();
        setTasks(tasksData);
      }

      if (kategorieRes.ok) {
        const kategorieData = await kategorieRes.json();
        setKategorie(kategorieData);
        if (!newTask.kategoria && kategorieData.length > 0) {
          setNewTask((prev) => ({ ...prev, kategoria: kategorieData[0].klucz }));
        }
      }

      if (strategicRes.ok) {
        const strategicData = await strategicRes.json();
        setStrategicKategorie(strategicData);
      }

      if (gamificationRes.ok) {
        const gamificationData = await gamificationRes.json();
        setGamificationStats(gamificationData);
      }

      if (rokRes.ok) {
        const rokData = await rokRes.json();
        if (rokData) {
          setCurrentRok(rokData);
          // Load cele for current rok
          const celeRes = await fetch(`/api/cele?rok_id=${rokData.id}`);
          if (celeRes.ok) {
            const celeData = await celeRes.json();
            setCele(celeData);
          }
        }
      }
    } catch (error) {
      console.error("Error loading data:", error);
    } finally {
      setLoading(false);
    }
  }, [selectedDate, newTask.kategoria]);

  useEffect(() => {
    checkDb();
  }, [checkDb]);

  useEffect(() => {
    if (dbInitialized) {
      loadData();
    }
  }, [dbInitialized, loadData]);

  // Dodaj zadanie
  const handleAddTask = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTask.zadanie.trim() || !newTask.kategoria) return;

    try {
      const res = await fetch("/api/zadania", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...newTask,
          dzien: selectedDate,
        }),
      });

      if (res.ok) {
        setShowAddForm(false);
        setNewTask({
          zadanie: "",
          kategoria: kategorie[0]?.klucz || "",
          cel_todo: "",
          planowany_czas: 30,
          godzina_start: "",
        });
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

  // Drag & Drop handlers for schedule
  const handleDragStart = (task: Task) => {
    setDraggedTask(task);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = async (timeSlot: string) => {
    if (!draggedTask) return;

    // Calculate end time based on planowany_czas
    const [hours, minutes] = timeSlot.split(":").map(Number);
    const duration = draggedTask.planowanyCzas || 30;
    const endMinutes = hours * 60 + minutes + duration;
    const endHours = Math.floor(endMinutes / 60);
    const endMins = endMinutes % 60;
    const endTime = `${endHours.toString().padStart(2, "0")}:${endMins.toString().padStart(2, "0")}`;

    try {
      const res = await fetch("/api/zadania", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id: draggedTask.id,
          godzina_start: timeSlot,
          godzina_koniec: endTime,
        }),
      });

      if (res.ok) {
        loadData();
      }
    } catch (error) {
      console.error("Error updating task schedule:", error);
    }

    setDraggedTask(null);
  };

  // Zmień datę
  const changeDate = (days: number) => {
    const date = new Date(selectedDate);
    date.setDate(date.getDate() + days);
    setSelectedDate(date.toISOString().split("T")[0]);
  };

  // Get category color
  const getCategoryColor = (klucz: string) => {
    const kat = kategorie.find((k) => k.klucz === klucz);
    return kat?.color || "#6366f1";
  };

  // Get category name
  const getCategoryName = (klucz: string) => {
    const kat = kategorie.find((k) => k.klucz === klucz);
    return kat?.nazwa || klucz;
  };

  // Ekran inicjalizacji bazy
  if (dbInitialized === false) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
          <h1 className="text-2xl font-bold text-gray-900 mb-4">Zadaniomat OKR</h1>
          <p className="text-gray-600 mb-6">
            Baza danych nie jest jeszcze zainicjalizowana.
          </p>
          <div className="space-y-3">
            <button
              onClick={initDb}
              disabled={initializingDb}
              className="w-full bg-purple-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-purple-700 disabled:opacity-50 transition-colors"
            >
              {initializingDb ? "Inicjalizowanie..." : "Zainicjalizuj bazę danych"}
            </button>
            <Link
              href="/setup"
              className="block w-full bg-gray-200 text-gray-700 py-3 px-6 rounded-lg font-medium hover:bg-gray-300 transition-colors"
            >
              Przejdź do Setup (więcej opcji)
            </Link>
          </div>
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

  // Get tasks for a specific time slot
  const getTasksForSlot = (timeSlot: string) => {
    return tasks.filter((t) => {
      if (!t.godzinaStart) return false;
      const taskStart = t.godzinaStart.slice(0, 5);
      return taskStart === timeSlot;
    });
  };

  // Get unscheduled tasks
  const unscheduledTasks = tasks.filter((t) => !t.godzinaStart);

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Header */}
      <header className="bg-white shadow-sm sticky top-0 z-50">
        <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
          <h1 className="text-xl font-bold text-gray-900">Zadaniomat OKR</h1>
          <div className="flex items-center gap-4">
            {isAdmin && (
              <Link
                href="/admin"
                className="text-sm text-purple-600 hover:text-purple-800 font-medium"
              >
                Admin
              </Link>
            )}
            <span className="text-sm text-gray-600 hidden sm:inline">
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

      <main className="max-w-6xl mx-auto px-4 py-4 space-y-4">
        {/* Gamification Panel */}
        <GamificationPanel stats={gamificationStats} />

        {/* Date Navigation & View Switcher */}
        <div className="bg-white rounded-xl shadow-sm p-4">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            {/* Date nav */}
            <div className="flex items-center gap-4">
              <button
                onClick={() => changeDate(-1)}
                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
              </button>

              <div className="text-center min-w-[180px]">
                <h2 className="text-lg font-semibold text-gray-900">{formatDate(selectedDate)}</h2>
                <p className="text-sm text-gray-500">
                  {completedTasks} / {totalTasks} ukończonych
                </p>
              </div>

              <button
                onClick={() => changeDate(1)}
                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <button
                onClick={() => setSelectedDate(new Date().toISOString().split("T")[0])}
                className="text-sm text-purple-600 hover:text-purple-800 font-medium px-3 py-1 rounded-lg hover:bg-purple-50"
              >
                Dziś
              </button>
            </div>

            {/* View switcher */}
            <div className="flex bg-gray-100 rounded-lg p-1">
              <button
                onClick={() => setActiveView("lista")}
                className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                  activeView === "lista" ? "bg-white text-purple-600 shadow-sm" : "text-gray-600 hover:text-gray-900"
                }`}
              >
                Lista
              </button>
              <button
                onClick={() => setActiveView("harmonogram")}
                className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                  activeView === "harmonogram" ? "bg-white text-purple-600 shadow-sm" : "text-gray-600 hover:text-gray-900"
                }`}
              >
                Harmonogram
              </button>
              <button
                onClick={() => setActiveView("cele")}
                className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                  activeView === "cele" ? "bg-white text-purple-600 shadow-sm" : "text-gray-600 hover:text-gray-900"
                }`}
              >
                Cele
              </button>
            </div>
          </div>
        </div>

        {/* LISTA VIEW */}
        {activeView === "lista" && (
          <div className="space-y-4">
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
              <form onSubmit={handleAddTask} className="bg-white rounded-xl shadow-sm p-6 space-y-4">
                <h3 className="font-semibold text-gray-900">Nowe zadanie</h3>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Zadanie *</label>
                  <input
                    type="text"
                    value={newTask.zadanie}
                    onChange={(e) => setNewTask({ ...newTask, zadanie: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
                    placeholder="Co chcesz zrobić?"
                    required
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Kategoria</label>
                    <select
                      value={newTask.kategoria}
                      onChange={(e) => setNewTask({ ...newTask, kategoria: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
                    >
                      {kategorie.map((kat) => (
                        <option key={kat.klucz} value={kat.klucz}>
                          {kat.nazwa}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Planowany czas (min)</label>
                    <input
                      type="number"
                      value={newTask.planowany_czas}
                      onChange={(e) => setNewTask({ ...newTask, planowany_czas: parseInt(e.target.value) || 0 })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
                      min="0"
                      step="15"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Godzina startu</label>
                  <input
                    type="time"
                    value={newTask.godzina_start}
                    onChange={(e) => setNewTask({ ...newTask, godzina_start: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Cel / Notatka</label>
                  <textarea
                    value={newTask.cel_todo}
                    onChange={(e) => setNewTask({ ...newTask, cel_todo: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
                    rows={2}
                    placeholder="Dodatkowe informacje..."
                  />
                </div>

                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setShowAddForm(false)}
                    className="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                  >
                    Anuluj
                  </button>
                  <button
                    type="submit"
                    className="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                  >
                    Dodaj zadanie
                  </button>
                </div>
              </form>
            )}

            {/* Tasks List */}
            <div className="space-y-3">
              {tasks.length === 0 ? (
                <div className="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
                  <p>Brak zadań na ten dzień</p>
                  <p className="text-sm mt-1">Kliknij &quot;Dodaj zadanie&quot; aby rozpocząć</p>
                </div>
              ) : (
                tasks.map((task) => (
                  <div
                    key={task.id}
                    draggable
                    onDragStart={() => handleDragStart(task)}
                    className="bg-white rounded-xl shadow-sm p-4 border-l-4 cursor-move hover:shadow-md transition-shadow"
                    style={{ borderLeftColor: getCategoryColor(task.kategoria) }}
                  >
                    <div className="flex items-start gap-3">
                      <button
                        onClick={() =>
                          handleStatusChange(task.id, task.status === "zakonczone" ? "nowe" : "zakonczone")
                        }
                        className={`mt-1 w-5 h-5 rounded-full border-2 flex-shrink-0 flex items-center justify-center transition-colors ${
                          task.status === "zakonczone"
                            ? "bg-green-500 border-green-500 text-white"
                            : "border-gray-300 hover:border-purple-500"
                        }`}
                      >
                        {task.status === "zakonczone" && (
                          <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path
                              fillRule="evenodd"
                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                              clipRule="evenodd"
                            />
                          </svg>
                        )}
                      </button>

                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span
                            className="text-xs px-2 py-0.5 rounded-full text-white"
                            style={{ backgroundColor: getCategoryColor(task.kategoria) }}
                          >
                            {getCategoryName(task.kategoria)}
                          </span>
                          {task.godzinaStart && (
                            <span className="text-xs text-gray-500">
                              {task.godzinaStart.slice(0, 5)}
                              {task.godzinaKoniec && ` - ${task.godzinaKoniec.slice(0, 5)}`}
                            </span>
                          )}
                          {task.planowanyCzas && (
                            <span className="text-xs text-gray-400">{task.planowanyCzas} min</span>
                          )}
                        </div>

                        <p
                          className={`text-gray-900 ${task.status === "zakonczone" ? "line-through text-gray-400" : ""}`}
                        >
                          {task.zadanie}
                        </p>

                        {task.celTodo && <p className="text-sm text-gray-500 mt-1">{task.celTodo}</p>}
                      </div>

                      <button
                        onClick={() => handleDeleteTask(task.id)}
                        className="p-1.5 text-gray-400 hover:text-red-500 transition-colors"
                      >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                          />
                        </svg>
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}

        {/* HARMONOGRAM VIEW */}
        {activeView === "harmonogram" && (
          <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
            {/* Unscheduled tasks */}
            <div className="lg:col-span-1 bg-white rounded-xl shadow-sm p-4">
              <h3 className="font-semibold text-gray-900 mb-3">Nieprzydzielone</h3>
              <div className="space-y-2">
                {unscheduledTasks.length === 0 ? (
                  <p className="text-sm text-gray-400">Wszystkie zadania mają przypisane godziny</p>
                ) : (
                  unscheduledTasks.map((task) => (
                    <div
                      key={task.id}
                      draggable
                      onDragStart={() => handleDragStart(task)}
                      className="p-3 rounded-lg border-l-4 cursor-move bg-gray-50 hover:bg-gray-100 transition-colors"
                      style={{ borderLeftColor: getCategoryColor(task.kategoria) }}
                    >
                      <p className="text-sm text-gray-900 font-medium truncate">{task.zadanie}</p>
                      <div className="flex items-center gap-2 mt-1">
                        <span className="text-xs text-gray-500">{task.planowanyCzas || 30} min</span>
                        <span className="text-xs text-gray-400">{getCategoryName(task.kategoria)}</span>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            {/* Schedule grid */}
            <div className="lg:col-span-3 bg-white rounded-xl shadow-sm p-4 overflow-auto max-h-[70vh]">
              <h3 className="font-semibold text-gray-900 mb-3">Harmonogram dnia</h3>
              <div className="space-y-1">
                {TIME_SLOTS.map((slot) => {
                  const slotTasks = getTasksForSlot(slot);
                  const isHour = slot.endsWith(":00");

                  return (
                    <div
                      key={slot}
                      onDragOver={handleDragOver}
                      onDrop={() => handleDrop(slot)}
                      className={`flex items-stretch gap-3 min-h-[40px] ${
                        isHour ? "border-t border-gray-200 pt-1" : ""
                      }`}
                    >
                      <div className={`w-14 text-right ${isHour ? "font-medium text-gray-900" : "text-gray-400"}`}>
                        {isHour ? slot : ""}
                      </div>
                      <div
                        className={`flex-1 rounded-lg border-2 border-dashed p-1 min-h-[36px] ${
                          draggedTask ? "border-purple-300 bg-purple-50" : "border-transparent"
                        }`}
                      >
                        {slotTasks.map((task) => (
                          <div
                            key={task.id}
                            draggable
                            onDragStart={() => handleDragStart(task)}
                            onClick={() =>
                              handleStatusChange(task.id, task.status === "zakonczone" ? "nowe" : "zakonczone")
                            }
                            className={`p-2 rounded-md text-sm cursor-pointer mb-1 ${
                              task.status === "zakonczone"
                                ? "bg-gray-100 text-gray-400 line-through"
                                : "text-white"
                            }`}
                            style={{
                              backgroundColor: task.status !== "zakonczone" ? getCategoryColor(task.kategoria) : undefined,
                            }}
                          >
                            <span className="font-medium">{task.zadanie}</span>
                            <span className="ml-2 opacity-75">({task.planowanyCzas || 30}min)</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        )}

        {/* CELE VIEW */}
        {activeView === "cele" && (
          <div className="space-y-6">
            {/* Current Rok info */}
            {currentRok ? (
              <div className="bg-white rounded-xl shadow-sm p-4">
                <h3 className="font-semibold text-gray-900">Aktualny Rok: {currentRok.nazwa}</h3>
              </div>
            ) : (
              <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <p className="text-yellow-800">
                  Nie masz utworzonego roku planowania.{" "}
                  <Link href="/admin" className="text-purple-600 hover:underline">
                    Utwórz rok w panelu admina
                  </Link>
                </p>
              </div>
            )}

            {/* Strategic categories with goals */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {strategicKategorie.map((kat) => {
                const cel = cele.find((c) => c.kategoria === kat.klucz);
                const categoryTasks = tasks.filter((t) => t.kategoria === kat.klucz);
                const completedCatTasks = categoryTasks.filter((t) => t.status === "zakonczone").length;

                return (
                  <div
                    key={kat.klucz}
                    className="bg-white rounded-xl shadow-sm p-5 border-t-4"
                    style={{ borderTopColor: kat.color || "#6366f1" }}
                  >
                    <div className="flex items-center justify-between mb-3">
                      <h3 className="font-semibold text-gray-900">{kat.nazwa}</h3>
                      <span
                        className="w-3 h-3 rounded-full"
                        style={{ backgroundColor: kat.color || "#6366f1" }}
                      ></span>
                    </div>

                    {cel?.cel ? (
                      <p className="text-sm text-gray-600 mb-3">{cel.cel}</p>
                    ) : (
                      <p className="text-sm text-gray-400 italic mb-3">Brak celu na ten rok</p>
                    )}

                    {cel?.planowaneGodzinyDziennie && (
                      <p className="text-xs text-gray-500 mb-2">
                        Plan: {cel.planowaneGodzinyDziennie}h dziennie
                      </p>
                    )}

                    <div className="mt-3 pt-3 border-t border-gray-100">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-500">Dziś:</span>
                        <span className="font-medium">
                          {completedCatTasks}/{categoryTasks.length} zadań
                        </span>
                      </div>
                      {categoryTasks.length > 0 && (
                        <div className="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                          <div
                            className="h-full rounded-full transition-all"
                            style={{
                              width: `${(completedCatTasks / categoryTasks.length) * 100}%`,
                              backgroundColor: kat.color || "#6366f1",
                            }}
                          ></div>
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>

            {strategicKategorie.length === 0 && (
              <div className="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">
                <p>Brak kategorii strategicznych</p>
                <p className="text-sm mt-1">
                  Ustaw kategorie jako strategiczne w panelu admina
                </p>
              </div>
            )}
          </div>
        )}
      </main>
    </div>
  );
}
