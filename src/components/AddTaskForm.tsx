"use client";

import { useState } from "react";

interface Kategoria {
  klucz: string;
  nazwa: string;
}

interface AddTaskFormProps {
  kategorie: Kategoria[];
  selectedDate: string;
  onAdd: (task: {
    kategoria: string;
    dzien: string;
    zadanie: string;
    cel_todo?: string;
    planowany_czas?: number;
    godzina_start?: string;
  }) => void;
  onCancel: () => void;
}

export function AddTaskForm({ kategorie, selectedDate, onAdd, onCancel }: AddTaskFormProps) {
  const [zadanie, setZadanie] = useState("");
  const [kategoria, setKategoria] = useState(kategorie[0]?.klucz || "");
  const [celTodo, setCelTodo] = useState("");
  const [planowanyCzas, setPlanowanyCzas] = useState(30);
  const [godzinaStart, setGodzinaStart] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!zadanie.trim()) return;

    onAdd({
      kategoria,
      dzien: selectedDate,
      zadanie: zadanie.trim(),
      cel_todo: celTodo.trim() || undefined,
      planowany_czas: planowanyCzas,
      godzina_start: godzinaStart || undefined,
    });

    // Reset form
    setZadanie("");
    setCelTodo("");
    setPlanowanyCzas(30);
    setGodzinaStart("");
  };

  return (
    <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-lg p-6 border">
      <h3 className="text-lg font-semibold mb-4">Nowe zadanie</h3>

      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Zadanie *
          </label>
          <input
            type="text"
            value={zadanie}
            onChange={(e) => setZadanie(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
            placeholder="Co chcesz zrobić?"
            autoFocus
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Kategoria
          </label>
          <select
            value={kategoria}
            onChange={(e) => setKategoria(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
          >
            {kategorie.map((k) => (
              <option key={k.klucz} value={k.klucz}>
                {k.nazwa}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Cel / Notatka
          </label>
          <textarea
            value={celTodo}
            onChange={(e) => setCelTodo(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
            rows={2}
            placeholder="Dodatkowe informacje..."
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Planowany czas (min)
            </label>
            <input
              type="number"
              value={planowanyCzas}
              onChange={(e) => setPlanowanyCzas(parseInt(e.target.value) || 0)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
              min={0}
              step={5}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Godzina startu
            </label>
            <input
              type="time"
              value={godzinaStart}
              onChange={(e) => setGodzinaStart(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 bg-white"
            />
          </div>
        </div>
      </div>

      <div className="flex justify-end gap-3 mt-6">
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
        >
          Anuluj
        </button>
        <button
          type="submit"
          disabled={!zadanie.trim()}
          className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Dodaj zadanie
        </button>
      </div>
    </form>
  );
}
