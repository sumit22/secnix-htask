/*
 * UI path -> test
 * | Network failure after server processed -> error, retry reuses key, no 2nd unit | retry after lost response reuses the key and spends one unit |
 */
import { expect, test } from "@playwright/test";
import { alert, arrange, card, expectQuota, open, request } from "../support/ui";

test("retry after lost response reuses the key and spends one unit", async ({ page }) => {
  const { Alice } = await arrange();
  await open(page, Alice);

  const keys: (string | undefined)[] = [];
  let attempt = 0;
  await page.route("**/api/v1/lookups", async (route) => {
    if (route.request().method() !== "POST") return route.fallback();
    keys.push(route.request().headers()["idempotency-key"]);
    attempt += 1;
    if (attempt === 1) {
      await route.fetch(); // server processes it and reserves a unit...
      await route.abort("failed"); // ...but the client never sees the response
    } else {
      await route.continue();
    }
  });

  await request(page, "retry.example.com");
  await expect(alert(page)).toHaveText("Network error. Retrying the same submission is safe.");
  await expect(page.getByLabel("Indicator")).toHaveValue("retry.example.com");

  await page.getByRole("button", { name: "Request lookup" }).click();
  await expect(card(page, "retry.example.com")).toContainText("Slot reserved");
  expect(keys).toHaveLength(2);
  expect(keys[0]).toBeTruthy();
  expect(keys[1]).toBe(keys[0]);
  await expect(page.getByRole("listitem")).toHaveCount(1);
  await expectQuota(page, { Reserved: 1, Remaining: 1 });
});
