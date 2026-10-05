# AGENTS.md — Working Agreement for AI Coding Agents

> **Stack-agnostic · project-agnostic.** Copy this file into any repository. It defines *how* agents work here; *what* the project is and *where* things live are defined in the project's status tracker (`plan.md`) and its docs. No language, framework, or vendor is assumed anywhere in this file.

## 1. Read order — always do this first

1. `AGENTS.md` (this file) — process rules.
2. `plan.md` — the status tracker: what exists, what's in progress, which files own which task.
3. Product briefs, decisions (`docs/`), and design references (`ui deisgns/`, `UX/`, `assets/`) when present.

Never assume structure from memory — discover, then act. If the tracker is missing, propose creating it before writing code.

## 2. The tracker is law (`plan.md`)

- Every task and sub-task carries: a **permanent unique ID**, a **status** (⬜ not started · 🔄 in progress · ✅ done · ⏸️ blocked · 🚫 dropped), a **comment** (scope + decisions + dated notes), and the **file path(s) that own it**.
- **Track first, build second.** Work that isn't in the tracker gets added (ID + status + comment + files) before any code.
- **Update in place.** Flip status, tick checkboxes, append dated notes (`— <date>: <note>`). Never rewrite or delete history.
- **Close the loop.** Done = status ✅ + created files added to the entry + change-log row appended.
- **One task = one concern = its own file list.** If work outgrows the task, split it; don't widen the original.
- **Blocked tasks cite their blocker** (question ID or dependency) right in the status.
- **Reference material is read-only.** Design docs, UX playbooks, and asset libraries are never edited by build tasks — copy out, never modify in place.

## 3. Surgical change discipline

- Touch **only** the files listed in the task's entry. Created a new file? Add it to the entry when done.
- **No drive-by work.** No refactors, renames, formatting sweeps, dependency bumps, or "improvements" outside the task scope.
- Found something broken outside scope? Add a tracker task for it. Never fix silently.
- Prefer additive change; deprecate before removing; keep public contracts stable unless the task says otherwise.
- **Structure for locality.** Status transitions in one service, design tokens in one file, validation in one shared validator, permissions in one policy layer. The measure of good structure in this repo: *a change touched exactly the files its task listed — nothing else.*

## 3a. Third-party assets stay out of the repo (standing rule)

- **Never commit third-party art or reference material.** The `UX/`, `assets/` (icons, illustration art) and `ui deisgns/` folders are not our work. Keep them **on disk only, locally** — they are listed in `.gitignore` and must not be tracked, staged or pushed.
- **Attribute, don't distribute.** When a task uses one of these files, record the owner, source link and licence in `ATTRIBUTION.md` — never copy the file into the repo to "include" it.
- **Never `git add -f`** these paths, and do not remove their `.gitignore` entries. If a build needs one at runtime, keep a placeholder/README or fetch it from the source, not the file itself.
- Applies to any new third-party asset too: source it, credit it in `ATTRIBUTION.md`, gitignore it.

## 4. Slice work vertically

- Break features into **tracer-bullet slices** that cut through every layer (data → logic → interface → test) and are demoable on their own — not horizontal layer-by-layer chunks.
- Record dependencies (`blocked by: <task-id>`) in the tracker and work them in order.
- One slice = one focused change set. Never mix unrelated changes in a single pass.
- Before slicing: check for prefactoring that makes the slices easier ("make the change easy, then make the easy change") and record it as its own task.

## 5. Design & UI rules (whenever the change has a user interface)

