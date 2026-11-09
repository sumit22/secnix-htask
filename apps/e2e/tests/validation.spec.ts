/*
 * UI path -> test
 * | Invalid indicator -> friendly error, nothing listed | invalid indicator shows a friendly error |
 * | Error clears on a valid retry                       | same |
 * | Empty / whitespace input blocked                    | empty and whitespace input cannot be submitted |
 */
import { expect, test } from "@playwright/test";
import { alert, arrange, card, expectQuota, open, request } from "../support/ui";

test("invalid indicator shows a friendly error", async ({ page }) => {
  const { Alice } = await arrange();
  await open(page, Alice);
  await request(page, "not a valid indicator!");
  await expect(alert(page)).toHaveText("That input is not valid. Check the indicator and retry.");
  await expect(page.getByText("No lookups yet.")).toBeVisible();
  await expectQuota(page, { Reserved: 0, Remaining: 2 });

  await request(page, "8.8.8.8");
  await expect(card(page, "8.8.8.8")).toContainText("Slot reserved");
  await expect(alert(page)).toHaveCount(0);
});

test("empty and whitespace input cannot be submitted", async ({ page }) => {
  const { Alice } = await arrange();
  await open(page, Alice);
  const submit = page.getByRole("button", { name: "Request lookup" });
  await expect(submit).toBeDisabled();
  await page.getByLabel("Indicator").fill("   ");
  await expect(submit).toBeDisabled();
  await page.getByLabel("Indicator").press("Enter");
  await expect(page.getByText("No lookups yet.")).toBeVisible();
  await expect(alert(page)).toHaveCount(0);
});
