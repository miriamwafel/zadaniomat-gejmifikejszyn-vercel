"use client";

import { useState, useEffect } from "react";
import { useSession } from "next-auth/react";
import Link from "next/link";

interface User {
  id: number;
  email: string;
  name: string | null;
  role: string;
  active: boolean;
  createdAt: string;
}

interface Kategoria {
  id: number;
  klucz: string;
  nazwa: string;
  typ: string;
  isStrategic: boolean;
  color: string;
  aktywne: boolean;
}

interface Rok {
  id: number;
  nazwa: string;
  dataStart: string;
  dataKoniec: string;
}

interface Cel {
  id: number;
  rokId: number;
  kategoria: string;
  cel: string;
  planowaneGodzinyDziennie: string;
}

export default function AdminPage() {
  const { data: session } = useSession();
  const [activeTab, setActiveTab] = useState<"users" | "kategorie" | "roki">("users");

  // Users state
  const [users, setUsers] = useState<User[]>([]);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [newPassword, setNewPassword] = useState("");

  // Kategorie state
  const [kategorie, setKategorie] = useState<Kategoria[]>([]);
  const [editingKategoria, setEditingKategoria] = useState<Kategoria | null>(null);
  const [newKategoria, setNewKategoria] = useState({ klucz: "", nazwa: "", color: "#6366f1", isStrategic: false });
  const [showNewKategoria, setShowNewKategoria] = useState(false);

  // Roki state
  const [roki, setRoki] = useState<Rok[]>([]);
  const [cele, setCele] = useState<Cel[]>([]);
  const [selectedRok, setSelectedRok] = useState<Rok | null>(null);
  const [showNewRok, setShowNewRok] = useState(false);
  const [newRok, setNewRok] = useState({ nazwa: "", dataStart: "", dataKoniec: "" });
  const [editingCel, setEditingCel] = useState<{ kategoria: string; cel: string; planowaneGodzinyDziennie: string } | null>(null);

  const [loading, setLoading] = useState(true);

  const isSuperAdmin = session?.user?.role === "super_admin";
  const isAdmin = session?.user?.role === "admin" || isSuperAdmin;

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [usersRes, kategorieRes, rokiRes] = await Promise.all([
        fetch("/api/users"),
        fetch("/api/kategorie"),
        fetch("/api/roki"),
      ]);

      if (usersRes.ok) setUsers(await usersRes.json());
      if (kategorieRes.ok) setKategorie(await kategorieRes.json());
      if (rokiRes.ok) {
        const rokiData = await rokiRes.json();
        setRoki(rokiData);
        if (rokiData.length > 0) {
          setSelectedRok(rokiData[0]);
          loadCele(rokiData[0].id);
        }
      }
    } catch (error) {
      console.error("Error loading data:", error);
    } finally {
      setLoading(false);
    }
  };

  const loadCele = async (rokId: number) => {
    try {
      const res = await fetch(`/api/cele?rok_id=${rokId}`);
      if (res.ok) {
        setCele(await res.json());
      }
    } catch (error) {
      console.error("Error loading cele:", error);
    }
  };

  // USER HANDLERS
  const handleUpdateUser = async (userId: number, updates: Partial<User> & { password?: string }) => {
    try {
      const res = await fetch("/api/users", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: userId, ...updates }),
      });

      if (res.ok) {
        loadData();
        setEditingUser(null);
        setNewPassword("");
      } else {
        const data = await res.json();
        alert(data.error || "Błąd aktualizacji");
      }
    } catch (error) {
      console.error("Error updating user:", error);
    }
  };

  const handleDeleteUser = async (userId: number) => {
    if (!confirm("Czy na pewno chcesz usunąć tego użytkownika?")) return;
    try {
      const res = await fetch(`/api/users?id=${userId}`, { method: "DELETE" });
      if (res.ok) loadData();
      else alert((await res.json()).error || "Błąd usuwania");
    } catch (error) {
      console.error("Error deleting user:", error);
    }
  };

  // KATEGORIE HANDLERS
  const handleCreateKategoria = async () => {
    if (!newKategoria.klucz || !newKategoria.nazwa) return;
    try {
      const res = await fetch("/api/kategorie", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          klucz: newKategoria.klucz.toLowerCase().replace(/\s+/g, "_"),
          nazwa: newKategoria.nazwa,
          color: newKategoria.color,
          is_strategic: newKategoria.isStrategic,
        }),
      });
      if (res.ok) {
        loadData();
        setNewKategoria({ klucz: "", nazwa: "", color: "#6366f1", isStrategic: false });
        setShowNewKategoria(false);
      }
    } catch (error) {
      console.error("Error creating kategoria:", error);
    }
  };

  const handleUpdateKategoria = async (id: number, updates: Partial<Kategoria>) => {
    try {
      const res = await fetch("/api/kategorie", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          id,
          ...updates,
          is_strategic: updates.isStrategic,
        }),
      });
      if (res.ok) {
        loadData();
        setEditingKategoria(null);
      }
    } catch (error) {
      console.error("Error updating kategoria:", error);
    }
  };

  // ROKI HANDLERS
  const handleCreateRok = async () => {
    if (!newRok.nazwa || !newRok.dataStart || !newRok.dataKoniec) return;
    try {
      const res = await fetch("/api/roki", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          nazwa: newRok.nazwa,
          data_start: newRok.dataStart,
          data_koniec: newRok.dataKoniec,
        }),
      });
      if (res.ok) {
        loadData();
        setNewRok({ nazwa: "", dataStart: "", dataKoniec: "" });
        setShowNewRok(false);
      }
    } catch (error) {
      console.error("Error creating rok:", error);
    }
  };

  const handleDeleteRok = async (rokId: number) => {
    if (!confirm("Usunięcie roku usunie również wszystkie powiązane cele. Kontynuować?")) return;
    try {
      const res = await fetch(`/api/roki?id=${rokId}`, { method: "DELETE" });
      if (res.ok) {
        loadData();
        setSelectedRok(null);
      }
    } catch (error) {
      console.error("Error deleting rok:", error);
    }
  };

  // CELE HANDLERS
  const handleSaveCel = async (kategoria: string, cel: string, planowaneGodzinyDziennie: string) => {
    if (!selectedRok) return;

    const existing = cele.find(c => c.kategoria === kategoria);

    try {
      if (existing) {
        const res = await fetch("/api/cele", {
          method: "PUT",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            id: existing.id,
            cel,
            planowane_godziny_dziennie: planowaneGodzinyDziennie,
          }),
        });
        if (res.ok) loadCele(selectedRok.id);
      } else {
        const res = await fetch("/api/cele", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            rok_id: selectedRok.id,
            kategoria,
            cel,
            planowane_godziny_dziennie: planowaneGodzinyDziennie,
          }),
        });
        if (res.ok) loadCele(selectedRok.id);
      }
      setEditingCel(null);
    } catch (error) {
      console.error("Error saving cel:", error);
    }
  };

  const getRoleBadgeColor = (role: string) => {
    switch (role) {
      case "super_admin": return "bg-red-100 text-red-800";
      case "admin": return "bg-orange-100 text-orange-800";
      default: return "bg-gray-100 text-gray-800";
    }
  };

  const getRoleLabel = (role: string) => {
    switch (role) {
      case "super_admin": return "Super Admin";
      case "admin": return "Admin";
      default: return "Użytkownik";
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
      </div>
    );
  }

  const strategicKategorie = kategorie.filter(k => k.isStrategic);

  return (
    <div className="min-h-screen bg-gray-100">
      <header className="bg-white shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Panel Admina</h1>
            <p className="text-sm text-gray-500">Zarządzanie systemem</p>
          </div>
          <Link href="/" className="text-purple-600 hover:text-purple-800 font-medium">
            ← Powrót do aplikacji
          </Link>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-4 py-6 space-y-6">
        {/* Tab navigation */}
        <div className="bg-white rounded-xl shadow-sm">
          <div className="flex border-b">
            <button
              onClick={() => setActiveTab("users")}
              className={`px-6 py-4 font-medium transition-colors ${
                activeTab === "users"
                  ? "text-purple-600 border-b-2 border-purple-600"
                  : "text-gray-500 hover:text-gray-700"
              }`}
            >
              Użytkownicy
            </button>
            <button
              onClick={() => setActiveTab("kategorie")}
              className={`px-6 py-4 font-medium transition-colors ${
                activeTab === "kategorie"
                  ? "text-purple-600 border-b-2 border-purple-600"
                  : "text-gray-500 hover:text-gray-700"
              }`}
            >
              Kategorie
            </button>
            <button
              onClick={() => setActiveTab("roki")}
              className={`px-6 py-4 font-medium transition-colors ${
                activeTab === "roki"
                  ? "text-purple-600 border-b-2 border-purple-600"
                  : "text-gray-500 hover:text-gray-700"
              }`}
            >
              Roki i Cele
            </button>
          </div>
        </div>

        {/* USERS TAB */}
        {activeTab === "users" && (
          <div className="bg-white rounded-xl shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-semibold text-gray-900">Użytkownicy ({users.length})</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Użytkownik</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rola</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Akcje</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {users.map((user) => (
                    <tr key={user.id}>
                      <td className="px-6 py-4">
                        <div className="text-sm font-medium text-gray-900">{user.name || "—"}</div>
                        <div className="text-sm text-gray-500">{user.email}</div>
                      </td>
                      <td className="px-6 py-4">
                        {editingUser?.id === user.id ? (
                          <select
                            value={editingUser.role}
                            onChange={(e) => setEditingUser({ ...editingUser, role: e.target.value })}
                            className="text-sm border rounded px-2 py-1"
                            disabled={!isSuperAdmin}
                          >
                            <option value="user">Użytkownik</option>
                            <option value="admin">Admin</option>
                            {isSuperAdmin && <option value="super_admin">Super Admin</option>}
                          </select>
                        ) : (
                          <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(user.role)}`}>
                            {getRoleLabel(user.role)}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        {editingUser?.id === user.id ? (
                          <select
                            value={editingUser.active ? "active" : "inactive"}
                            onChange={(e) => setEditingUser({ ...editingUser, active: e.target.value === "active" })}
                            className="text-sm border rounded px-2 py-1"
                          >
                            <option value="active">Aktywny</option>
                            <option value="inactive">Nieaktywny</option>
                          </select>
                        ) : (
                          <span className={`px-2 py-1 text-xs font-semibold rounded-full ${user.active ? "bg-green-100 text-green-800" : "bg-gray-100 text-gray-800"}`}>
                            {user.active ? "Aktywny" : "Nieaktywny"}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-500">
                        {new Date(user.createdAt).toLocaleDateString("pl-PL")}
                      </td>
                      <td className="px-6 py-4 text-right text-sm">
                        {editingUser?.id === user.id ? (
                          <div className="flex items-center justify-end gap-2">
                            <input
                              type="password"
                              placeholder="Nowe hasło"
                              value={newPassword}
                              onChange={(e) => setNewPassword(e.target.value)}
                              className="text-sm border rounded px-2 py-1 w-28"
                            />
                            <button
                              onClick={() => handleUpdateUser(user.id, {
                                role: editingUser.role,
                                active: editingUser.active,
                                ...(newPassword && { password: newPassword }),
                              })}
                              className="text-green-600 hover:text-green-800 font-medium"
                            >
                              Zapisz
                            </button>
                            <button
                              onClick={() => { setEditingUser(null); setNewPassword(""); }}
                              className="text-gray-600 hover:text-gray-800"
                            >
                              Anuluj
                            </button>
                          </div>
                        ) : (
                          <div className="flex items-center justify-end gap-3">
                            <button onClick={() => setEditingUser(user)} className="text-purple-600 hover:text-purple-800 font-medium">
                              Edytuj
                            </button>
                            {isSuperAdmin && user.id !== parseInt(session?.user?.id || "0") && (
                              <button onClick={() => handleDeleteUser(user.id)} className="text-red-600 hover:text-red-800 font-medium">
                                Usuń
                              </button>
                            )}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* KATEGORIE TAB */}
        {activeTab === "kategorie" && (
          <div className="space-y-4">
            <div className="bg-white rounded-xl shadow-sm p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-semibold text-gray-900">Kategorie ({kategorie.length})</h2>
                {isAdmin && (
                  <button
                    onClick={() => setShowNewKategoria(!showNewKategoria)}
                    className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                  >
                    + Nowa kategoria
                  </button>
                )}
              </div>

              {showNewKategoria && (
                <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                  <h3 className="font-medium text-gray-900 mb-3">Nowa kategoria</h3>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <input
                      type="text"
                      placeholder="Klucz (np. marketing)"
                      value={newKategoria.klucz}
                      onChange={(e) => setNewKategoria({ ...newKategoria, klucz: e.target.value })}
                      className="px-3 py-2 border rounded-lg text-gray-900"
                    />
                    <input
                      type="text"
                      placeholder="Nazwa (np. Marketing)"
                      value={newKategoria.nazwa}
                      onChange={(e) => setNewKategoria({ ...newKategoria, nazwa: e.target.value })}
                      className="px-3 py-2 border rounded-lg text-gray-900"
                    />
                    <div className="flex items-center gap-2">
                      <input
                        type="color"
                        value={newKategoria.color}
                        onChange={(e) => setNewKategoria({ ...newKategoria, color: e.target.value })}
                        className="w-10 h-10 rounded cursor-pointer"
                      />
                      <label className="flex items-center gap-2">
                        <input
                          type="checkbox"
                          checked={newKategoria.isStrategic}
                          onChange={(e) => setNewKategoria({ ...newKategoria, isStrategic: e.target.checked })}
                          className="w-4 h-4"
                        />
                        <span className="text-sm">Strategiczna</span>
                      </label>
                    </div>
                    <div className="flex gap-2">
                      <button onClick={handleCreateKategoria} className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Dodaj
                      </button>
                      <button onClick={() => setShowNewKategoria(false)} className="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Anuluj
                      </button>
                    </div>
                  </div>
                </div>
              )}

              <div className="space-y-2">
                {kategorie.map((kat) => (
                  <div
                    key={kat.id}
                    className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border-l-4"
                    style={{ borderLeftColor: kat.color }}
                  >
                    {editingKategoria?.id === kat.id ? (
                      <div className="flex-1 flex items-center gap-4">
                        <input
                          type="text"
                          value={editingKategoria.nazwa}
                          onChange={(e) => setEditingKategoria({ ...editingKategoria, nazwa: e.target.value })}
                          className="px-3 py-1 border rounded text-gray-900"
                        />
                        <input
                          type="color"
                          value={editingKategoria.color}
                          onChange={(e) => setEditingKategoria({ ...editingKategoria, color: e.target.value })}
                          className="w-8 h-8 rounded cursor-pointer"
                        />
                        <label className="flex items-center gap-2">
                          <input
                            type="checkbox"
                            checked={editingKategoria.isStrategic}
                            onChange={(e) => setEditingKategoria({ ...editingKategoria, isStrategic: e.target.checked })}
                            className="w-4 h-4"
                          />
                          <span className="text-sm">Strategiczna</span>
                        </label>
                        <button
                          onClick={() => handleUpdateKategoria(kat.id, editingKategoria)}
                          className="text-green-600 hover:text-green-800 font-medium"
                        >
                          Zapisz
                        </button>
                        <button onClick={() => setEditingKategoria(null)} className="text-gray-500">
                          Anuluj
                        </button>
                      </div>
                    ) : (
                      <>
                        <div className="flex items-center gap-3">
                          <span className="w-4 h-4 rounded-full" style={{ backgroundColor: kat.color }}></span>
                          <span className="font-medium text-gray-900">{kat.nazwa}</span>
                          <span className="text-xs text-gray-500">({kat.klucz})</span>
                          {kat.isStrategic && (
                            <span className="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full">
                              Strategiczna
                            </span>
                          )}
                        </div>
                        {isAdmin && (
                          <button
                            onClick={() => setEditingKategoria(kat)}
                            className="text-purple-600 hover:text-purple-800 font-medium text-sm"
                          >
                            Edytuj
                          </button>
                        )}
                      </>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* ROKI TAB */}
        {activeTab === "roki" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Lista roków */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-semibold text-gray-900">Roki planowania</h2>
                <button
                  onClick={() => setShowNewRok(!showNewRok)}
                  className="px-3 py-1 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700"
                >
                  + Nowy
                </button>
              </div>

              {showNewRok && (
                <div className="mb-4 p-4 bg-gray-50 rounded-lg space-y-3">
                  <input
                    type="text"
                    placeholder="Nazwa (np. Q1 2025)"
                    value={newRok.nazwa}
                    onChange={(e) => setNewRok({ ...newRok, nazwa: e.target.value })}
                    className="w-full px-3 py-2 border rounded-lg text-gray-900"
                  />
                  <div className="grid grid-cols-2 gap-2">
                    <input
                      type="date"
                      value={newRok.dataStart}
                      onChange={(e) => setNewRok({ ...newRok, dataStart: e.target.value })}
                      className="px-3 py-2 border rounded-lg text-gray-900"
                    />
                    <input
                      type="date"
                      value={newRok.dataKoniec}
                      onChange={(e) => setNewRok({ ...newRok, dataKoniec: e.target.value })}
                      className="px-3 py-2 border rounded-lg text-gray-900"
                    />
                  </div>
                  <div className="flex gap-2">
                    <button onClick={handleCreateRok} className="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg">
                      Dodaj
                    </button>
                    <button onClick={() => setShowNewRok(false)} className="px-3 py-2 bg-gray-300 text-gray-700 rounded-lg">
                      Anuluj
                    </button>
                  </div>
                </div>
              )}

              <div className="space-y-2">
                {roki.length === 0 ? (
                  <p className="text-gray-500 text-sm">Brak roków. Utwórz pierwszy!</p>
                ) : (
                  roki.map((rok) => (
                    <div
                      key={rok.id}
                      onClick={() => { setSelectedRok(rok); loadCele(rok.id); }}
                      className={`p-3 rounded-lg cursor-pointer transition-colors ${
                        selectedRok?.id === rok.id ? "bg-purple-100 border border-purple-300" : "bg-gray-50 hover:bg-gray-100"
                      }`}
                    >
                      <div className="font-medium text-gray-900">{rok.nazwa}</div>
                      <div className="text-xs text-gray-500">
                        {new Date(rok.dataStart).toLocaleDateString("pl-PL")} - {new Date(rok.dataKoniec).toLocaleDateString("pl-PL")}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            {/* Cele dla wybranego roku */}
            <div className="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">
                Cele {selectedRok ? `dla: ${selectedRok.nazwa}` : "(wybierz rok)"}
              </h2>

              {selectedRok ? (
                <div className="space-y-4">
                  {strategicKategorie.length === 0 ? (
                    <p className="text-gray-500">Brak kategorii strategicznych. Oznacz kategorie jako strategiczne w zakładce &quot;Kategorie&quot;.</p>
                  ) : (
                    strategicKategorie.map((kat) => {
                      const cel = cele.find(c => c.kategoria === kat.klucz);
                      const isEditing = editingCel?.kategoria === kat.klucz;

                      return (
                        <div
                          key={kat.klucz}
                          className="p-4 rounded-lg border-l-4 bg-gray-50"
                          style={{ borderLeftColor: kat.color }}
                        >
                          <div className="flex items-center justify-between mb-2">
                            <h3 className="font-medium text-gray-900">{kat.nazwa}</h3>
                            {!isEditing && (
                              <button
                                onClick={() => setEditingCel({
                                  kategoria: kat.klucz,
                                  cel: cel?.cel || "",
                                  planowaneGodzinyDziennie: cel?.planowaneGodzinyDziennie || "1.00",
                                })}
                                className="text-purple-600 hover:text-purple-800 text-sm"
                              >
                                Edytuj cel
                              </button>
                            )}
                          </div>

                          {isEditing ? (
                            <div className="space-y-3">
                              <textarea
                                value={editingCel.cel}
                                onChange={(e) => setEditingCel({ ...editingCel, cel: e.target.value })}
                                placeholder="Opisz cel na ten rok..."
                                className="w-full px-3 py-2 border rounded-lg text-gray-900"
                                rows={3}
                              />
                              <div className="flex items-center gap-4">
                                <div className="flex items-center gap-2">
                                  <label className="text-sm text-gray-600">Godzin dziennie:</label>
                                  <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={editingCel.planowaneGodzinyDziennie}
                                    onChange={(e) => setEditingCel({ ...editingCel, planowaneGodzinyDziennie: e.target.value })}
                                    className="w-20 px-2 py-1 border rounded text-gray-900"
                                  />
                                </div>
                                <button
                                  onClick={() => handleSaveCel(editingCel.kategoria, editingCel.cel, editingCel.planowaneGodzinyDziennie)}
                                  className="px-4 py-1 bg-green-600 text-white rounded-lg text-sm"
                                >
                                  Zapisz
                                </button>
                                <button
                                  onClick={() => setEditingCel(null)}
                                  className="px-4 py-1 bg-gray-300 text-gray-700 rounded-lg text-sm"
                                >
                                  Anuluj
                                </button>
                              </div>
                            </div>
                          ) : (
                            <div>
                              {cel?.cel ? (
                                <p className="text-gray-700">{cel.cel}</p>
                              ) : (
                                <p className="text-gray-400 italic">Brak celu</p>
                              )}
                              {cel?.planowaneGodzinyDziennie && (
                                <p className="text-xs text-gray-500 mt-1">
                                  Plan: {cel.planowaneGodzinyDziennie}h dziennie
                                </p>
                              )}
                            </div>
                          )}
                        </div>
                      );
                    })
                  )}

                  <div className="pt-4 border-t">
                    <button
                      onClick={() => handleDeleteRok(selectedRok.id)}
                      className="text-red-600 hover:text-red-800 text-sm"
                    >
                      Usuń ten rok
                    </button>
                  </div>
                </div>
              ) : (
                <p className="text-gray-500">Wybierz rok z listy po lewej stronie.</p>
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
