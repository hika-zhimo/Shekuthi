# Plan-Template Skill

A universal, tech-agnostic and project-agnostic skill for AI coding agents to implement and enforce spec-driven development via the **3-file contract**:

- **`AGENTS.md`**: Frozen working agreement governing how agents operate (read order, surgical changes, tracer-bullet slicing, security/content baselines, definition of done).
- **`request.md`**: Plain-English user feature list with permanent `R<n>` IDs and checkboxes.
- **`plan.md`**: Technical status tracker, single source of truth, repo map, milestone dashboard, and change log.

## Directory Contents

- [`SKILL.md`](./SKILL.md) — Main skill instructions with YAML frontmatter for AI agents.
- [`skill.yaml`](./skill.yaml) — Standalone YAML specification of metadata, triggers, workflow phases, status vocabulary, and operating rules.
- [`resources/`](./resources/) — Ready-to-copy, clean, stack-agnostic templates:
  - [`resources/AGENTS.md`](./resources/AGENTS.md)
  - [`resources/plan.md`](./resources/plan.md)
  - [`resources/request.md`](./resources/request.md)
  - [`resources/ATTRIBUTION.md`](./resources/ATTRIBUTION.md)

## Installation & Adoption

### 1. In Antigravity
To make this skill available across your workspace or globally:
- **Workspace level:** Place in `.agents/skills/plan-template/` within your repository root.
- **Global level:** Place in `~/.gemini/config/skills/plan-template/`.

### 2. In any Repository
To initialize a repository with this plan-driven system:
1. Copy the files from `resources/` into your repository root:
   ```bash
   cp resources/AGENTS.md ./AGENTS.md
   cp resources/plan.md ./plan.md
   cp resources/request.md ./request.md
   cp resources/ATTRIBUTION.md ./ATTRIBUTION.md
   ```
2. Adjust `plan.md` §2 (`Repo map`) to reflect your project's directory structure.
3. Add features to `request.md` in plain English (`R1`, `R2`, ...).
4. Prompt your AI agent:
   > "Read AGENTS.md, request.md, and plan.md, sync the requirements, and implement the first milestone."
