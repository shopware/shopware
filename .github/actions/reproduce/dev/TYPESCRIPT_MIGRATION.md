# TypeScript migration plan — reproduce action

Status: **DONE** — sources migrated to `.ts` (native type-stripping, no build), `tsc --noEmit` strict
is clean, `typescript-eslint` in place, all tests green. Retained as the rationale/decision record.
The one remaining real-world validation is a **fork end-to-end run** (nothing type-checks in the
pipeline itself; the runtime traps are Node < 22.18 and a missed `import type`).

## Goal & guiding constraint

Type the reproduce action's Node ESM sources (`.mjs`, ~3,900 LOC across 35 modules) **without
introducing a build step**. The action runs by copying itself to an immutable dir and invoking
`node …/repro.<ext>` directly (see `reproduce.md` "Stage tooling"); its trust model depends on *the
copied source being what runs*. A `tsc` emit would recreate the source-vs-generated sync problem we
already carry for `reproduce.lock.yml` and weaken that guarantee.

**Therefore: run `.ts` via Node's native type-stripping** (stable/default on Node ≥22.18 and ≥23.6).
Running needs only an up-to-date Node — **no `tsc`, no build, no emit**.

**Type-*checking* is deliberately NOT a pipeline gate.** Types here are for *documentation + editor
assist* (VS Code's tsserver checks live as you write, for free), not a compile-time CI safety net.
The runtime safety net stays what it is today: the 186 tests + 122 bash assertions. `tsc --noEmit`
exists only as an **optional local** `npm run typecheck` you run when you want a full-project sweep —
it never blocks CI. Consequence to accept knowingly: a wrong annotation won't fail the pipeline
(types are erased before anything runs); only wrong behavior does.

Type-stripping constrains us to **erasable syntax only** (no `enum`, `namespace`, parameter
properties, `import =`) — the code already uses none. The `tsconfig` sets `erasableSyntaxOnly` so the
editor flags any slip.

## Preconditions (verify before Phase 1)

- [ ] Node that runs the CLI is **≥22.18** on every path. Today: `actions/setup-node@… node-version: 22`
  (resolves latest 22.x ✅) on the runner; the CLI runs **host-side**, not inside the PHP/Playwright
  sandbox containers, so those images are irrelevant. Pin `node-version: '22'` (not an exact old patch)
  or add `--experimental-strip-types` as a belt-and-suspenders flag in the wrapper.
- [ ] Confirm no other entrypoint executes a `repro.*` file under an older Node.

## Phasing overview

| Phase | What | Renames? | Effort | Ships independently |
|---|---|---|---|---|
| 0 | Tooling (`tsconfig` for the editor) + domain types via `checkJs` on the existing `.mjs` | no | 0.5 d | yes |
| 1 | Atomic `.mjs`→`.ts` rename + import-extension fix + `import type` pass + workflow wrapper | **all at once** | 0.5 d | yes |
| 2 | Opportunistic `strict` + boundary typing — done whenever, not a mandated rollout | no | ongoing | yes |

**Target for a "runnable, typed, editor-checked" state (Phases 0–1): ~1 day.** Everything beyond is
optional polish you do as you touch code — there is no CI gate forcing it, so it never blocks.

Dropped from an earlier draft (as over-engineered for an internal tool): a blocking `tsc` CI gate and
a mandated tier-by-tier strict marathon. `tsc` stays an optional local sweep; `strict` is opt-in.

---

## Phase 0 — Editor tooling + domain types (no renames)

Get types + editor checking in place on the existing `.mjs` first. Fully reversible.

1. Add devDeps: `typescript`, `@types/node` (matching the runtime major). (`typescript-eslint` only if
   we later add TS lint rules — see Phase 2.)
2. `tsconfig.json` (drives tsserver in the editor; `tsc` is optional-local, never CI-gating):
   ```jsonc
   {
     "compilerOptions": {
       "module": "nodenext",
       "moduleResolution": "nodenext",
       "target": "es2023",
       "lib": ["es2023"],
       "types": ["node"],
       "noEmit": true,
       "allowImportingTsExtensions": true,
       "allowJs": true,
       "checkJs": true,          // editor checks the .mjs via JSDoc until the rename
       "erasableSyntaxOnly": true,
       "strict": true,           // editor-only signal; nothing gates on it
       "skipLibCheck": true
     },
     "include": ["**/*.mjs", "**/*.ts"],
     "exclude": ["node_modules", "executors/playwright/boilerplate"]
   }
   ```
3. Author the **domain types** in `types.ts` (see model below); reference from existing JSDoc
   (`@param {import('./types.ts').Plan} plan`). Start with `bundle.mjs` (the hub) + `report/verdict`.
4. Add an **optional** `npm run typecheck` = `tsc --noEmit`. Run it locally when you want a full sweep;
   **do not** add it as a blocking CI step.

**Exit:** editor type-checks as you write; core types exist; `npm run typecheck` available on demand.

## Phase 1 — Atomic rename `.mjs` → `.ts` (the only "big" step)

Import specifiers couple the rename: under type-stripping Node does **not** rewrite paths, so the
moment `bundle.mjs` becomes `bundle.ts`, every `import … from './bundle.mjs'` must become `./bundle.ts`
in the same change. So this is **one scripted, atomic commit**, not file-by-file.

1. **Pin Node ≥22.18** wherever the CLI runs (see Preconditions). Optionally add
   `--experimental-strip-types` to the wrapper as belt-and-suspenders.
2. Script the rename: `git mv` each non-boilerplate `*.mjs` → `*.ts`; rewrite relative import specifiers
   `'(\./…)\.mjs'` → `'$1.ts'` across sources **and** the test files' imports.
3. **`import type` pass** — the one real runtime gotcha: a type-only symbol imported as a value is NOT
   erased and throws at runtime. Convert every type-only import to `import type { … }`. tsserver/ESLint
   flags misses; a quick `tsc --noEmit` sweep catches them all at once.
4. Test files: keep them `.mjs`, just fix their import specifiers to `.ts` (a `.mjs` importing a `.ts`
   module works under strip mode) — avoids churning the freshly-written suite.
5. **Workflow wrapper + invocations** (`reproduce.md`, then `dev/compile.sh` to regenerate the lock):
   - `exec node /tmp/reproduce/cli/repro.mjs` → `repro.ts`
   - the `node …/cli/repro.mjs {validate,verify,blocked-result}` calls → `repro.ts`
6. **CI** (`reproduce-action-tests.yml` + `package.json`): drop the `node --check *.mjs` step (invalid on
   `.ts`); rely on the tests. `test`/`test:coverage` globs unchanged (tests stay `.mjs`). No `tsc` gate.
7. Verify: `npm test && npm run test:bash && npm run lint` green **and** one real repro end-to-end on a
   fork — this is the check that matters, since a type/strip problem shows up at runtime, not in CI.

**Exit:** everything is `.ts`, runs with no build step, editor-typed, all tests pass, one fork run OK.

**Rollback:** single revert of the rename commit + lock regen.

## Phase 2 — Opportunistic polish (no deadline, no gate)

Do these as you touch code; none of it blocks anything.

- Tighten types where you're already editing; prefer `unknown` + a narrow over `any` at JSON/`fetch`
  boundaries. Natural high-value targets: a single `parsePlan(raw: unknown): Plan` guard (hand-rolled,
  no runtime dep) shared by `validate`/`execute-bundle`/`full-run`; typed `admin-api` responses; the
  `LegResult`/`Evidence` contract shared by executors + `verdict` + `comment`.
- Expect mostly-mechanical fixups when you do: `process.getuid()` is `number|undefined`,
  `spawnSync().status` is `number|null`, `readJson`/`fetch().json()` are `any`.
- If you ever want lint-level type rules, add `typescript-eslint` (`no-floating-promises`,
  `no-misused-promises` are worth it for this async CLI) and drop the `**/*.ts` ESLint ignore.
- Optionally convert `*.test.mjs` → `*.test.ts`.
- Update `README.md` "Linting & tests": document the no-build type-stripping model + the
  `import type` / `erasableSyntaxOnly` constraints; drop the "TypeScript is a follow-up" note.

---

## Domain type model (sketch — `types.ts`)

```ts
export type Executor = 'playwright' | 'http' | 'direct';
export type Layer = 'storefront-ui' | 'admin-ui' | 'store-api' | 'admin-api' | 'service';
export type LegStatus = 'reproduced' | 'not_reproduced' | 'inconclusive' | 'blocked';
export type Verdict = 'live_bug' | 'fixed_on_trunk' | 'regression' | 'not_reproducible'
  | 'blocked' | 'needs_human_review';

export interface HttpAssertion {
  kind?: 'http_status' | 'response_field';
  field?: string;                     // jq filter
  op?: 'equals' | 'contains' | 'matches' | 'present' | 'absent' | 'gt' | 'lt';
  expect?: string | number;
  role?: 'assert' | 'precondition';
  label?: string;
}
export interface Plan {
  executor: Executor;
  layer: Layer;
  issue: number;
  version: string;
  confidence?: number;
  script_path?: string;
  request?: HttpRequest; requests?: HttpRequest[];
  assertion?: HttpAssertion; assertions?: HttpAssertion[];
  seeded_readiness?: ReadinessCheck[];
  fixtures?: { demodata?: boolean };
  viewport?: { width: number; height: number };
  record_video?: boolean;
  // …trust fields: blocked_reason, agent_explanation, derived_from
}
export interface LegResult {
  schema_version: '1'; issue: number; target: string; version: string;
  executor: Executor | 'unknown'; status: LegStatus;
  assertion: { matched: boolean | null; checks?: AssertionCheck[] };
  evidence: Evidence; blocked_reason: string | null;
}
```

## Gotchas checklist

- [ ] **`import type` for every type-only import** — the one gotcha that breaks at *runtime* (not just
  in an editor): under stripping, a type imported as a value isn't erased and throws. This is why we
  don't need `tsc` to *run*, but we do need this discipline.
- [ ] Import extensions must be `.ts` everywhere (no path rewriting under strip mode).
- [ ] Keep **erasable-syntax-only** — no enums; use string-literal unions. (Editor flags slips via
  `erasableSyntaxOnly`; nothing else enforces it, so it's a review habit.)
- [ ] `dev/compile.sh` + `reproduce.lock.yml` must be regenerated after the wrapper edit (Phase 1).
- [ ] No new runtime `node_modules` at action runtime — `typescript`/`@types` are **devDependencies**
  only; the runtime still needs zero deps (strip is built into Node).
- [ ] For the optional local `tsc`: global `fetch` typing needs recent `@types/node` + `lib: es2023`
  (no `lib: dom`); `process.getuid()` is `number|undefined`; `spawnSync().status` is `number|null`.
- [ ] Playwright `boilerplate/` stays excluded from the Node tsconfig (browser/Playwright context).

## Risk & rollback

- **The only failure mode that reaches production is runtime, not CI** — because nothing type-checks in
  the pipeline. Two runtime traps: (a) Node < 22.18 somewhere the CLI runs → `.ts` won't execute; (b) a
  missed `import type` → a dead value import throws. Both are caught by **one real fork end-to-end run
  in Phase 1** plus an optional local `tsc --noEmit` sweep before merging the rename. This is the
  accepted trade for "no build, no gate": the fork run replaces the CI type gate.
- **Each phase is an independent, revertable commit.** Phase 1 (the rename) is the only "big" one and
  reverts cleanly as a unit.
- Behavior regressions are still caught by the existing **186 tests + 122 bash assertions** (unchanged).
