---
name: plan-template
description: >-
  Tech-agnostic and project-agnostic workflow for spec-driven development using the 3-file system (AGENTS.md, request.md, plan.md). Use when initializing project tracking, planning features, syncing plain-English user requests with structured agent tasks, executing surgical vertical slices, and maintaining rigorous build and verification status across any language, framework, or architecture.
---

# Plan-Template: Tech-Agnostic & Project-Agnostic Agent Workflow

A universal, stack-independent operating system for pairing humans and AI coding agents. It enforces a strict **3-file contract** that eliminates drift, prevents hallucinated scope, maintains two-way synchronization between user desires and agent execution, and guarantees surgical delivery.

```
       User Vocabulary                   Single Source of Truth                 Frozen Rules
   ┌──────────────────────┐              ┌────────────────────┐            ┌──────────────────┐
   │      request.md      │ ◄──────────► │      plan.md       │ ◄───────── │    AGENTS.md     │
   │ (Plain-English list, │   Two-Way    │ (Permanent IDs,    │  Governs   │  (Process rules, │
   │  R<n> IDs, checkboxes│    Sync      │  file ownership,   │  Execution │   surgical edits,│
   │  ticked by human)    │              │  status & log)     │            │   DoD checklist) │
   └──────────────────────┘              └────────────────────┘            └──────────────────┘
```

---

## 1. Core Operating Principles

1. **Tech-Agnostic & Project-Agnostic**:
   - Zero assumptions about languages (Rust, TypeScript, Python, Go, C++, Swift, Dart, Java, etc.).
   - Zero assumptions about platforms (Web, Backend, Mobile, Desktop, CLI, Embedded, Microservices, Monorepos).
   - Zero vendor lock-in. Works identically in Antigravity, Claude Code, Codex, Cursor, or terminal LLMs.

2. **Read Order — Always Do This First**:
   Before modifying or writing any code, the agent must inspect files in this exact sequence:
   1. `AGENTS.md` — Process rules and working agreement.
   2. `request.md` — User feature list in plain English (`R<n>` IDs).
   3. `plan.md` — Status tracker, repo map, milestone dashboard, and task ownership.
   4. `docs/` and project specifications / ADRs (`docs/decisions/`).
   *Never assume structure from memory — discover, then act.*

3. **The Tracker is Law (`plan.md`)**:
   - **Track first, build second**: Work that is not in the tracker must be added before writing a single line of code. No ID = No code.
   - Every task has:
     - Permanent unique ID (`M<milestone>.<n>`)
     - Status icon (`⬜` Not started · `🔄` In progress · `✅` Done · `⏸️` Blocked · `🚫` Dropped)
     - Owning file list (`> **Files:** <path1> · <path2>`)
     - Scope comment & dated notes (`— YYYY-MM-DD: <note>`)
     - Direct link to user request (`> **Request:** R<n>`)
   - **Update in place**: Flip status, append dated notes. Never rewrite or delete historical context.

4. **Surgical Change Discipline**:
   - Touch **only** the files explicitly listed under `> **Files:**` in the task entry.
   - If a new file is created, add it to the task entry upon completion.
   - **No drive-by work**: No refactoring, reformatting, dependency bumps, or unsolicited cleanup outside the task scope.
   - Structure for locality: Status transitions in one module, tokens in one design file, validation in one shared validator.

5. **Tracer-Bullet Vertical Slicing**:
   - Slice work vertically across all layers (persistence/data → core logic → interface/API/UI/CLI → automated tests).
   - Every slice must be independently runnable, verifiable, and demonstrable.
   - Prefactor first when helpful: "Make the change easy, then make the easy change" (tracked as its own task).

6. **Third-Party Asset Isolation**:
   - Design mockups, external art, and third-party references stay local and uncommitted.
   - Add to `.gitignore`; never commit with `git add -f`.
   - Record provenance, author, link, and license in `ATTRIBUTION.md`.

---

## 2. Directory Layout & Structure

When adopting this skill in any project, ensure the project root provides:

```text
./
├── AGENTS.md                  # Working agreement for coding agents (frozen)
├── request.md                 # Plain-English user feature list
├── plan.md                    # Living status tracker and repo map
├── ATTRIBUTION.md             # Register for third-party assets and licenses
├── .gitignore                 # Excludes local design assets & build artifacts
├── docs/                      # Project briefs and architecture documents
│   ├── brief.md               # What is being built and for whom
│   └── decisions/             # Architecture Decision Records (ADRs: Q01-stack.md, etc.)
└── <project-source-tree>/     # Actual source code (adapted to the repo's stack)
```

The skill template files are available in the [`resources/`](./resources/) directory:
- [`resources/AGENTS.md`](./resources/AGENTS.md)
- [`resources/plan.md`](./resources/plan.md)
- [`resources/request.md`](./resources/request.md)
- [`resources/ATTRIBUTION.md`](./resources/ATTRIBUTION.md)

---

## 3. Step-by-Step Agent Workflow

### Phase 1: Project Discovery & Bootstrap
When starting on a new or existing repository:
1. Check if `AGENTS.md`, `request.md`, and `plan.md` exist in the project root.
2. If missing, copy the templates from [`resources/`](./resources/) into the project root.
3. Discover the project stack and folder organization:
   - Identify build systems, package manifests (`package.json`, `Cargo.toml`, `pyproject.toml`, `go.mod`, etc.).
   - Fill Section 2 (`## 2 · Repo map`) in `plan.md` to reflect the actual source and test locations.
   - Fill Section 1 (`## 1 · Source documents`) with the project's documentation files.

