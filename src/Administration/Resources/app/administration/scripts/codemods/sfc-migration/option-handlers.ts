/**
 * @sw-package framework
 */

/**
 * One handler per supported top-level component option. `classifyOptions()` walks the options
 * object and dispatches into OPTION_HANDLERS; everything the registry does not claim falls into the
 * tier from `OPTION_TIERS` (tables.ts) or, failing that, an unknown-option TODO. Supporting a new
 * option means adding one handler entry — the loop, the rewrite pass, and the assembly stay
 * untouched.
 *
 * Handlers only collect: they record plain descriptors and never render, because rendering has to
 * read the MagicString after the `this` rewrite pass. `renderMember()` / `renderWatcher()` turn
 * those descriptors into source, and transform-script.ts calls them once the rewrite is done.
 *
 * A handler that does not recognize an option's shape reports its own TODO — there is no
 * fall-through return value.
 */

import { traverseFast } from '@babel/types';
import type * as t from '@babel/types';
import { LIFECYCLE_HOOKS, OPTION_TIERS, sourceKeyed } from './tables';
import { type Ctx, type FnLike, IDENTIFIER, arrowText, asFunction, keyName, raw, report, snip } from './ast';

/** A collected `computed` / `methods` member, rendered by `renderMember()` after the rewrite pass. */
type CollectedMember =
    | { kind: 'computed'; name: string; fn: FnLike }
    | { kind: 'method'; name: string; fn: FnLike }
    | { kind: 'writable-computed'; name: string; getFn: FnLike; setFn: FnLike };

/** A collected `watch` entry, rendered by `renderWatcher()` after the rewrite pass. */
type CollectedWatcher = { source: string; handler: FnLike | string; options: string };

/** Everything the classification pass collects for the later rewrite and render steps. */
type Collected = {
    propsNode: t.ObjectExpression | t.ArrayExpression | null;
    propNames: Set<string>;
    emitsNode: t.Node | null;
    inheritAttrs: string | null;
    injects: string[];
    dataEntries: { name: string; valueNode: t.Node }[];
    computeds: CollectedMember[];
    methods: CollectedMember[];
    watchEntries: { key: string; prop: t.ObjectProperty | t.ObjectMethod }[];
    hooks: { hook: string; fn: FnLike }[];
    rewriteFns: FnLike[];
    foreignNodes: t.Node[];
    createdFn: FnLike | null;
};

type OptionHandler = (prop: t.ObjectMethod | t.ObjectProperty, ctx: Ctx, collected: Collected) => void;

function containsThisAccess(node: t.Node): boolean {
    let found = false;

    traverseFast(node, (descendant) => {
        found = found || descendant.type === 'ThisExpression';
    });

    return found;
}

function memberAccess(base: string, segments: string[]): string {
    return segments.reduce(
        (value, segment) => (IDENTIFIER.test(segment) ? `${value}.${segment}` : `${value}[${JSON.stringify(segment)}]`),
        base,
    );
}

/**
 * An option a handler claims but whose shape it does not recognize. Indistinguishable from an option
 * no handler claims at all, so it is reported the same way.
 */
function unknownOption(ctx: Ctx, name: string, prop: t.ObjectMethod | t.ObjectProperty): void {
    report(ctx, 'todo', `unknown option '${name}'`, prop);
}

function createCollected(): Collected {
    return {
        propsNode: null,
        propNames: new Set(),
        emitsNode: null,
        inheritAttrs: null,
        injects: [],
        dataEntries: [],
        computeds: [],
        methods: [],
        watchEntries: [],
        hooks: [],
        rewriteFns: [],
        foreignNodes: [],
        createdFn: null,
    };
}

