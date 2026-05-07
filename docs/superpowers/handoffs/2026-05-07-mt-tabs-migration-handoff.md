# MT Tabs Migration Handoff Prompt

Use this prompt in a fresh session:

```markdown
You are working in `/Users/jannisleifeld/Sites/shopware-localhost` on branch `sw-tabs-migration-to-mt-tabs`.

Goal: Continue solving `shopware/shopware#14822`: migrate Administration `sw-tabs` / `sw-tabs-item` consumers to `mt-tabs :items` behind `V6_8_0_0`, while preserving 100% backward compatibility when the major feature flag is inactive.

Read these files first:
- `AGENTS.md`
- `src/Administration/Resources/app/administration/AGENTS.md`
- `docs/superpowers/specs/2026-05-07-mt-tabs-major-feature-flag-migration-design.md`
- `docs/superpowers/plans/2026-05-07-mt-tabs-major-feature-flag-migration.md`

Important workflow context:
- We used `brainstorming`, `shopware-backward-compatibility`, `writing-plans`, and `subagent-driven-development`.
- The user chose to work on the current branch instead of a worktree.
- Do not remove legacy `sw-tabs`, `sw-tabs-deprecated`, or `sw-tabs-item` behavior while `V6_8_0_0` is inactive.
- Do not commit unrelated existing worktree changes.

Completed and reviewed:

1. Task 1: Deprecated tabs lint rule.
   - `no-deprecated-components` now reports unguarded `sw-tabs` and `sw-tabs-item` usage with the `mt-tabs` `items` migration message.
   - It allows legacy usage only in explicit inactive `V6_8_0_0` compatibility branches.
   - Tests cover allowed compatibility branches and rejected mixed boolean/active-major cases.
   - Targeted Jest passed with 69 tests.

2. Task 2: `mt-tabs` wrapper hardening.
   - `mt-tabs` forwards `new-item-active` from `mt-tabs-original`.
   - The wrapper declares `emits: ['new-item-active']` so parent listeners are not forwarded through `$attrs` and called twice.
   - Tests cover core item pass-through, extension item merge order, route behavior, and single event delivery.
   - Targeted Jest passed with 6 tests.

3. Task 3: Legacy tab deprecation.
   - Added `@major-deprecated tag:v6.8.0 - Use mt-tabs with the items property instead.` annotations/comments to `sw-tabs`, `sw-tabs-deprecated`, and `sw-tabs-item`.
   - Preserved props, refs, slots, methods, registrations, and behavior.
   - `sw-tabs.spec.js` covers inactive legacy rendering and active `mt-tabs` item pass-through.
   - Targeted Jest passed with 2 tests.

4. Task 4 started: consumer migration behind `V6_8_0_0`.
   - Completed representative consumer: `sw-users-permissions-role-detail`.
   - Completed compact batch:
     - `sw-settings-usage-data`
     - `sw-settings-tag-detail-modal`
     - `sw-settings-logging-entry-info`
     - `sw-settings-logging-mail-sent-info`
   - Active branches use `mt-tabs :items`.
   - Inactive branches preserve legacy `sw-tabs` / `sw-tabs-item` markup.
   - Added/updated tests for active and inactive feature flag states.
   - Quality review found missing `default-item`; this was fixed for the compact batch and tests now assert `defaultItem` where relevant.

Known current state:
- Admin-wide `composer eslint:admin:fix` is expected to fail until the remaining unguarded `sw-tabs` consumers are migrated, because Task 1 made the lint rule stricter.
- Targeted ESLint/Jest passed for the touched files, but final full verification has not been completed.
- There are unrelated modified files in the worktree that pre-existed or are outside this migration. Do not revert or commit them unless explicitly instructed.

Continue from Task 4 in the plan:

1. Migrate remaining `sw-tabs` consumers in small batches.
2. For each consumer:
   - Add active `V6_8_0_0` branch with `<mt-tabs :items="...">`.
   - Keep inactive branch with existing `<sw-tabs>` / `<sw-tabs-item>` markup for BC.
   - Preserve Twig blocks, props, refs, slots, routes, and extension points in the legacy branch.
   - Use real existing tab names, snippet labels, routes, and `position-identifier` values.
   - Pass `default-item` / `:default-item` so the visual active tab matches the rendered content.
   - Add or update adjacent Jest tests for inactive legacy and active `mt-tabs` behavior.
3. After each batch, run targeted Jest and targeted ESLint for touched files.
4. Review each batch for BC and code quality before continuing.
5. When all consumers are migrated, run:
   - `rg '<sw-tabs|<sw-tabs-item' src/Administration/Resources/app/administration/src`
   - `composer eslint:admin:fix`
   - `composer format:admin:fix`
   - targeted Jest for changed specs
   - full `npx jest --collectCoverage=false` from `src/Administration/Resources/app/administration` if feasible.

Useful commands:

```bash
rg -l '<sw-tabs|<sw-tabs-item' src/Administration/Resources/app/administration/src
npx jest --collectCoverage=false src/app/component/meteor-wrapper/mt-tabs/mt-tabs.spec.js src/app/component/base/sw-tabs/sw-tabs.spec.js
npx jest --collectCoverage=false eslint-rules/deprecation-rules/no-deprecated-components.spec.js
git status --short
git diff --stat
```

Before claiming completion, use verification-before-completion and report exact commands/results.
```
