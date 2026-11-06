# Administration public API snapshot: working plan

Status: workflows 1a and 1b are done.
The format spec (`public-api/FORMAT.md`), the golden snapshot excerpt below `public-api/` and the fixtures below `scripts/generate-public-api/__fixtures__/` are waiting for review.
There is no generator yet.
Issue: [shopware/shopware#22591](https://github.com/shopware/shopware/issues/22591).
Branch: `22591-admin-make-public-api-breaks-statically-analyzable`.
Background proposal: [Review-Engpass Administration](https://claude.ai/artifact/9mZ2k5SMAXzLosYNv94XQH) (artifact, German and English).
Last updated: 2026-11-06.

This file is a working document for this branch.
Once the format is settled, the durable decisions belong in an ADR, and this file should be removed.

## Problem

Coding agents now write most Administration PRs, and we cannot review all of them by hand.
We also do not want to rely on AI reviews alone.

In the Administration, the main review risk is breaking extensions.
Plugins hook into the Administration through names they only know as strings: components, blocks, methods called via `$super`, props, services, stores, routes and snippet keys.
If a PR renames or moves one of them, neither the build nor the tests fail, and the plugin stops working.
Today the only way to see what is public is to read the code and know the conventions.

Real cases that slipped through:

- PR #20959 added a `<div>` that opens outside two blocks and closes inside the inner one.
  A plugin that replaces the inner block without `{% parent %}` breaks the page (issue #21108, fixed in #21189).
  The same PR also moved a block into another block.
- PR #17339 renamed a block only when the major feature flag is on.
  The bulk edit lost all custom field checkboxes, noticed four weeks later (issue #19601).
- During the UI shell rework (PR #15271), blocks of `sw-admin-menu` were deleted and `blocks-list.json` was regenerated, so `meta.spec.js` turned green again.
  The review guide names `sw_admin_menu_header_logo`, but that block still exists; the removed blocks are listed in the 1a research.

## Goal

Generate a committed, static representation of the complete Administration public API from the source code.
A PR that changes the public API then shows that change in its diff, and CI fails on unannounced breaks.
Plugins can use the same snapshot to check whether they are still compatible with a Shopware version.

We design the output first and build the generator second.
Hand-written golden files define what the generator has to produce.

## Non-goals

- The approval rules from the background proposal (1 approval for small PRs, the 300-line limit, `disable_smart_dismissal`, branch protection).
  The snapshot and the check are the basis for those rules, but the rules are a separate decision.
- Detecting behavior changes inside a method or a block.
  Changing `new Criteria(1, 25)` to `new Criteria(1, 500)` stays a human judgment.
- Making large PRs smaller.

## Current state in the repository

Paths are relative to `src/Administration/Resources/app/administration/`.

| What | Where | Notes |
|---|---|---|
| Block list | `blocks-list.json`, `scripts/generate-block-list/` | About 8,000 lines, names only, no nesting |
| Position identifiers | `src/meta/position-identifiers.json`, `scripts/generate-position-identifier-list/` | |
| Data sets | `src/meta/data-sets.json`, `scripts/generate-data-set-list/` | |
| `shopware:*` modules | `shopware-modules.json`, `src/shopware-virtual-modules.d.ts`, `scripts/generate-shopware-modules/` | Added in #20246, the `.d.ts` has about 980 lines |
| Shared source file rules | `scripts/public-api-source-files.ts` | Used by all list generators and `meta.spec.js` |
| Guard test | `src/meta/meta.spec.js`, `src/meta/baseline.ts` | Compares templates against the lists |
| Extension type surface | `extension-tooling/admin-types.d.ts` | Imports `global.types.ts` and the generated declarations for extensions |
| Extension checks | `composer admin:check-extensions`, `scripts/extensionTooling/` | Type-checks and lints installed extensions |
| Composition API public API | `ComponentPublicApiMapping`, `swDefinePublic`, `swDefineOverride` | Experimental, feature `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` |
| Global object | `src/core/shopware.ts`, `src/global.types.ts` | |
| BC review guides | `.agents/skills/sw-review/guides/` (repository root) | `bc-admin.md`, `bc-extension-points.md`, `admin-bc-and-flags.md` |
| PHP equivalent | `roave/backward-compatibility-check` in the root `composer.json` | |

The public API rule is already documented in `technical-docs/11-code-quality/02-unused-code.md`: every export without `@private` is public API, and removing one follows the deprecation process.
At the time of writing the source contains about 1,500 `@private`, 500 `@deprecated tag:v6.8.0`, 70 `@deprecated tag:v6.9.0` and 50 `@public` tags.
About 460 of roughly 880 component, view and page index files contain `@private`.
`ts-morph` is already installed.

## Decisions

| # | Topic | Decision |
|---|---|---|
| D1 | What is public | The documented rule: everything without `@private` is public. |
| D2 | Private parts | `@private` parts are part of the snapshot, in the same file as the public parts, marked as private. The check never reports a break for them. |
| D3 | Primary consumer | Reviewers and plugins are equally important. The snapshot has to produce readable PR diffs and be a usable type contract from the start. |
| D4 | Scope | All four areas: the `Shopware` global object with services, stores and `shopware:*` modules; the component API (name, props, events, slots, methods and computed properties reachable via override and `$super`); the block tree with nesting (Twig and native blocks); routes, snippet keys, position identifiers and data sets. |
| D5 | Format | `.d.ts` files as the machine-readable contract, plus a generated plain-text report in the style of API Extractor's `.api.md` for reviewers. |
| D6 | Feature flags | Two snapshots: one for the current state and one with the major feature flag enabled. Both are committed and both are compared. |
| D7 | CI | Two checks. A drift check regenerates the snapshot and fails when `git diff` is not empty. A break check runs the generator from trunk on the base and head commits, classifies the difference and blocks unannounced breaks, even when the PR committed a regenerated snapshot. |
| D8 | Plugin consumption | The snapshot ships in the Administration package. `extension-tooling/admin-types.d.ts` includes it, and `admin:check-extensions` checks a plugin against the installed version. |
| D9 | Coverage | The whole Administration, not a slice. |
| D10 | Existing lists | Replace them directly. `blocks-list.json`, `position-identifiers.json`, `data-sets.json` and `shopware-modules.json` go away, and `meta.spec.js` plus the generators move to the new snapshot. |
| D11 | HTML page | A simple page, generated deterministically from the classified diff: verdict, one card per finding, and the code tree. No simulation of plugin overrides. |
| D12 | Where to work | Directly on branch `22591-admin-make-public-api-breaks-statically-analyzable`. Nothing is committed or pushed without an explicit go. |
| D13 | `@private` on an export | `@private` on a component export, a mixin export or a whole component makes everything of it private, including the registered name and its blocks. |
| D14 | Intended breaks | A committed acceptance file with one entry per finding (finding id, element id, mandatory justification), protected by an owner entry in the root `.codeowners` file (codeowners-plus; `.github/CODEOWNERS` is intentionally empty). No PR label. |
| D15 | Machine index | Keep `public-api/index.json` as the replacement for the four old lists in Vite, Jest and ESLint. |
| D16 | Referenced types | Types from `src/` that the snapshot references (for example `NotificationType` or `ServiceContainer` entries) get their own snapshot, so a change to them shows up as a diff. |
| D17 | Native SFCs | The format and generator must support native setup SFCs and their extension system (`swDefinePublic`, `swDefineOverride`, `ComponentPublicApiMapping`, `<sw-block>`) from the start. |
| D18 | Existing crossings | The crossings that already exist on trunk are part of the baseline snapshot. Only new crossings are findings. |
| D19 | Snippet signatures | Snippet keys carry their signature: placeholder names and the number of plural forms. Texts are never part of the snapshot. This replaces assumption A3. |
| D20 | Typed `Component.override` | Core types `Component.override` as an interface with today's signature, and the snapshot adds a generic signature through interface merging. Plugins keep calling `Shopware.Component.override(...)` without a cast. |
| D21 | Outsourced overrides | Override configs in separate files use a new helper `Component.wrapOverrideConfig('<target>', {...})`, so they get the same types. |
| D22 | Report view | The `.api.md` reads like a declaration outline without tree glyphs: blocks as a tree with two spaces per level, siblings sorted, no column alignment, markers after two spaces, `[private]` on every line of a private entry, both flag states merged in one report. The break check adds a plain-language summary grouped by break, addition, allowed, accepted, private and info, and GitHub annotations on the head source line. Snapshot files stay collapsed in PR diffs. |
| D23 | Version markers | The report names the flag states by version: `[until v6.8.0]` and `[from v6.8.0]` instead of `[current only]` and `[major only]`, so the marker stays readable in old commits. |
| D24 | Block context | Blocks record whether they follow an open `v-if` chain and which `v-for` and slot variables they can use, and `v-if` chains across blocks count as crossings. Losing either, or a new chain crossing, is a break. |
| D25 | Template refs and classes | Components record their static template refs (name and target tag) and CSS classes. A missing or retargeted ref is a break; a missing class is info. Source: 635 store plugins read core `$refs`, 1,541 style core classes. |
| D26 | Registries | Filters, directives, ACL privileges, settings groups, CMS blocks, elements and categories, rule conditions and groups, search types, flow actions and custom field types that core registers are units of the snapshot. A missing entry or a changed group is a break. |
| D27 | Events and flags | Event bus event names with their emitters, and feature flag names, are units. A missing event is a break; a missing flag is info. |
| D28 | SCSS | Variables and mixins that plugins import from `~scss/` are units. A missing name is a break, a changed value is info. |
| D29 | Plugin tooling warnings | `admin:check-extensions` also checks the new names and warns about `$parent` access and imports from `src/`. |
| D30 | Store usage (proposal) | A store usage index with counts per element id annotates findings, reports private elements that store plugins use, and detects name collisions. Where it comes from in CI is open. |

## Assumptions

These were not asked explicitly.
Correct them here if they are wrong.

- A1: Only the core Administration is generated.
  The Storefront bundle's Administration extension and locally installed plugins serve as real consumers for verification.
- A2: `@deprecated tag:vX` and `@experimental` stay visible as markers in the snapshot.
  Removing something in the major that its `@deprecated` tag announced is not a break.
- A4: The folder name and the file split (one file per component or per module) are proposed together with the golden files.

## Open questions

- Whether the SFC migration and the move to native blocks need their own classification rule, now that native SFCs are in scope (D17).
- Whether the HTML page can be attached to a PR as a GitHub issue artifact, or has to live on the Actions run page.

## Break taxonomy (first draft)

Each row becomes a fixture with a before state, an after state and the expected report.

| Change | Classification |
|---|---|
| Code inside a method changes | No break |
| Attributes, text or new elements inside a block | No break |
| New optional prop, new block, new method | Addition |
| Component, block, method, computed property, service, store member, route or snippet key removed or renamed | Break |
| Block moved under a different parent block | Break |
| HTML element opened in one block and closed in another (#20959) | Break |
| Block renamed only with the major flag on (#17339) | Break in the major snapshot |
| Block loses a template variable from a `v-for` or slot, for example `item` or `itemIndex` (#20959) | Break |
| Block no longer follows an open `v-if` chain that overrides may continue with `v-else-if` or `v-else` | Break |
| `v-if` chain continues in another block, so overriding the first block breaks the chain | Break |
| Template ref removed or now on another component | Break |
| CSS class removed from a template | Info |
| Filter, directive, privilege, settings group, CMS block or element, rule condition, search type, flow action, custom field type or event removed | Break |
| Rule condition or CMS block moved to another group or category | Break |
| SCSS variable or mixin removed, mixin parameters changed | Break |
| SCSS variable value changed, feature flag removed | Info |
| Prop removed, renamed, or changed from optional to required | Break |
| Method parameters changed in an incompatible way | Break |
| Removal announced with `@deprecated tag:vX`, done in that major | Allowed |
| Any change to a `@private` part | Reported, never a break |

## Plan

1. Output first.
   Write hand-made golden files on the branch: the folder structure, snapshots for `sw-sales-channel-detail`, `sw-order-detail` and a slice of the `Shopware` object, the taxonomy fixtures, and examples of how a PR diff and the CI output look.
   Stop here for review before writing the generator.
2. Generator.
   Build it for the whole Administration and for both flag states.
   Use `ts-morph` or the TypeScript compiler API for TypeScript surfaces, `vue-component-meta` or a custom extractor for components (Options API configs and native SFCs), and a parser that keeps the Twig and native block tree with nesting.
   Output has to be deterministic and sorted.
3. Classifying diff and CI.
   Compare base and head with the trunk generator, classify by the taxonomy, add the drift check and the break check.
4. Replace the existing lists.
   Move `meta.spec.js` and the `scripts/generate-*` generators to the snapshot and delete the old JSON files.
5. Plugin compatibility.
   Include the snapshot in `admin-types.d.ts` and extend `admin:check-extensions`.
   Besides `tsc`, plugins need an index of names they use only as strings: `Component.override` targets, `$super` method names and block names in plugin templates.
6. HTML page.
   Generate the simple page from the classified diff.
7. Verification (see below).

Prior art to check before step 2: Microsoft API Extractor, `elm diff`, `cargo-semver-checks` and `roave/backward-compatibility-check`.

## Verification

The prototype is done when these pass:

- Backtest on the real commit pairs of #20959, #17339, the UI shell rework block deletion and #17081.
  All known breaks are reported, including both findings in #20959, and #17081 has no finding.
- Mutation check: renaming a block, a method and a prop that the Storefront bundle's Administration extension uses makes the check fail each time.
- Two runs on the same commit produce byte-identical output.
- A PR that only regenerates the snapshot still fails the break check.
- On about 50 random merged Administration PRs, fewer than 5% of findings are false alarms, judged together with an Administration lead.

## Workflow for step 1

Step 1 runs as two workflows with a review by us in between.
Every workflow agent only reads code or writes files below `public-api/` and the fixture folder; nothing is committed.

### Workflow 1a: understand and choose a format

1. Inventory (8 read-only agents in parallel, structured output).
   - The `Shopware` object, service container, stores and `shopware:*` modules.
   - Options API components: `register`, `extend`, `override`, `wrapComponentConfig`, mixins, and what an override can reach via `$super`.
   - Native SFCs and the Composition API extension system: `swDefinePublic`, `ComponentPublicApiMapping`, `<sw-block>`.
   - The Twig and native block tree, position identifiers and data sets, starting from the existing generators.
   - Routes and snippet keys.
   - Real consumers: the Storefront bundle's Administration extension, `custom/plugins/SwagCommercial` and `custom/plugins/SwagPayPal`, and which core names they use as strings.
   - Prior art: API Extractor `.api.md`, `elm diff`, `cargo-semver-checks`, `vue-component-meta` output.
   - History: the exact base and head commits and the affected snippets for #20959, #17339, the UI shell rework block deletion and #17081.
2. Format proposals (3 agents, after the inventory is complete).
   Each proposes a different folder layout and file split and writes `sw-sales-channel-detail` in that format.
3. Judges (3 agents per proposal, one lens each).
   - Reviewer: how the PR diff reads for the taxonomy cases.
   - Plugin: whether `tsc` accepts it through `admin-types.d.ts` and whether a plugin can type an override against it.
   - Generator: whether it can be generated deterministically for all of the Administration.
4. Synthesis (1 agent): one format spec, built on the winning proposal with the best parts of the others.

Then we review the format spec before anything else is written.

### Workflow 1b: golden files

1. Writers, as a pipeline, one agent per target.
   - Snapshots for `sw-sales-channel-detail`, `sw-order-detail` and a slice of the `Shopware` object, both flag states.
   - One fixture per taxonomy row: before, after, expected report.
   - For each historical case: the expected snapshot diff, the CI output and the simple HTML page.
2. Verification, in the same pipeline, per target.
   - Every name in a golden file exists in the source at the stated commit, and nothing public is missing.
   - The `.d.ts` files compile with `tsc` inside the Docker container.
   - A skeptic tries to show that the expected classification of a fixture is wrong.
3. Completeness critic (1 agent): which surface, case or file from this plan has no golden file yet.

Then we review the golden files, and only after that starts step 2 (generator).

Result of 1b:

- Snapshot excerpt: `sw-sales-channel` and `sw-order` (module, detail pages, snippets), the mixins `notification` and `placeholder`, the `Shopware` object with a service and a store, the `shopware:*` virtual modules, and the referenced source types below `types/` (D16).
- `current.d.ts` and `major.d.ts` reference every snapshot file and compile with `skipLibCheck: false` without a diagnostic below `public-api/`.
  Route components that have no snapshot file yet are stubbed in `_excerpt/route-component-stubs.d.ts`.
- `index.json` covers the excerpt, and its `virtualModules` equal the old `shopware-modules.json`.
- 22 taxonomy fixtures and 4 historical cases (#20959, #17339, #15271, #17081), each with expected findings, the historical ones also with snapshot diffs and the HTML page.
- The cases not written yet and the spec gaps the review found are listed at the end of `FORMAT.md` ("Test fixtures" and "Open points").

## How to continue

Step 1 is done up to the review.
After the review, settle the "Open points" in `public-api/FORMAT.md`, then start step 2: the generator, tested against the fixtures.