/** Shared collector for `computed` and `methods` entries (incl. the writable-computed form). */
function collectFnMember(
    prop: t.ObjectMethod | t.ObjectProperty | t.SpreadElement,
    ctx: Ctx,
    collected: Collected,
    bucket: CollectedMember[],
    kind: 'computed' | 'method',
    optionLabel: string,
): void {
    if (prop.type === 'SpreadElement') {
        report(ctx, 'todo', `spread in ${optionLabel}`, prop);
        return;
    }

    const name = keyName(prop);

    if (!name || !IDENTIFIER.test(name)) {
        report(ctx, 'todo', `${optionLabel} entry with unsupported key`, prop);
        return;
    }

    const fn = asFunction(prop);

    // Writable computed: `foo: { get() {}, set(value) {} }`.
    if (!fn && kind === 'computed' && prop.type === 'ObjectProperty' && prop.value.type === 'ObjectExpression') {
        const members = prop.value.properties;
        const getter = members.find((member) => member.type !== 'SpreadElement' && keyName(member) === 'get') as
            | t.ObjectMethod
            | t.ObjectProperty
            | undefined;
        const setter = members.find((member) => member.type !== 'SpreadElement' && keyName(member) === 'set') as
            | t.ObjectMethod
            | t.ObjectProperty
            | undefined;
        const getFn = getter ? asFunction(getter) : null;
        const setFn = setter ? asFunction(setter) : null;

        if (getFn && setFn && members.length === 2) {
            collected.rewriteFns.push(getFn, setFn);
            ctx.bindings.set(name, 'computed');
            bucket.push({ kind: 'writable-computed', name, getFn, setFn });
            return;
        }

        report(ctx, 'todo', `unsupported ${optionLabel} entry '${name}'`, prop);
        return;
    }

    if (!fn) {
        report(ctx, 'todo', `${optionLabel} entry '${name}' is not a plain function`, prop);
        return;
    }

    collected.rewriteFns.push(fn);
    ctx.bindings.set(name, kind);
    bucket.push({ kind, name, fn });
}

function handleLifecycleHook(prop: t.ObjectMethod | t.ObjectProperty, ctx: Ctx, collected: Collected): void {
    const name = keyName(prop) as string;
    const fn = asFunction(prop);

    if (fn) {
        collected.hooks.push({ hook: LIFECYCLE_HOOKS[name], fn });
    } else {
        report(ctx, 'todo', `${name} is not a plain function`, prop);
    }
}

