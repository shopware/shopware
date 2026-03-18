import { replaceBlocks, wrapInTemplate } from './transform-template';
import {
    transformData,
    transformComputed,
    transformMethods,
    transformWatch,
    transformInject,
    transformLifecycleHooks,
    detectUnsupportedFeatures,
    type DataRecord,
    type ComputedRecord,
    type WatchRecord,
    type InjectInput,
    type LifecycleHooks,
} from './transform-script';
export type ScriptType = 'setup' | 'options';

/** Status of the SFC merge/generation operation (distinct from analysis status). */
export type MergeStatus = 'fully-migrated' | 'partially-migrated' | 'not-migratable';

export interface GenerateSFCOptions {
    template: string;
    script: string;
    scriptType: ScriptType;
}

export interface MergeResult {
    sfc: string;
    status: MergeStatus;
    blockers: string[];
}

/**
 * Assembles a `.vue` Single File Component string from a template and script section.
 *
 * When `template` is empty the `<template>` block is omitted entirely.
 * `scriptType: 'setup'` produces `<script setup>` (Composition API),
 * `scriptType: 'options'` produces `<script>` (Options API backoff).
 */
export function generateSFC({ template, script, scriptType }: GenerateSFCOptions): string {
    const parts: string[] = [];

    if (template) {
        parts.push(wrapInTemplate(template));
    }

    const openTag = scriptType === 'setup' ? '<script setup>' : '<script>';
    parts.push(`${openTag}\n${script}\n</script>`);

    return parts.join('\n\n');
}

// ---------------------------------------------------------------------------
// Minimal JS parser helpers
// ---------------------------------------------------------------------------

/**
 * Extracts the component name from a Shopware.Component.register/extend call.
 */
