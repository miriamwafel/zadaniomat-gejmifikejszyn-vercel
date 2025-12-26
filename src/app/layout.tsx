import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Zadaniomat OKR",
  description: "System zarządzania celami i zadaniami z gamifikacją",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pl">
      <body className="antialiased font-sans">
        {children}
      </body>
    </html>
  );
}