const OPTION_HANDLERS: Record<string, OptionHandler> = sourceKeyed<OptionHandler>({
    // The template option is replaced by the SFC's own <template> section.
    template: () => {},

    name: (prop, ctx) => {
        if (prop.type === 'ObjectProperty' && prop.value.type === 'StringLiteral') {
            if (prop.value.value !== ctx.componentName) {
                report(ctx, 'skip', `name '${prop.value.value}' does not match the directory name`);
            }
        } else {
            report(ctx, 'skip', 'non-literal component name');
        }
    },

    inheritAttrs: (prop, ctx, collected) => {
        if (prop.type === 'ObjectProperty' && prop.value.type === 'BooleanLiteral') {
            collected.inheritAttrs = String(prop.value.value);
        } else {
            report(ctx, 'todo', 'non-literal inheritAttrs', prop);
        }
    },

    props: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            unknownOption(ctx, 'props', prop);
            return;
        }

        if (prop.value.type === 'ObjectExpression' || prop.value.type === 'ArrayExpression') {
            collected.propsNode = prop.value;

            if (prop.value.type === 'ObjectExpression') {
                for (const propEntry of prop.value.properties) {
                    const propName = propEntry.type === 'SpreadElement' ? null : keyName(propEntry);

                    if (propName) {
                        collected.propNames.add(propName);
                        ctx.bindings.set(propName, 'prop');
                    }
                }
            } else {
                for (const element of prop.value.elements) {
                    if (element && element.type === 'StringLiteral') {
                        collected.propNames.add(element.value);
                        ctx.bindings.set(element.value, 'prop');
                    }
                }
            }

            collected.foreignNodes.push(prop.value);
        } else {
            report(ctx, 'todo', 'unsupported props declaration', prop);
        }
    },

    emits: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            unknownOption(ctx, 'emits', prop);
            return;
        }

        collected.emitsNode = prop.value;
        collected.foreignNodes.push(prop.value);
    },

    inject: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            unknownOption(ctx, 'inject', prop);
            return;
        }

        const elements = prop.value.type === 'ArrayExpression' ? prop.value.elements : null;
        const names = elements?.map((element) =>
            element && element.type === 'StringLiteral' && IDENTIFIER.test(element.value) ? element.value : null,
        );

        if (names && names.every((injectName): injectName is string => injectName !== null)) {
            // Vue's Options API unwraps injected refs on reads and forwards writes to `.value`.
            // The generated binding cannot prove whether a provider returns a primitive, reactive
            // object, or Ref, so keep the draft but make the result partial until the runtime
            // representation is deliberately normalized and covered.
            report(ctx, 'todo', 'array inject declaration requires runtime ref-unwrapping verification', prop);

            for (const injectName of names) {
                collected.injects.push(injectName);
                ctx.bindings.set(injectName, 'inject');
            }
        } else {
            report(ctx, 'todo', 'unsupported inject declaration (only the array form is migrated)', prop);
        }
    },

    data: (prop, ctx, collected) => {
        const fn = asFunction(prop);

        if (fn && fn.params.length > 0) {
            report(ctx, 'todo', 'parameterized data() requires an explicit vm mapping', prop);
            return;
        }

        const returned =
            fn && fn.body.type === 'BlockStatement'
                ? fn.body.body.length === 1 &&
                  fn.body.body[0].type === 'ReturnStatement' &&
                  fn.body.body[0].argument?.type === 'ObjectExpression'
                    ? fn.body.body[0].argument
                    : null
                : fn && fn.body.type === 'ObjectExpression'
                  ? fn.body
                  : null;

        if (!returned) {
            report(ctx, 'todo', 'data() does not directly return an object literal', prop);
            return;
        }

        for (const entry of returned.properties) {
            const entryName = entry.type === 'SpreadElement' ? null : keyName(entry);

            if (
                entry.type !== 'ObjectProperty' ||
                !entryName ||
                !IDENTIFIER.test(entryName) ||
                (entry.shorthand && entry.value.type === 'Identifier' && entry.value.name === entryName)
            ) {
                report(ctx, 'todo', 'unsupported data() entry', entry);
                continue;
            }

            if (containsThisAccess(entry.value)) {
                report(ctx, 'todo', 'data() initializer reads component this and is not runtime-equivalent', entry);
            }

            collected.dataEntries.push({ name: entryName, valueNode: entry.value });
            ctx.bindings.set(entryName, 'data');
        }
    },

    computed: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            unknownOption(ctx, 'computed', prop);
            return;
        }

        for (const entry of prop.value.properties) {
            collectFnMember(entry, ctx, collected, collected.computeds, 'computed', 'computed');
        }
    },

    methods: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            unknownOption(ctx, 'methods', prop);
            return;
        }

        for (const entry of prop.value.properties) {
            collectFnMember(entry, ctx, collected, collected.methods, 'method', 'methods');
        }
    },

    watch: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            unknownOption(ctx, 'watch', prop);
            return;
        }

        for (const entry of prop.value.properties) {
            const watchKey = entry.type === 'SpreadElement' ? null : keyName(entry);

            if (!watchKey || entry.type === 'SpreadElement') {
                report(ctx, 'todo', 'unsupported watch entry', entry);
                continue;
            }

            collected.watchEntries.push({ key: watchKey, prop: entry });
        }
    },

    created: (prop, ctx, collected) => {
        collected.createdFn = asFunction(prop);

        if (!collected.createdFn) {
            report(ctx, 'todo', 'created is not a plain function', prop);
        }
    },
});

/** Classifies every top-level option into the collected state or a report. */
function classifyOptions(ctx: Ctx, options: t.ObjectExpression): Collected {
    const collected = createCollected();

    for (const prop of options.properties) {
        if (prop.type === 'SpreadElement') {
            report(ctx, 'skip', 'root option spread');
            continue;
        }

        const name = keyName(prop);

        if (!name) {
            report(ctx, 'skip', 'dynamic option key');
            continue;
        }

        const tier = OPTION_TIERS[name];

        if (tier === 'skip') {
            report(ctx, 'skip', name);
            continue;
        }

        if (tier === 'todo') {
            report(ctx, 'todo', `convert '${name}' manually`, prop);
            continue;
        }

        const handler = OPTION_HANDLERS[name] ?? (LIFECYCLE_HOOKS[name] ? handleLifecycleHook : null);

        if (handler) {
            handler(prop, ctx, collected);
            continue;
        }

        unknownOption(ctx, name, prop);
    }

    return collected;
}

/**
 * Collects the `watch(...)` descriptors. Runs after classification because the sources need the
 * complete binding map, and before the rewrite pass because it contributes handlers to it.
 */
