import { defineConfig } from "@playwright/test";
import base from "./playwright.config";

// Human review run (modelled on salon-os demo config): visible browser, slowed actions, one worker.
export default defineConfig({
  ...base,
  timeout: 120_000,
  use: {
    ...base.use,
    headless: false,
    viewport: { width: 1440, height: 900 },
    launchOptions: { slowMo: Number(process.env.SLOWMO ?? 400) },
  },
});
