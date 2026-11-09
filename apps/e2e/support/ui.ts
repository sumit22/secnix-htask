import { expect, type Locator, type Page } from "@playwright/test";
import { analysts, type Analyst } from "./api";
import { resetDb } from "./reset";

export type Names = "Alice" | "Bob" | "Carol";

/** Reset DB and return the seeded analysts by first name. */
export async function arrange(): Promise<Record<Names, Analyst>> {
  resetDb();
  return (await analysts()) as Record<Names, Analyst>;
}

export async function open(page: Page, analyst?: Analyst): Promise<void> {
  await page.goto("/");
  await expect(page.getByRole("option", { name: /Alice/ })).toBeAttached();
  if (analyst) await page.getByLabel("Analyst").selectOption(analyst.id);
}

export function quotaValue(page: Page, label: string): Locator {
  return page
    .locator("dl.quota > div")
    .filter({ has: page.getByText(label, { exact: true }) })
    .locator("dd");
}

export async function expectQuota(
  page: Page,
  v: Partial<Record<"Limit" | "Reserved" | "Consumed" | "Remaining" | "Queued", number>>,
): Promise<void> {
  for (const [k, n] of Object.entries(v)) {
    await expect(quotaValue(page, k)).toHaveText(String(n), { timeout: 10_000 });
  }
}

export async function request(page: Page, indicator: string): Promise<void> {
  await page.getByLabel("Indicator").fill(indicator);
  await page.getByRole("button", { name: "Request lookup" }).click();
}

export function card(page: Page, indicator: string): Locator {
  return page.getByRole("listitem").filter({ hasText: indicator });
}

/** Page-level error paragraph (excludes Next's route announcer, which also has role=alert). */
export function alert(page: Page): Locator {
  return page.locator("p[role=alert]");
}
