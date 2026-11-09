/*
 * UI path -> test
 * | Request -> leased card with countdown          | request leases a slot and the countdown decreases |
 * | Quota reserved/remaining update after request  | request leases a slot and the countdown decreases |
 * | Run -> completed (verdict/score/families)      | run completes the lookup and consumes the unit |
 * | Quota reserved->consumed after run, Run gone   | run completes the lookup and consumes the unit |
 */
import { expect, test } from "@playwright/test";
import { arrange, card, expectQuota, open, request } from "../support/ui";

const secondsOf = (t: string): number => {
  const m = /Lease expires in\s+(\d\d):(\d\d)/.exec(t);
  return m ? Number(m[1]) * 60 + Number(m[2]) : NaN;
};

test("request leases a slot and the countdown decreases", async ({ page }) => {
  const { Alice } = await arrange();
  await open(page, Alice);
  await request(page, "lease.example.com");

  const c = card(page, "lease.example.com");
  await expect(c.getByText("leased", { exact: true })).toBeVisible();
  await expect(c).toContainText("Slot reserved. Lease expires in");
  await expect(c.getByRole("button", { name: "Run lookup" })).toBeVisible();
  await expect(page.getByLabel("Indicator")).toHaveValue("");
  await expectQuota(page, { Reserved: 1, Remaining: 1, Consumed: 0 });

  const first = secondsOf((await c.textContent()) ?? "");
  expect(first).toBeLessThanOrEqual(8);
  await expect.poll(async () => secondsOf((await c.textContent()) ?? ""), { timeout: 6000 }).toBeLessThan(first);
});

test("run completes the lookup and consumes the unit", async ({ page }) => {
  const { Alice } = await arrange();
  await open(page, Alice);
  await request(page, "run.example.com");
  const c = card(page, "run.example.com");
  await c.getByRole("button", { name: "Run lookup" }).click();

  await expect(c.getByText("completed", { exact: true })).toBeVisible();
  await expect(c).toContainText(/Verdict: (malicious|suspicious|harmless|unknown)/);
  await expect(c).toContainText(/Score \d{1,3}\/100/);
  await expect(c).toContainText(/Malware families: .+/);
  await expect(c.getByRole("button", { name: "Run lookup" })).toHaveCount(0);
  await expectQuota(page, { Reserved: 0, Consumed: 1, Remaining: 1 });
});
