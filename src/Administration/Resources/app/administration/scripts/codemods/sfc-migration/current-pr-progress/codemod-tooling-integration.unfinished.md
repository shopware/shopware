# Missing: Codemod Not Integrated Into Admin Codemod Tooling

**Status:** The SFC migration codemod is only runnable via manual `npx tsx` invocation. It is not wired into the standard Shopware Administration codemod infrastructure.

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

## Acceptance check

- [ ] `code-mods.js` registers the SFC migration codemod
- [ ] A `package.json` script entry exists for easy invocation
- [ ] `README.md` updated to use the canonical invocation
- [ ] Running via the standard codemod CLI produces the same output as the direct `npx tsx` invocation
