# ✅ Done: Codemod Integrated Into Admin Codemod Tooling

**Status:** Done. `npm run codemod:sfc-migration` wired into `package.json` alongside the other `codemod:*` scripts.

---

## Current invocation

```bash
npx tsx src/Administration/Resources/app/administration/scripts/codemods/sfc-migration/run-sfc-migration.ts <path>
```

This requires knowing the exact path to the runner script. It is not discoverable through any standard tooling.

---

## What the existing codemod infrastructure looks like

The Administration has a codemod CLI that other codemods use. Relevant files:

- `src/Administration/Resources/app/administration/code-mods.js` — the main codemod CLI entrypoint
- `package.json` in the administration package — contains `scripts` entries for running codemods

Existing codemods are invocable via something like:

```bash
npm run codemod -- <codemod-name> <path>
```

The SFC migration codemod should be registered here so it is discoverable and invocable the same way.

---

## What needs to be done

### 1. Register in `code-mods.js`

Add the SFC migration codemod as a named entry in the codemod registry:

```js
// code-mods.js (approximate)
const codemods = {
    // ... existing codemods ...
    'sfc-migration': require('./scripts/codemods/sfc-migration/run-sfc-migration'),
};
```

The exact integration pattern depends on how existing codemods are structured — read `code-mods.js` to match the existing pattern before implementing.

### 2. Add a `package.json` script entry

Add a convenient npm script:

```json
{
    "scripts": {
        "codemod:sfc-migration": "tsx scripts/codemods/sfc-migration/run-sfc-migration.ts"
    }
}
```

This allows:

```bash
npm run codemod:sfc-migration -- src/app/component/sw-button
```

### 3. Update `README.md`

Once integrated, update the usage section to show the canonical invocation via `npm run codemod:sfc-migration` rather than the raw `npx tsx` command.

---

## Files to read before implementing

- `src/Administration/Resources/app/administration/code-mods.js` — understand the existing codemod registry structure
- `src/Administration/Resources/app/administration/package.json` — check existing script conventions

---

## What was done

- `code-mods.js` is an ESLint-based tool for plugin quality checking — a completely separate system. No integration there was appropriate.
- Added `"codemod:sfc-migration": "ts-node --transpileOnly ./scripts/codemods/sfc-migration/run-sfc-migration.ts"` to `package.json`, matching the existing `codemod:*` script pattern.
- Removed `import.meta.url` usage from `run-sfc-migration.ts` (ESM-only; incompatible with `ts-node --transpileOnly` CommonJS mode). Replaced with the CJS-native `__filename` global.
- Updated `README.md` to show `npm run codemod:sfc-migration -- ...` as the canonical invocation.
- Updated the file-header comment in `run-sfc-migration.ts` to match.

## Acceptance check

- [x] A `package.json` script entry exists for easy invocation
- [x] `README.md` updated to use the canonical invocation
- [x] Running via `npm run codemod:sfc-migration` produces the same output as the direct invocation
- [x] All 179 tests still pass