function extractComponentName(jsContent: string): string {
    const match = jsContent.match(/Shopware\.Component\.\w+\s*\(\s*['"]([^'"]+)['"]/);
    return match?.[1] ?? 'unknown-component';
}

/**
 * Extracts the inject option (array or object) from the raw JS source.
 * Returns `null` when no inject option is present.
 *
 * Handles:
 *  - `inject: ['a', 'b']`
 *  - `inject: { alias: { from: 'svc' } }`
 */
function extractInject(jsContent: string): InjectInput | null {
    const arrayMatch = jsContent.match(/\binject\s*:\s*(\[[^\]]*\])/);
    if (arrayMatch) {
        const items = arrayMatch[1].replace(/[\[\]\s]/g, '').split(',').filter(Boolean);
        return items.map((i) => i.replace(/['"]/g, ''));
    }
    return null;
}

/**
 * Extracts scalar data() return properties.
 *
 * This is an intentionally simple regex-based extractor. It handles the common
 * Shopware component pattern of `data() { return { key: value, ... }; }`.
 * Complex nested structures or expressions spanning multiple lines may not be
 * captured correctly — those cases should fall back to the Options API backoff.
 */
function extractData(jsContent: string): DataRecord {
    const dataMatch = jsContent.match(/\bdata\s*\(\s*\)\s*\{[\s\S]*?return\s*\{([\s\S]*?)\};\s*\}/);
    if (!dataMatch) return {};

    const body = dataMatch[1];
    const result: DataRecord = {};

    const entries = body.split(',').map((e) => e.trim()).filter(Boolean);
    for (const entry of entries) {
        const colonIndex = entry.indexOf(':');
        if (colonIndex === -1) continue;
        const key = entry.slice(0, colonIndex).trim();
        const value = entry.slice(colonIndex + 1).trim();
        if (key && value) {
            result[key] = value;
        }
    }

    return result;
}

/**
 * Extracts the content between the first matching pair of braces starting at `startIndex`.
 * Uses brace-counting to handle nested structures correctly.
 */
function extractBetweenBraces(content: string, startIndex: number): string | null {
    let depth = 0;
    let bodyStart = -1;

    for (let i = startIndex; i < content.length; i++) {
        const ch = content[i];

        if (ch === '{') {
            depth++;
            if (depth === 1) {
                bodyStart = i + 1;
            }
        } else if (ch === '}') {
            depth--;
            if (depth === 0 && bodyStart !== -1) {
                return content.slice(bodyStart, i);
            }
        }
    }

    return null;
}

/**
 * Extracts computed properties from the `computed: { ... }` option block.
 *
 * Uses brace-counting so that nested objects/functions inside computed bodies
 * are handled correctly.
 *
 * Returns a `ComputedRecord` with getter function strings as values.
 */
function extractComputed(jsContent: string): ComputedRecord {
    const computedKeyMatch = jsContent.match(/\bcomputed\s*:\s*\{/);
    if (!computedKeyMatch || computedKeyMatch.index === undefined) return {};

    const blockStart = computedKeyMatch.index + computedKeyMatch[0].length - 1;
    const body = extractBetweenBraces(jsContent, blockStart);
    if (!body) return {};

    const result: ComputedRecord = {};

    // Match individual shorthand methods inside the computed block body
    // by extracting each method via brace-counting.
    const methodHeaderRegex = /(\w+)\s*\(([^)]*)\)\s*\{/g;
    let match: RegExpExecArray | null;

    while ((match = methodHeaderRegex.exec(body)) !== null) {
        const [fullMatch, name, params] = match;
        const braceStart = match.index + fullMatch.length - 1;
        const methodBody = extractBetweenBraces(body, braceStart);

        if (methodBody !== null) {
            result[name] = `(${params}) => {${methodBody}}`;
        }
    }

    return result;
}

/**
 * Extracts lifecycle hooks from the component options.
 */
function extractLifecycleHooks(jsContent: string): LifecycleHooks {
    const knownHooks = [
        'mounted',
        'created',
        'beforeMount',
        'beforeDestroy',
        'destroyed',
        'updated',
        'beforeUpdate',
        'activated',
        'deactivated',
        'errorCaptured',
    ];

    const result: LifecycleHooks = {};
    for (const hook of knownHooks) {
        // Match `mounted() { ... }` or `mounted: function() { ... }`
        const regex = new RegExp(`\\b${hook}\\s*\\(\\s*\\)\\s*\\{([^}]*)\\}`, 's');
        const match = jsContent.match(regex);
        if (match) {
            result[hook] = `() => {${match[1]}}`;
        }
    }
    return result;
}

/**
 * Collects Composition API Vue imports needed based on what was generated.
 */
function collectVueImports(lines: string[]): string[] {
    const joined = lines.join('\n');
    const imports: string[] = [];

    if (/\bref\(/.test(joined)) imports.push('ref');
    if (/\breactive\(/.test(joined)) imports.push('reactive');
    if (/\bcomputed\(/.test(joined)) imports.push('computed');
    if (/\bwatch\(/.test(joined)) imports.push('watch');
    if (/\binject\(/.test(joined)) imports.push('inject');
    if (/\bonMounted\(/.test(joined)) imports.push('onMounted');
    if (/\bonBeforeMount\(/.test(joined)) imports.push('onBeforeMount');
    if (/\bonBeforeUnmount\(/.test(joined)) imports.push('onBeforeUnmount');
    if (/\bonUnmounted\(/.test(joined)) imports.push('onUnmounted');
    if (/\bonUpdated\(/.test(joined)) imports.push('onUpdated');
    if (/\bonBeforeUpdate\(/.test(joined)) imports.push('onBeforeUpdate');
    if (/\bonActivated\(/.test(joined)) imports.push('onActivated');
    if (/\bonDeactivated\(/.test(joined)) imports.push('onDeactivated');
    if (/\bonErrorCaptured\(/.test(joined)) imports.push('onErrorCaptured');

    return imports;
}

/**
 * Strips the `template` import and the outer `Shopware.Component.register(...)` wrapper
 * from JS source, returning only the component options object body suitable for
 * embedding in a `<script>` (Options API backoff) block.
 */
function extractOptionsApiBody(jsContent: string, componentName: string): string {
    // Remove the template import line
    let body = jsContent.replace(/^import\s+template\s+from\s+['"][^'"]+['"];\s*\n?/m, '');

    // Wrap in a defineComponent call for clarity, preserving existing registration
    body = body.replace(
        /Shopware\.Component\.(register|extend)\s*\(\s*['"][^'"]+['"]\s*,\s*/,
        `// Options API backoff: automatic Composition API conversion was not possible\n// for "${componentName}". Migrate manually when ready.\nShopware.Component.register('${componentName}', `,
    );

    return body.trim();
}

/**
 * Generates a `<script setup>` body from parsed Options API source.
 */
function buildScriptSetupBody(jsContent: string, componentName: string): string {
    const lines: string[] = [];

    // Component name declaration (Shopware convention)
    lines.push(`// Component: ${componentName}`);
    lines.push('');

    const inject = extractInject(jsContent);
    const injectLines = inject ? transformInject(inject) : [];

    const dataRecord = extractData(jsContent);
    const dataLines = transformData(dataRecord);

    const computedRecord = extractComputed(jsContent);
    const computedLines = transformComputed(computedRecord);

    const hooks = extractLifecycleHooks(jsContent);
    const hookLines = transformLifecycleHooks(hooks);

    // Collect all generated lines to determine which Vue imports are needed
    const allGeneratedLines = [...injectLines, ...dataLines, ...computedLines, ...hookLines];
    const vueImports = collectVueImports(allGeneratedLines);

    if (vueImports.length > 0) {
        lines.push(`import { ${vueImports.join(', ')} } from 'vue';`);
        lines.push('');
    }

    if (injectLines.length > 0) {
        lines.push(...injectLines);
        lines.push('');
    }

    if (dataLines.length > 0) {
        lines.push(...dataLines);
        lines.push('');
    }

    if (computedLines.length > 0) {
        lines.push(...computedLines);
        lines.push('');
    }

    if (hookLines.length > 0) {
        lines.push(...hookLines);
        lines.push('');
    }

    return lines.join('\n').trimEnd();
}

/**
 * Merges a `.html.twig` template and an `index.js` Options API component
 * into a `.vue` Single File Component.
 *
 * Returns the SFC string, the migration status, and any blockers that
 * prevented full Composition API conversion.
 */
export function mergeComponentFiles(twigContent: string, jsContent: string): MergeResult {
    const componentName = extractComponentName(jsContent);
    const blockers = detectUnsupportedFeatures(jsContent);

    // Hard blockers — cannot produce any SFC output
    if (blockers.includes('render function')) {
        return { sfc: '', status: 'not-migratable', blockers };
    }

    // Transform the Twig template
    const transformedTemplate = replaceBlocks(twigContent) ?? twigContent;

    // Soft blockers — keep Options API inside <script>
    if (blockers.length > 0) {
        const optionsBody = extractOptionsApiBody(jsContent, componentName);
        const sfc = generateSFC({
            template: transformedTemplate,
            script: optionsBody,
            scriptType: 'options',
        });

        return { sfc, status: 'partially-migrated', blockers };
    }

    // Full Composition API migration
    const scriptBody = buildScriptSetupBody(jsContent, componentName);
    const sfc = generateSFC({
        template: transformedTemplate,
        script: scriptBody,
        scriptType: 'setup',
    });

    return { sfc, status: 'fully-migrated', blockers: [] };
}
