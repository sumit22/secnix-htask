import { describe, expect, it } from "vitest";
import { readEnv } from "./env";

describe("readEnv", () => {
  it("strips trailing slashes from the API URL", () => {
    expect(readEnv("http://localhost:8000//").apiUrl).toBe(
      "http://localhost:8000",
    );
  });

  it("throws when the API URL is missing", () => {
    expect(() => readEnv("")).toThrow("NEXT_PUBLIC_API_URL is required");
  });

  it("throws when the API URL is invalid", () => {
    expect(() => readEnv("not a url")).toThrow("not a valid URL");
  });
});
