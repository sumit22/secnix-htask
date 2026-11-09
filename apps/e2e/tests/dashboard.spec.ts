/*
 * UI path -> test
 * | Dashboard loads, quota numbers shown                | loads quota numbers and analyst picker |
 * | Quota placeholders before an analyst is chosen      | loads quota numbers and analyst picker (see note) |
 * | Analyst picker lists seeded analysts                | loads quota numbers and analyst picker |
 * | No analyst selected: form disabled, empty list      | loads quota numbers and analyst picker |
 * | Selection persists across reload                    | analyst selection persists across reload |
 *
 * Note: GET /quota requires X-Analyst-Id, so the quota panel shows "–" until an analyst is picked.
 */
import { expect, test } from "@playwright/test";
import { arrange, expectQuota, open, quotaValue } from "../support/ui";

test.beforeEach(async () => {
  await arrange();
});

test("loads quota numbers and analyst picker", async ({ page }) => {
  await open(page);
  await expect(page.getByRole("heading", { name: "Enrichment Lookup Coordinator" })).toBeVisible();

  const options = page.getByLabel("Analyst").getByRole("option");
  await expect(options).toHaveText(["Select an analyst…", "Alice (Tier 1)", "Bob (Tier 2)", "Carol (Threat Hunter)"]);
  await expect(page.getByRole("button", { name: "Request lookup" })).toBeDisabled();
  await expect(page.getByText("No lookups yet.")).toBeVisible();
  await expect(quotaValue(page, "Limit")).toHaveText("–");

  await page.getByLabel("Analyst").selectOption({ label: "Alice (Tier 1)" });
  await expect(page.getByRole("heading", { name: "Quota — mock-intel" })).toBeVisible({ timeout: 10_000 });
  await expectQuota(page, { Limit: 2, Reserved: 0, Consumed: 0, Remaining: 2, Queued: 0 });
});

test("analyst selection persists across reload", async ({ page }) => {
  await open(page);
  await page.getByLabel("Analyst").selectOption({ label: "Bob (Tier 2)" });
  await page.reload();
  await expect(page.getByLabel("Analyst").locator("option:checked")).toHaveText("Bob (Tier 2)");
  await expectQuota(page, { Limit: 2 });
  await page.getByLabel("Indicator").fill("example.com");
  await expect(page.getByRole("button", { name: "Request lookup" })).toBeEnabled();
});
