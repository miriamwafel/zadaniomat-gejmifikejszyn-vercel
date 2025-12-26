// Utility functions

export function formatDate(date: Date | string): string {
  const d = new Date(date);
  return d.toLocaleDateString("pl-PL", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function formatTime(time: string | null): string {
  if (!time) return "";
  return time.substring(0, 5);
}

export function formatMinutes(minutes: number): string {
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  if (hours > 0) {
    return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`;
  }
  return `${mins}min`;
}

export function getStatusColor(status: string): string {
  switch (status) {
    case "zakonczone":
      return "bg-green-500";
    case "w_trakcie":
    case "rozpoczete":
      return "bg-yellow-500";
    case "nowe":
    default:
      return "bg-gray-400";
  }
}

export function getStatusLabel(status: string): string {
  switch (status) {
    case "zakonczone":
      return "Zakończone";
    case "w_trakcie":
    case "rozpoczete":
      return "W trakcie";
    case "nowe":
    default:
      return "Nowe";
  }
}

export function cn(...classes: (string | boolean | undefined)[]): string {
  return classes.filter(Boolean).join(" ");
}
