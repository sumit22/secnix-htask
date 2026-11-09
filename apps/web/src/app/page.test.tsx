import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import Home from "./page";

it("renders the title", () => {
  render(<Home />);
  expect(
    screen.getByRole("heading", { name: "Enrichment Lookup Coordinator" }),
  ).toBeInTheDocument();
});
