/*
 * UI path -> test
 * | Lease expires unrun -> "Lease expired", Run gone | unrun lease expires, Run disappears, unit returned |
 * | Quota unit returned after expiry                 | same |
 * | Run after expiry -> 409 message                  | run racing past expiry shows the lease-expired error |
 */
import { expect, test } from "@playwright/test";
import { arrange, card, expectQuota, open, request } from "../support/ui";

test("unrun lease expires, Run disappears, unit returned", async ({ page }) => {
  test.setTimeout(40_000);
  const { Alice } = await arrange();
  await open(page, Alice);
  await request(page, "expire.example.com");
  const c = card(page, "expire.example.com");
  await expectQuota(page, { Reserved: 1, Remaining: 1 });

  await expect(c.getByText("Lease expired", { exact: false })).toBeVisible({ timeout: 20_000 });
  await expect(c.getByRole("button", { name: "Run lookup" })).toHaveCount(0);
  await expect(c.getByText("expired", { exact: true })).toBeVisible({ timeout: 10_000 });
  await expect(c).toContainText("Lease expired. Request the lookup again.");
  await expectQuota(page, { Reserved: 0, Consumed: 0, Remaining: 2 });
});

test("run racing past expiry shows the lease-expired error", async ({ page }) => {
  test.setTimeout(40_000);
  const { Alice } = await arrange();
  await open(page, Alice);
  await request(page, "late.example.com");
  const c = card(page, "late.example.com");

  // Hold the run request until the server-side lease has really expired, then let it through.
  await page.route("**/api/v1/lookups/*/run", async (route) => {
    await new Promise((r) => setTimeout(r, 10_000));
    await route.continue();
  });
  await c.getByRole("button", { name: "Run lookup" }).click();

  await expect(c.getByRole("alert")).toHaveText("Lease expired. Request the lookup again.", { timeout: 20_000 });
  await expect(c.getByRole("button", { name: "Run lookup" })).toHaveCount(0);
  await expectQuota(page, { Reserved: 0, Consumed: 0, Remaining: 2 });
});
