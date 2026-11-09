import { execSync } from "node:child_process";
import path from "node:path";

const ROOT = path.resolve(__dirname, "..", "..", "..");

/** Reset and seed the database (also resets the day's quota allocation). */
export function resetDb(): void {
  execSync("docker compose exec -T api php artisan migrate:fresh --seed --force", {
    cwd: ROOT,
    stdio: "pipe",
  });
}