- **Adopt the project's chosen design system before inventing styles.** Use its tokens — palette, type scale, radius, spacing, elevation, motion — via the token file only. Never hardcode literal values in components.
- **Color is semantic.** Accent/primary colors mark interaction and status, never decoration. No decorative gradients. Support light/dark where the system defines them.
- **Icons.** Outline style is the default; filled variant only for active/selected states. One color via `currentColor`/theme tint; stroke weight matches adjacent text; flip directionally for RTL. Icons come from the project's icon library — never ad-hoc sources.
- **Illustrations.** Use the project's illustration library for empty states, errors, onboarding, and heroes; match the active theme variant.
- **Motion.** Animate only `transform`/`opacity`. Interactive transitions must be interruptible; exits softer than enters; stagger only infrequent entrances (~80–100 ms); small press feedback (scale ≈ 0.96–0.98); loading uses shimmer skeletons, not spinners. Never stagger high-frequency interactions.
- **Accessibility floor.** ≥44–48 px/dp touch targets; visible focus states; WCAG AA contrast; labels above inputs; alt text / content descriptions on meaningful imagery; never encode meaning in color alone.
- **Walk every state before calling UI done:** default, hover, focus, active/pressed, loading, empty, error, disabled, and dark mode. When animation is involved, replay at 10% speed — what looks wrong slow is wrong fast.

## 6. Content rules

- No lorem ipsum, no placeholder names ("John Doe", "Acme"), no AI-marketing clichés ("elevate", "seamless", "unleash", "next-gen", "game-changer"). Write plain, specific, realistic copy.
- No emojis anywhere in UI code or markup — use the icon library.
- Never fabricate data in product surfaces; sample fixtures are allowed in dev/test builds only and must be clearly marked.

## 7. Security & privacy defaults (every feature, every project)

- **Minimum data.** Collect the least personal data that works; state the purpose at collection; record consent with text version + timestamp.
- **Encryption.** Personal data encrypted at rest; secrets and keys only in environment files — never committed, never logged; encrypted fields get a blind-index column when they must be searchable.
- **User rights built-in.** Registered users get self-serve **data export** and **data deletion**; deletion is honored end-to-end (backups/analytics included where feasible); retain only integrity snapshots stripped of personal data (e.g., attribution names), and log every request.
- **Hardening baseline.** Validate every input; allow-list uploads and re-encode files; rate-limit auth and write endpoints; hash passwords (bcrypt/argon2); scope permissions per role; CSRF for cookie sessions; pinned CORS for token clients; security headers; audit sensitive admin actions.
- **Money boundary.** If the project is donation- or commission-free based, the platform never stores or processes payment credentials — display-only (e.g., show a QR/ID) or link out; payment data never enters logs or exports.

## 8. Verification — definition of done

A task is **done** only when all of these hold:

1. The code implements exactly the task comment's scope — nothing more.
2. Tests/lint/build pass for the touched area; if nothing covers it, add the smallest meaningful test (or record why not in the task comment).
3. UI changes have been walked through every state (§5).
4. The tracker is updated: status, dated note, files touched, change-log row.
5. Decisions made along the way are recorded as dated notes or ADRs in `docs/decisions/` — not left in chat history.

If verification can't be completed (no environment, missing credentials), say so explicitly in the task comment and leave the status at 🔄 — never mark ✅.

## 9. Communication

- **State assumptions explicitly.** When a decision is missing, ask **one consolidated round** of questions (never drip-feed), then proceed on the best-supported interpretation and record it in the tracker.
- **Summaries stay short:** what changed, which task IDs, which files.
- Review findings, when asked for, cite `path/to/file:line`, group by severity, and end with a verdict (block / needs changes / approve).

---

*Derived from the project's working files — tracker conventions, design-system do's/don'ts, UI-polish principles, vertical-slice planning, and privacy/security feature requirements — generalized so this file can be dropped into any repository.*



## Project tracking location — 2026-10-05

By owner instruction, the active project tracker is `plan-template/resources/plan.md` and the request intake is `plan-template/resources/request.md`. Read these wherever this agreement says `plan.md` or the adopted skill says root `request.md`; all implementation paths resolve from the repository root. Apply the workflow in `plan-template/SKILL.md`. Keep task/request links synchronized; request ticks are the owner's manual confirmation. The old root `plan.md` is a retained migration copy for the owner to delete, not the target for future task updates.

— 2026-10-05: owner moved the skill to root `plan-template/`; merged remaining SKILL metadata into root and removed the empty legacy directory.
