# Missing: `normaliseJsContent` Uses Fragile String Replacement

**Status:** Implemented but fragile — can produce invalid JavaScript for components with nested `};` patterns.

---

## Current behavior

`run-sfc-migration.ts` contains `normaliseJsContent` to handle components written as:

```js
export default {
    template,
    data() { return { ... }; },
    methods: {
        save() {
            const config = {
                retry: true,
            };  // ← nested };
        },
    },
};  // ← this is the "last };" to replace
```

The current implementation finds the **last occurrence of `};`** in the file and replaces it with `});` to close the `Shopware.Component.register(...)` call it wraps around the component:

```ts
function normaliseJsContent(jsContent: string, componentName: string): string {
    // ...
    return wrapped.replace(/\};\s*$/, '});');
    // or similar last-}; replacement
}
```

**The bug:** If the component has a nested `};` pattern at module level (e.g., a module-level const object or a method containing an object literal that ends with `};`), the regex may match the wrong `};` and produce invalid JavaScript.

---

## Example of failure

```js
const DEFAULT_CONFIG = {
    timeout: 5000,
};  // ← this gets replaced instead of the outer export default };

export default {
    data() { return {}; },
};
```

The normaliser would replace the first `};` (from `DEFAULT_CONFIG`) and produce:

```js
Shopware.Component.register('name', const DEFAULT_CONFIG = {  // invalid!
```

---

## What needs to be done

### Replace with AST-based normalization

Use `ts-morph` (already a dependency used by `transform-script.ts`) to parse the file and programmatically:

1. Find the `export default { ... }` declaration
2. Extract its object literal
3. Re-wrap it as `Shopware.Component.register('name', { ... })`

This is immune to nested `};` patterns because it operates on the AST, not raw text.

```ts
import { Project } from 'ts-morph';

function normaliseJsContent(jsContent: string, componentName: string): string {
    const project = new Project({ useInMemoryFileSystem: true });
    const sourceFile = project.createSourceFile('tmp.js', jsContent);
    
    const exportDefault = sourceFile.getExportAssignment(e => !e.isExportEquals());
    if (!exportDefault) return jsContent; // not export default style
    
    const objectLiteral = exportDefault.getExpression().getText();
    
    // Remove the export default
    exportDefault.remove();
    
    // Add the register call
    sourceFile.addStatements(
        `Shopware.Component.register('${componentName}', ${objectLiteral});`
    );
    
    return sourceFile.getFullText();
}
```

---

## Alternative: Stricter regex

If AST is considered overkill, a stricter regex that only matches `};` at the end of the file (with optional trailing whitespace and newlines) is safer than finding the "last" occurrence anywhere:

```ts
jsContent.replace(/\};\s*\n?\s*$/, '});')
```

This still fails for edge cases but is significantly less likely to match the wrong position.

---

## Relevant file

- `run-sfc-migration.ts` — `normaliseJsContent` function

---

## Acceptance check

- [ ] `normaliseJsContent` correctly handles components with module-level `};` patterns
- [ ] Test in `run-sfc-migration.spec.ts` covers `export default {}` normalization with a nested object
- [ ] The fix uses AST-based parsing or is accompanied by a documented rationale for why the simpler approach is safe enough
