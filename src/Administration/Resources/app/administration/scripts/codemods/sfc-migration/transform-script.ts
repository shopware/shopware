/**
 * Options API → Composition API converters.
 *
 * Each function accepts a parsed representation of an Options API section
 * and returns an array of Composition API code lines ready to be embedded
 * in a `<script setup>` block.
 *
 * "Parsed representation" intentionally uses plain record types so that
 * callers (e.g. generate-sfc.ts) can extract values from the AST once and
 * pass them here without coupling this module to a specific AST library.
 */

/** Value string from data() return object, keyed by property name. */
export type DataRecord = Record<string, string>;

/** Either a getter function string, or `{ get, set }` for writable computed. */
export type ComputedValue = string | { get: string; set: string };
export type ComputedRecord = Record<string, ComputedValue>;

/** Handler function string, or `{ handler, deep?, immediate? }` for advanced watchers. */
export type WatchValue =
    | string
    | { handler: string; deep?: boolean; immediate?: boolean };
export type WatchRecord = Record<string, WatchValue>;

/** Array form or object form (with `from` and optional `default`) for inject. */
export type InjectInput =
    | string[]
    | Record<string, { from: string; default?: string }>;

export type LifecycleHooks = Record<string, string>;

const LIFECYCLE_MAP: Record<string, string> = {
    mounted: 'onMounted',
    // `created` has no direct Composition API equivalent; onMounted is the closest.
    created: 'onMounted',
    beforeMount: 'onBeforeMount',
    beforeDestroy: 'onBeforeUnmount',
    destroyed: 'onUnmounted',
    updated: 'onUpdated',
    beforeUpdate: 'onBeforeUpdate',
    activated: 'onActivated',
    deactivated: 'onDeactivated',
    errorCaptured: 'onErrorCaptured',
};

/**
 * Replaces `this.xxx` property accesses with the bare variable name.
 * `this.$emit`, `this.$route`, etc. are preserved because they have
 * Composition API equivalents that callers should handle separately.
 */
function substituteThis(code: string): string {
    // Preserve this.$xxx (Vue instance helpers) — only strip plain this.xxx
    return code.replace(/\bthis\.(?!\$)(\w+)/g, '$1');
}

/**
 * Detects and warns about `this.$super()` calls that cannot be automatically
 * converted. The call is preserved with a TODO comment so the developer is
 * informed during review.
 */
