/*
 * UI path -> test
 * | Second analyst requests active indicator -> shared note, no extra unit | second analyst gets the shared lookup without spending quota |
 */
import { expect, test } from "@playwright/test";
import { arrange, card, expectQuota, open, request } from "../support/ui";

test("second analyst gets the shared lookup without spending quota", async ({ page }) => {
  const { Alice, Bob } = await arrange();
  await open(page, Alice);
  await request(page, "shared.example.com");
  await expect(card(page, "shared.example.com")).toContainText("Slot reserved");
  await expect(card(page, "shared.example.com")).not.toContainText("shared with another request");
  await expectQuota(page, { Reserved: 1, Remaining: 1 });

  await page.getByLabel("Analyst").selectOption(Bob.id);
  await request(page, "shared.example.com");
  const c = card(page, "shared.example.com");
  await expect(c).toContainText("Already in progress — shared with another request.");
  await expect(page.getByRole("listitem")).toHaveCount(1);
  await expectQuota(page, { Reserved: 1, Consumed: 0, Remaining: 1, Queued: 0 });
});