### Phase 2: Requirement Intake & Two-Way Sync
1. **User input → Tracker**:
   - The user writes plain-English requirements into `request.md` using `R<n>` IDs:
     ```markdown
     ## Authentication
     - [ ] R1 · User can sign in with email and password
     - [ ] R2 · User can request a password reset link
     ```
   - Agent inspects `request.md`. For any new `R<n>` line not in `plan.md`:
     - Create a task in `plan.md` §5 under the appropriate milestone (`M0`, `M1`, etc.).
     - Assign an ID (`M1.1`), map candidate files, and link back with `> **Request:** R1`.
     - Deduplicate strictly by request ID (`R<n>`), never by wording.
2. **Tracker addition → User list**:
   - If the agent or architecture breakdown requires a new user-facing task, add a matching plain-English line to `request.md` with a new `R<n>` ID.
3. **Execution status sync**:
   - `plan.md` `✅` is the agent's technical record of completion.
   - `request.md` `[x]` is the user's manual confirmation after verifying the feature. The agent maintains both in sync.

### Phase 3: Vertical Slicing & Task Breakdown
When drafting tasks in `plan.md` §5:
1. Break milestones into thin vertical tracer bullets:
   - Bad (Horizontal): "Build database tables", "Build REST controllers", "Build UI".
   - Good (Vertical): "M1.1: Submit contact form end-to-end (validation → database record → notification email → UI feedback)".
2. Set explicit dependency edges:
   - State `Depends: M0.1` or `Blocked by: Q02` in the task comment.
   - If blocked, set task status icon to `⏸️`.

### Phase 4: Surgical Task Execution
When picking up a task:
1. Flip task status in `plan.md` from `⬜` to `🔄`.
2. Append a dated start note: `— YYYY-MM-DD: started implementation`.
3. Check the `> **Files:**` line. Touch **only** those files.
4. If implementation reveals another file must be touched:
   - Stop and consider if the scope outgrew the task.
   - If it belongs to this task, add the path to `> **Files:**` in `plan.md` before editing.
   - If it is an unrelated issue or refactor, **never fix silently**. Create a separate tracker task in `plan.md`.

### Phase 5: Verification & Definition of Done
Before flipping any task to `✅`, verify all 5 criteria:
1. **Scope Fidelity**: Implements exactly what the task comment specifies — no drive-by additions.
2. **Quality & Test Verification**:
   - Tests, linters, and typecheckers for the touched files pass with zero errors.
   - If no automated tests cover the touched area, add the smallest meaningful unit or integration test.
3. **State Walk (UI, API, or CLI)**:
   - **For UI**: Walk default, hover, focus, active, loading, empty, error, disabled, and dark/light modes.
   - **For APIs**: Walk 200/201 success, 400 validation error, 401/403 authorization error, 404 not found, and rate limiting.
   - **For CLIs/Libraries**: Walk standard invocation, invalid arguments, exit codes, stdout/stderr separation, and pipe handling.
4. **Tracker Closed**:
   - Flip status to `✅`.
   - Append dated completion note: `— YYYY-MM-DD: completed and verified`.
   - Ensure all created files are recorded in `> **Files:**`.
   - Append a row to `plan.md` §7 (`## 7 · Change log`):
     `| YYYY-MM-DD | M1.1 · Feature Name | path/to/file1, path/to/file2 | Verified end-to-end |`
5. **Decisions Documented**:
   - If technical decisions or trade-offs were made, record them as an ADR in `docs/decisions/Q<n>-<topic>.md` and resolve the question in `plan.md` §6.

---

## 4. Universal Standards (Tech-Agnostic)

### Content Rules
- No placeholder copy ("lorem ipsum", "John Doe", "Acme Corp"). Write realistic, domain-specific text.
- No AI marketing jargon ("elevate", "seamless", "next-gen", "game-changer", "unleash").
- No emojis in UI code or production markup — use an outline icon library.
- Never fabricate data in product surfaces. Test fixtures must be isolated in test directories.

### Security & Privacy Defaults
- **Least Data Principle**: Collect only what is strictly required; state purpose at collection.
- **Secrets Management**: Secrets, API keys, and credentials belong in environment variables (`.env`), never committed to source control or logged.
- **User Rights**: Provide mechanisms for data export and complete data deletion.
- **Defensive Hardening Baseline**:
  - Validate and sanitize all user inputs.
  - Rate-limit sensitive endpoints (auth, search, mutations).
  - Use cryptographically strong password hashing (argon2 / bcrypt).
  - Enforce least privilege in access control.
  - Payment credentials must never be handled or logged directly; link out or display identifiers only.

### Communication Agreement
- **Consolidated Clarifications**: When requirements or decisions are ambiguous, ask **one consolidated round** of questions using the tracker (`plan.md` §6). Do not drip-feed questions.
- **Punchy Summaries**: When reporting progress, state:
  1. What changed.
  2. Which task IDs (`M<milestone>.<n>`).
  3. Which files were touched.
- **Code Review**: When reviewing code, cite `path/to/file:line`, sort by severity (Blocker, Major, Minor), and provide an unambiguous verdict.
