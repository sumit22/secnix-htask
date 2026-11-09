import { resetDb } from "./reset";

export default function globalSetup(): void {
  resetDb();
}
