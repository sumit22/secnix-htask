/*
 * UI path -> test
 * | Recent lookups survive reload (leased + completed) | recent lookups are restored after reload |
 * | Lists are per analyst                              | same |
 */
import { expect, test } from "@playwright/test";
import { arrange, card, open, request } from "../support/ui";

test("recent lookups are restored after reload", async ({ page }) => {
  const { Alice, Bob } = await arrange();
  await open(page, Alice);
  await request(page, "done.example.com");
  await card(page, "done.example.com").getByRole("button", { name: "Run lookup" }).click();
  await expect(card(page, "done.example.com")).toContainText("Verdict:");
  await request(page, "pending.example.com");
  await expect(card(page, "pending.example.com")).toContainText("Slot reserved");

  await page.reload();
  await expect(card(page, "done.example.com")).toContainText("Verdict:");
  await expect(card(page, "pending.example.com")).toContainText("Slot reserved");
  await expect(page.getByRole("listitem")).toHaveCount(2);

  await page.getByLabel("Analyst").selectOption(Bob.id);
  await expect(page.getByText("No lookups yet.")).toBeVisible();
});
