import { auth } from "@/lib/auth";
import { NextResponse } from "next/server";

export default auth((req) => {
  const { nextUrl } = req;
  const isLoggedIn = !!req.auth;
  const isAdmin = req.auth?.user?.role === "admin" || req.auth?.user?.role === "super_admin";

  // Ścieżki publiczne
  const publicPaths = ["/login", "/register", "/setup", "/api/auth", "/api/init-db"];
  const isPublicPath = publicPaths.some(path => nextUrl.pathname.startsWith(path));

  // Ścieżki adminowe
  const adminPaths = ["/admin"];
  const isAdminPath = adminPaths.some(path => nextUrl.pathname.startsWith(path));

  // Jeśli użytkownik nie jest zalogowany i próbuje dostać się do chronionej ścieżki
  if (!isLoggedIn && !isPublicPath) {
    return NextResponse.redirect(new URL("/login", nextUrl));
  }

  // Jeśli użytkownik jest zalogowany i próbuje dostać się do login/register
  if (isLoggedIn && (nextUrl.pathname === "/login" || nextUrl.pathname === "/register")) {
    return NextResponse.redirect(new URL("/", nextUrl));
  }

  // Jeśli użytkownik nie jest adminem i próbuje dostać się do panelu admina
  if (isAdminPath && !isAdmin) {
    return NextResponse.redirect(new URL("/", nextUrl));
  }

  return NextResponse.next();
});

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.svg$).*)"],
};
