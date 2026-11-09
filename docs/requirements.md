# Enrichment Lookup Coordinator with Provider Quota

> Source of truth. Extracted verbatim from `SSE_ENGINEERING_ASSESSMENT_3.pdf` (Securonix SSE Engineering Assessment — confidential, do not share outside the hiring process).

**Tags:** Full-stack | Concurrency | Leases and expiry | Queue | Idempotency

Our products are built around cyber threat intelligence, so this problem is set in that domain. You do not need prior cybersecurity experience. A short primer is included below and you are welcome to use your AI assistant to learn the concepts. We will ask about the domain decisions you made in the interview.

## Background

When analysts investigate an indicator (an IP, domain or file hash), they 'enrich' it by querying third-party threat intelligence providers for its reputation, related malware families and so on. These integrations are usually rate-limited or quota-limited. Many analysts and automated playbooks ask for lookups at the same time, and a retried request must not burn extra quota.

## The task

Scenario: your provider plan has only **10 lookups remaining for today**. Build a full-stack coordinator that shares this quota safely.

### Frontend

- A dashboard showing remaining quota
- An input to request enrichment for an indicator
- After a lookup slot is reserved: a countdown timer for the lease and a 'Run lookup' button
- Status messages for: queued (with position), lease expired, completed (showing the reputation result)

### Backend

- Request enrichment: reserves one unit of quota for the indicator for 5 minutes (a lease)
- Run lookup: calls the provider and permanently consumes the reserved unit. Mock the provider with a function or endpoint that returns a random reputation verdict; no real API key is needed.
- When a lease expires without the lookup being run, the unit is returned and offered to the next queued request
- A status endpoint for a lookup request

### Database

- Tables for quota, lookup leases/requests, and the waiting queue

### Business rules

- The system must never allow more lookups than the quota, even under about 200 simultaneous requests
- An indicator can have at most one active lookup (leased or queued) at a time. Requests for the same indicator should not consume additional quota.
- If a client retries a request with the same idempotency key, it must not consume a second unit
- When no quota is available, requests are queued and promoted in the order they arrived

### Scope note

- Time is limited. A working core (request, run, expiry, never exceed quota) matters more than a complete feature set. Decide what to prioritise and document what you chose to leave out.

## Expectations

- Any language, framework or stack you are comfortable with. Redis or a queue is optional.
- No authentication needed (identify the requesting analyst by an id in the request), and the UI can be plain.
- You are expected to use an AI coding assistant (Claude Code, Codex, Cursor, etc.). We are interested in how you use it.
- This problem is scoped to take about 20-30 minutes of focused build time with an AI assistant. Please do not spend more than [2 hours] in total, including the README and deployment steps. A working, well-understood core is better than extra features.
- Please submit within [X days] of receiving this assignment.

## What to submit

1. GitHub repository (public, or private with access granted to [GitHub handle/email]) containing:
   - A clear README covering: what the project does, tech stack, architecture and design decisions (including how you protect the quota under concurrency and handle lease expiry), how to run it locally, how to run tests (if any), API endpoints, known limitations, what you would improve with more time, and any security or domain assumptions you made
   - Deployment instructions: step-by-step instructions to deploy the app (for example Docker/docker-compose, or a hosting platform of your choice), including required environment variables and how to seed or migrate the database
   - A sensible commit history (please avoid a single giant commit)
2. Prompts used: the full list of prompts you gave to the AI tool (export, screenshots, or a markdown file in the repo is fine)
3. Build time: your start and end time for the build, and total time taken from first prompt to a working app
4. Tokens consumed: total input/output tokens used (for example the /cost command in Claude Code, the usage dashboard in Cursor, or the usage view in Codex/your account)
5. A short note (a few lines) on where the AI got something wrong or needed correction, and what you changed

## Before you start

- Note your start time.
- Make sure your AI tool's usage or token tracking is available so you can report it afterwards.
- Create a new, empty GitHub repository.

## Follow-up interview

Shortlisted candidates will be invited to a [30-45 min] interview. We will walk through your repository together, discuss your design decisions, trade-offs and domain understanding, and may ask you to explain or modify parts of the code. You should be able to explain any part of your submission, including code generated by AI.
