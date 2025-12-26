"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function SetupPage() {
  const router = useRouter();
  const [status, setStatus] = useState<"idle" | "loading" | "success" | "error">("idle");
  const [message, setMessage] = useState("");

  const initDb = async (force: boolean = false) => {
    setStatus("loading");
    try {
      const url = force ? "/api/init-db?force=true" : "/api/init-db";
      const res = await fetch(url, { method: "POST" });
      const data = await res.json();

      if (res.ok) {
        setStatus("success");
        setMessage(data.message || "Baza danych zainicjalizowana pomyślnie!");
      } else {
        setStatus("error");
        setMessage(data.details || data.error || "Wystąpił błąd");
      }
    } catch {
      setStatus("error");
      setMessage("Nie udało się połączyć z bazą danych");
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 flex items-center justify-center p-4">
      <div className="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Zadaniomat OKR</h1>
        <p className="text-gray-600 mb-6">Konfiguracja bazy danych</p>

        {status === "idle" && (
          <>
            <p className="text-gray-500 mb-6">
              Wybierz opcję inicjalizacji bazy danych:
            </p>
            <div className="space-y-3">
              <button
                onClick={() => initDb(false)}
                className="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors"
              >
                Zainicjalizuj (zachowaj dane)
              </button>
              <button
                onClick={() => initDb(true)}
                className="w-full bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
              >
                Resetuj bazę (usuń wszystko)
              </button>
            </div>
            <p className="text-xs text-amber-600 mt-4">
              Opcja &quot;Resetuj bazę&quot; usunie wszystkie dane i użytkowników!
            </p>
          </>
        )}

        {status === "loading" && (
          <div className="py-8">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto"></div>
            <p className="text-gray-500 mt-4">Tworzenie tabel...</p>
          </div>
        )}

        {status === "success" && (
          <div className="py-4">
            <div className="text-green-500 text-5xl mb-4">✓</div>
            <p className="text-green-600 font-medium mb-6">{message}</p>
            <button
              onClick={() => router.push("/register")}
              className="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors"
            >
              Utwórz konto administratora
            </button>
          </div>
        )}

        {status === "error" && (
          <div className="py-4">
            <div className="text-red-500 text-5xl mb-4">✗</div>
            <p className="text-red-600 mb-6">{message}</p>
            <div className="space-y-3">
              <button
                onClick={() => initDb(false)}
                className="w-full bg-gray-600 text-white py-3 rounded-lg font-medium hover:bg-gray-700 transition-colors"
              >
                Spróbuj ponownie
              </button>
              <button
                onClick={() => initDb(true)}
                className="w-full bg-red-600 text-white py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
              >
                Resetuj i zainicjalizuj od nowa
              </button>
            </div>
          </div>
        )}

        <p className="text-xs text-gray-400 mt-6">
          Upewnij się, że zmienne środowiskowe POSTGRES_URL są poprawnie skonfigurowane w Vercel.
        </p>
      </div>
    </div>
  );
}
