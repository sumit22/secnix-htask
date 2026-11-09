import { render, screen } from "@testing-library/react";
import { expect, it, vi } from "vitest";
import { sampleQuota } from "../lib/fixtures";
import Home from "./page";

it("renders the dashboard with quota and analyst picker", async () => {
  vi.stubEnv("NEXT_PUBLIC_API_URL", "http://api.test");
  vi.stubGlobal(
    "fetch",
    vi.fn((input: string) => {
      const url = input;
      const data = url.endsWith("/quota")
        ? sampleQuota()
        : url.endsWith("/analysts")
          ? [{ id: "a1", name: "Alice", team: { id: "t1", slug: "default" } }]
          : [];
      return Promise.resolve(new Response(JSON.stringify({ data })));
    }),
  );
  render(<Home />);
  expect(
    screen.getByRole("heading", { name: "Enrichment Lookup Coordinator" }),
  ).toBeInTheDocument();
  expect(await screen.findByText("Alice")).toBeInTheDocument();
  expect(await screen.findByText("Remaining")).toBeInTheDocument();
  expect(await screen.findByText("3")).toBeInTheDocument();
});