function handleSuperCalls(code: string, methodName: string): string {
    if (!code.includes('this.$super')) {
        return code;
    }

    console.warn(
        `[sfc-migration] Method "${methodName}" contains a this.$super() call. ` +
            'Automatic migration of $super is not supported. ' +
            'Please migrate this manually after conversion.',
    );

    return code.replace(
        /this\.\$super\(/g,
        '/* TODO: migrate $super */ this.$super(',
    );
}

/** Detects whether a string value looks like an object literal. */
function isObjectLiteral(value: string): boolean {
    const trimmed = value.trim();
    return trimmed.startsWith('{') && trimmed.endsWith('}');
}

/**
 * Converts the return value of `data()` to `ref()` / `reactive()` declarations.
 *
 * Objects become `reactive()`; everything else becomes `ref()`.
 */
export function transformData(data: DataRecord): string[] {
    return Object.entries(data).map(([key, value]) => {
        const trimmed = value.trim();
        if (isObjectLiteral(trimmed)) {
            return `const ${key} = reactive(${trimmed});`;
        }
        return `const ${key} = ref(${trimmed});`;
    });
}

/**
 * Converts `computed` option properties to Composition API `computed()` declarations.
 */
export function transformComputed(computed: ComputedRecord): string[] {
    return Object.entries(computed).map(([key, value]) => {
        if (typeof value === 'object' && 'get' in value) {
            return [
                `const ${key} = computed({`,
                `    get: ${value.get},`,
                `    set: ${value.set},`,
                `});`,
            ].join('\n');
        }
        return `const ${key} = computed(${value as string});`;
    });
}

/**
 * Converts `methods` option properties to `const` arrow-function declarations.
 *
 * `this.xxx` references are rewritten to bare variable names.
 * `this.$super()` calls are preserved with a TODO comment and a console warning.
 */
export function transformMethods(methods: Record<string, string>): string[] {
    return Object.entries(methods).map(([name, fn]) => {
        let body = handleSuperCalls(fn, name);
        body = substituteThis(body);

        const isAsync = body.trim().startsWith('async');
        const prefix = isAsync ? 'const ' : 'const ';
        return `${prefix}${name} = ${body};`;
    });
}

/**
 * Converts `watch` option entries to Composition API `watch()` calls.
 *
 * Dot-notation path watchers (e.g. `'product.name'`) cannot be safely converted
 * and are skipped with a warning.
 */
export function transformWatch(watch: WatchRecord): string[] {
    const lines: string[] = [];

    for (const [key, value] of Object.entries(watch)) {
        if (key.includes('.')) {
            console.warn(
                `[sfc-migration] Watcher "${key}" uses a dot-notation path which cannot ` +
                    'be automatically converted. Please migrate this watcher manually.',
            );
            continue;
        }

        if (typeof value === 'string') {
            lines.push(`watch(() => ${key}, ${value});`);
        } else {
            const { handler, ...options } = value;
            const optionEntries = Object.entries(options)
                .map(([k, v]) => `${k}: ${String(v)}`)
                .join(', ');

            if (optionEntries) {
                lines.push(`watch(() => ${key}, ${handler}, { ${optionEntries} });`);
            } else {
                lines.push(`watch(() => ${key}, ${handler});`);
            }
        }
    }

    return lines;
}

/**
 * Converts `inject` option (array or object form) to Composition API `inject()` calls.
 */
export function transformInject(inject: InjectInput): string[] {
    if (Array.isArray(inject)) {
        return inject.map((key) => `const ${key} = inject('${key}');`);
    }

    return Object.entries(inject).map(([localName, config]) => {
        const from = config.from;
        const defaultValue = config.default;

        if (defaultValue !== undefined) {
            // Strip surrounding quotes from the default string value if present
            const rawDefault = defaultValue.trim().replace(/^'(.*)'$/, '$1');
            return `const ${localName} = inject('${from}', '${rawDefault}');`;
        }

        return `const ${localName} = inject('${from}');`;
    });
}

/**
 * Converts Options API lifecycle hooks to their Composition API equivalents.
 *
 * `created` has no direct equivalent; it maps to `onMounted` with a comment
 * noting the semantic difference so the developer can review.
 */
export function transformLifecycleHooks(hooks: LifecycleHooks): string[] {
    return Object.entries(hooks).flatMap(([hookName, body]) => {
        const compositionHook = LIFECYCLE_MAP[hookName];
        if (!compositionHook) {
            return [];
        }

        if (hookName === 'created') {
            return [
                `// NOTE: \`created\` has no direct Composition API equivalent; mapped to onMounted`,
                `${compositionHook}(${body});`,
            ];
        }

        return [`${compositionHook}(${body});`];
    });
}

/**
 * Scans raw JS source for Options API features that cannot be automatically
 * converted to the Composition API.
 *
 * Returns an array of blocker names. An empty array means the component is
 * a candidate for full Composition API migration.
 */
export function detectUnsupportedFeatures(jsContent: string): string[] {
    const blockers: string[] = [];

    if (/\bmixins\s*:/.test(jsContent)) {
        blockers.push('mixins');
    }

    if (/\brender\s*\(/.test(jsContent)) {
        blockers.push('render function');
    }

    // Options API `extends` via Shopware.Component.extend() — different from
    // template `{% extends %}` which is a Twig concept.
    if (/Shopware\.Component\.extend\s*\(/.test(jsContent)) {
        blockers.push('extends');
    }

    return blockers;
}
