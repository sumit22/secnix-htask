/*
 * UI path -> test
 * | Quota exhausted -> queued with position N        | queues when exhausted, FIFO, promotes without reload |
 * | FIFO order of two queued items                   | same |
 * | Queued item promoted to leased on expiry (no reload) | same |
 * | Quota "Queued" counter                           | same |
 */
import { expect, test } from "@playwright/test";
import { requestLookup } from "../support/api";
import { arrange, card, expectQuota, open, request } from "../support/ui";

test("queues when exhausted, FIFO, promotes without reload", async ({ page }) => {
  test.setTimeout(60_000);
  const { Alice, Carol } = await arrange();
  await open(page, Carol);
  // Fill the quota only once the page is ready; staggered so the units free up ~3s apart (TTL 8s).
  await requestLookup(Alice.id, "a1.example.com");
  await new Promise((r) => setTimeout(r, 3000));
  await requestLookup(Alice.id, "a2.example.com");
  await request(page, "first.example.com");
  await expect(card(page, "first.example.com")).toContainText("Queued — position 1");
  await request(page, "second.example.com");
  await expect(card(page, "second.example.com")).toContainText("Queued — position 2");
  await expect(card(page, "first.example.com").getByRole("button", { name: "Run lookup" })).toHaveCount(0);
  await expectQuota(page, { Reserved: 2, Remaining: 0, Queued: 2 });

  // Earliest queued is promoted first when the first lease expires; no page reload.
  await expect(card(page, "first.example.com")).toContainText("Slot reserved", { timeout: 20_000 });
  await expect(card(page, "first.example.com").getByRole("button", { name: "Run lookup" })).toBeVisible();
  await expect(card(page, "second.example.com")).toContainText(/Queued — position 1|Slot reserved/);

  await expect(card(page, "second.example.com")).toContainText("Slot reserved", { timeout: 20_000 });
});
