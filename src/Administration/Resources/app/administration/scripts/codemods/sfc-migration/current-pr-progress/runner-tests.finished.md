# Missing: `run-sfc-migration.ts` Has Zero Test Coverage

**Status:** No test file exists for the CLI runner.

---

## What is untested

`run-sfc-migration.ts` contains all of the following logic that has never been exercised by a test:

| Behavior | Function / code path |
|----------|---------------------|
| Recursive `index.js` discovery | `glob('**/index.js', ...)` call |
| Companion `.html.twig` discovery | `findTwigFile(dir)` |
| Skip behavior when no `.html.twig` found | `if (!twigPath)` branch |
| `export default {}` normalization | `normaliseJsContent(content, name)` |
| Fully-migrated file write | `fs.writeFileSync` for `status === 'fully-migrated'` |
| Partially-migrated file write | `fs.writeFileSync` for `status === 'partially-migrated'` |
| Not-migratable skip | No file written when `status === 'not-migratable'` |
| Output file naming | `<component-name>.vue` derivation |
| Per-file console report | `✓`, `~`, `✗`, `SKIP` symbols |
| Final summary counts | Aggregation of status counts |

---

## How to write these tests

Use a temporary directory (e.g., `os.tmpdir()` + a unique subdirectory via `fs.mkdtempSync`) populated with fixture files. This avoids touching the real repo and is fully isolated.

### Test setup pattern

```ts
import os from 'os';
import path from 'path';
import fs from 'fs';

let tmpDir: string;

beforeEach(() => {
    tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-migration-test-'));
});

afterEach(() => {
    fs.rmSync(tmpDir, { recursive: true, force: true });
});
```

### Test cases to write

**1. Fully-migratable component → writes `.vue`**
- Create `component-dir/index.js` (simple component) + `component-dir/sw-card.html.twig`
- Run the migrator on `tmpDir`
- Assert `sw-card.vue` was written
- Assert `sw-card.vue` contains `<script setup>`

**2. Not-migratable component → no file written**
- Create `component-dir/index.js` (render-component fixture)
- Assert no `.vue` file written

**3. Partially-migratable component → writes `.vue` with plain `<script>`**
- Create `component-dir/index.js` (mixin-component fixture)
- Assert `.vue` written with plain `<script>` (not `<script setup>`)

**4. Missing `.html.twig` → component skipped**
- Create `component-dir/index.js` only (no twig)
- Assert no `.vue` file written
- Assert `SKIP (no twig)` appears in output

**5. `export default {}` normalization → writes valid `.vue`**
- Create `component-dir/index.js` using `export default { ... }` style
- Assert output `.vue` is written (normalization worked)

**6. Recursive discovery**
- Create components in nested directories
- Assert all are processed

**7. Summary output**
- Run with mixed fixtures
- Assert printed summary contains correct counts

**8. Overwrite behavior (once `overwrite-protection` is implemented)**
- Create pre-existing `.vue` file
- Assert either overwrite is blocked or a warning is printed

---

## Relevant files

- `run-sfc-migration.ts` — the file under test
- `__fixtures__/` — reuse existing fixture files in temp dirs

---

## Acceptance check

- [ ] Test file `run-sfc-migration.spec.ts` exists
- [ ] All behaviors listed above have at least one test each
- [ ] Tests use a temp directory (no writes to the actual repo)
- [ ] Tests clean up after themselves in `afterEach`
- [ ] Tests pass in CI
