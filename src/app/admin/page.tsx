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

export default function AdminPage() {
  const { data: session } = useSession();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [newPassword, setNewPassword] = useState("");

  const isSuperAdmin = session?.user?.role === "super_admin";

  useEffect(() => {
    loadUsers();
  }, []);

  const loadUsers = async () => {
    try {
      const res = await fetch("/api/users");
      if (res.ok) {
        const data = await res.json();
        setUsers(data);
      }
    } catch (error) {
      console.error("Error loading users:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateUser = async (userId: number, updates: Partial<User> & { password?: string }) => {
    try {
      const res = await fetch("/api/users", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: userId, ...updates }),
      });

      if (res.ok) {
        loadUsers();
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
      const res = await fetch(`/api/users?id=${userId}`, {
        method: "DELETE",
      });

      if (res.ok) {
        loadUsers();
      } else {
        const data = await res.json();
        alert(data.error || "Błąd usuwania");
      }
    } catch (error) {
      console.error("Error deleting user:", error);
    }
  };

  const getRoleBadgeColor = (role: string) => {
    switch (role) {
      case "super_admin":
        return "bg-red-100 text-red-800";
      case "admin":
        return "bg-orange-100 text-orange-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

  const getRoleLabel = (role: string) => {
    switch (role) {
      case "super_admin":
        return "Super Admin";
      case "admin":
        return "Admin";
      default:
        return "Użytkownik";
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-100 flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100">
      <header className="bg-white shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Panel Admina</h1>
            <p className="text-sm text-gray-500">Zarządzanie użytkownikami</p>
          </div>
          <Link
            href="/"
            className="text-purple-600 hover:text-purple-800 font-medium"
          >
            ← Powrót do aplikacji
          </Link>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-4 py-6">
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">
              Użytkownicy ({users.length})
            </h2>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Użytkownik
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Rola
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Data rejestracji
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Akcje
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {users.map((user) => (
                  <tr key={user.id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div>
                        <div className="text-sm font-medium text-gray-900">
                          {user.name || "—"}
                        </div>
                        <div className="text-sm text-gray-500">{user.email}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {editingUser?.id === user.id ? (
                        <select
                          value={editingUser.role}
                          onChange={(e) =>
                            setEditingUser({ ...editingUser, role: e.target.value })
                          }
                          className="text-sm border rounded px-2 py-1"
                          disabled={!isSuperAdmin}
                        >
                          <option value="user">Użytkownik</option>
                          <option value="admin">Admin</option>
                          {isSuperAdmin && (
                            <option value="super_admin">Super Admin</option>
                          )}
                        </select>
                      ) : (
                        <span
                          className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(
                            user.role
                          )}`}
                        >
                          {getRoleLabel(user.role)}
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      {editingUser?.id === user.id ? (
                        <select
                          value={editingUser.active ? "active" : "inactive"}
                          onChange={(e) =>
                            setEditingUser({
                              ...editingUser,
                              active: e.target.value === "active",
                            })
                          }
                          className="text-sm border rounded px-2 py-1"
                        >
                          <option value="active">Aktywny</option>
                          <option value="inactive">Nieaktywny</option>
                        </select>
                      ) : (
                        <span
                          className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                            user.active
                              ? "bg-green-100 text-green-800"
                              : "bg-gray-100 text-gray-800"
                          }`}
                        >
                          {user.active ? "Aktywny" : "Nieaktywny"}
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {new Date(user.createdAt).toLocaleDateString("pl-PL")}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                      {editingUser?.id === user.id ? (
                        <div className="flex items-center justify-end gap-2">
                          <input
                            type="password"
                            placeholder="Nowe hasło (opcjonalne)"
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                            className="text-sm border rounded px-2 py-1 w-36"
                          />
                          <button
                            onClick={() =>
                              handleUpdateUser(user.id, {
                                role: editingUser.role,
                                active: editingUser.active,
                                ...(newPassword && { password: newPassword }),
                              })
                            }
                            className="text-green-600 hover:text-green-800 font-medium"
                          >
                            Zapisz
                          </button>
                          <button
                            onClick={() => {
                              setEditingUser(null);
                              setNewPassword("");
                            }}
                            className="text-gray-600 hover:text-gray-800"
                          >
                            Anuluj
                          </button>
                        </div>
                      ) : (
                        <div className="flex items-center justify-end gap-3">
                          <button
                            onClick={() => setEditingUser(user)}
                            className="text-purple-600 hover:text-purple-800 font-medium"
                          >
                            Edytuj
                          </button>
                          {isSuperAdmin && user.id !== parseInt(session?.user?.id || "0") && (
                            <button
                              onClick={() => handleDeleteUser(user.id)}
                              className="text-red-600 hover:text-red-800 font-medium"
                            >
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

        {/* Legenda ról */}
        <div className="mt-6 bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-gray-900 mb-3">Role użytkowników</h3>
          <div className="space-y-2 text-sm text-gray-600">
            <p>
              <span className="font-medium text-red-700">Super Admin</span> — pełny dostęp, może zarządzać wszystkimi użytkownikami, nadawać role admin/super_admin, usuwać użytkowników
            </p>
            <p>
              <span className="font-medium text-orange-700">Admin</span> — może edytować użytkowników (bez zmiany ról admin), przeglądać listę użytkowników
            </p>
            <p>
              <span className="font-medium text-gray-700">Użytkownik</span> — standardowy dostęp do aplikacji
            </p>
          </div>
        </div>
      </main>
    </div>
  );
}
