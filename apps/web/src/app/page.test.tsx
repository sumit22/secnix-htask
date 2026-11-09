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
          ? [
              {
                id: "01a1c02f-0000-7000-8000-0000000000a1",
                name: "Alice",
                team: {
                  id: "01a1c02f-0000-7000-8000-0000000000f1",
                  slug: "default",
                },
              },
            ]
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