function collectWatchers(ctx: Ctx, collected: Collected): CollectedWatcher[] {
    const watchers: CollectedWatcher[] = [];

    for (const { key, prop } of collected.watchEntries) {
        const segments = key.split('.');
        const head = segments[0];
        const rest = segments.slice(1);
        const headKind = ctx.bindings.get(head);
        let sourceText: string | null = null;

        if (head === '$route') {
            if (rest.length === 0) {
                report(ctx, 'todo', `watch source '${key}' has exact $route semantics that need runtime verification`, prop);
                continue;
            }

            ctx.helpers.add('route');
            sourceText = `() => ${memberAccess('route', rest)}`;
        } else if (headKind === 'prop') {
            ctx.helpers.add('props');
            sourceText = `() => ${memberAccess('props', segments)}`;
        } else if (headKind === 'data' || headKind === 'computed') {
            sourceText = rest.length > 0 ? `() => ${memberAccess(`${head}.value`, rest)}` : head;
        }

        if (!sourceText) {
            report(ctx, 'todo', `watch source '${key}' is not a known prop, data or computed`, prop);
            continue;
        }

        let handlerFn = asFunction(prop);
        const watchOptions: string[] = [];
        let handlerText: string | null = null;
        let supported = true;

        if (!handlerFn && prop.type === 'ObjectProperty' && prop.value.type === 'ObjectExpression') {
            for (const optionEntry of prop.value.properties) {
                const optionName = optionEntry.type === 'SpreadElement' ? null : keyName(optionEntry);

                if (optionName === 'handler') {
                    handlerFn = asFunction(optionEntry as t.ObjectMethod | t.ObjectProperty);

                    if (!handlerFn && optionEntry.type === 'ObjectProperty' && optionEntry.value.type === 'StringLiteral') {
                        handlerText = optionEntry.value.value;
                    }
                } else if (
                    (optionName === 'deep' || optionName === 'immediate' || optionName === 'flush') &&
                    optionEntry.type === 'ObjectProperty' &&
                    (optionEntry.value.type === 'BooleanLiteral' || optionEntry.value.type === 'StringLiteral')
                ) {
                    watchOptions.push(`${optionName}: ${raw(ctx, optionEntry.value)}`);
                } else {
                    supported = false;
                }
            }
        } else if (!handlerFn && prop.type === 'ObjectProperty' && prop.value.type === 'StringLiteral') {
            handlerText = prop.value.value;
        }

        if (handlerFn) {
            collected.rewriteFns.push(handlerFn);
        } else if (handlerText && ctx.bindings.get(handlerText) !== 'method') {
            supported = false;
        }

        if (!supported || (!handlerFn && !handlerText)) {
            report(ctx, 'todo', `unsupported watch entry '${key}'`, prop);
            continue;
        }

        watchers.push({
            source: sourceText,
            handler: handlerFn ?? (handlerText as string),
            options: watchOptions.length > 0 ? `, { ${watchOptions.join(', ')} }` : '',
        });
    }

    return watchers;
}

/** Render phase — only valid once the `this` rewrite has run over the MagicString. */
function renderMember(ctx: Ctx, member: CollectedMember): string {
    if (member.kind === 'writable-computed') {
        return (
            `const ${member.name} = computed({\n` +
            `get: () => ${snip(ctx, member.getFn.body)},\n` +
            `set: ${arrowText(ctx, member.setFn)},\n` +
            `});`
        );
    }

    if (member.kind === 'computed') {
        return `const ${member.name} = computed(${arrowText(ctx, member.fn)});`;
    }

    return `const ${member.name} = ${arrowText(ctx, member.fn)};`;
}

/** Render phase — only valid once the `this` rewrite has run over the MagicString. */
function renderWatcher(ctx: Ctx, watcher: CollectedWatcher): string {
    const handler = typeof watcher.handler === 'string' ? watcher.handler : arrowText(ctx, watcher.handler);

    return `watch(${watcher.source}, ${handler}${watcher.options});`;
}

export {
    type Collected,
    type CollectedMember,
    type CollectedWatcher,
    type OptionHandler,
    OPTION_HANDLERS,
    classifyOptions,
    collectWatchers,
    renderMember,
    renderWatcher,
};
