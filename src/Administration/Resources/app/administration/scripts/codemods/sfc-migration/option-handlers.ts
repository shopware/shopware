/**
 * @sw-package framework
 */

/**
 * One handler per supported top-level component option. `classifyOptions()` walks the options
 * object and dispatches into OPTION_HANDLERS; everything the registry does not claim falls into
 * the TODO/SKIP tiers from tables.ts. Supporting a new option means adding one handler entry —
 * the loop, the rewrite pass, and the assembly stay untouched.
 *
 * A handler returns `true` when it consumed the option (including recording its own TODO) and
 * `false` when the option's shape is not one it recognizes, which routes the option into the
 * generic TODO fallback.
 */

import type * as t from '@babel/types';
import { LIFECYCLE_HOOKS, SKIP_OPTIONS, TODO_OPTIONS, type MemberKind } from './tables';
import { type Ctx, type FnLike, IDENTIFIER, arrowText, asFunction, keyName, raw, snip, todo } from './ast';

type NamedText = { name: string; text: () => string };

/** Everything the classification pass collects for the later rewrite and assembly steps. */
type Collected = {
    propsNode: t.ObjectExpression | t.ArrayExpression | null;
    propNames: Set<string>;
    emitsNode: t.Node | null;
    inheritAttrs: string | null;
    injects: string[];
    dataEntries: { name: string; valueNode: t.Node }[];
    computeds: NamedText[];
    methods: NamedText[];
    watchEntries: { key: string; prop: t.ObjectProperty | t.ObjectMethod }[];
    hooks: { hook: string; fn: FnLike }[];
    rewriteFns: FnLike[];
    foreignNodes: t.Node[];
    createdFn: FnLike | null;
};

type OptionHandler = (prop: t.ObjectMethod | t.ObjectProperty, ctx: Ctx, collected: Collected) => boolean;

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
    bucket: NamedText[],
    kind: MemberKind,
    optionLabel: string,
): void {
    if (prop.type === 'SpreadElement') {
        todo(ctx, `spread in ${optionLabel}`, raw(ctx, prop));
        return;
    }

    const name = keyName(prop);

    if (!name || !IDENTIFIER.test(name)) {
        todo(ctx, `${optionLabel} entry with unsupported key`, raw(ctx, prop));
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
            bucket.push({
                name,
                text: () =>
                    `const ${name} = computed({\n` +
                    `get: () => ${snip(ctx, getFn.body)},\n` +
                    `set: ${arrowText(ctx, setFn)},\n` +
                    `});`,
            });
            return;
        }

        todo(ctx, `unsupported ${optionLabel} entry '${name}'`, raw(ctx, prop));
        return;
    }

    if (!fn) {
        todo(ctx, `${optionLabel} entry '${name}' is not a plain function`, raw(ctx, prop));
        return;
    }

    collected.rewriteFns.push(fn);
    ctx.bindings.set(name, kind);

    if (kind === 'computed') {
        bucket.push({ name, text: () => `const ${name} = computed(${arrowText(ctx, fn)});` });
    } else {
        bucket.push({ name, text: () => `const ${name} = ${arrowText(ctx, fn)};` });
    }
}

function handleLifecycleHook(prop: t.ObjectMethod | t.ObjectProperty, ctx: Ctx, collected: Collected): boolean {
    const name = keyName(prop) as string;
    const fn = asFunction(prop);

    if (fn) {
        collected.hooks.push({ hook: LIFECYCLE_HOOKS[name], fn });
    } else {
        todo(ctx, `${name} is not a plain function`, raw(ctx, prop));
    }

    return true;
}

const OPTION_HANDLERS: Record<string, OptionHandler> = {
    // The template option is replaced by the SFC's own <template> section.
    template: () => true,

    name: (prop, ctx) => {
        if (prop.type === 'ObjectProperty' && prop.value.type === 'StringLiteral') {
            if (prop.value.value !== ctx.componentName) {
                ctx.blockers.add(`name '${prop.value.value}' does not match the directory name`);
            }
        } else {
            ctx.blockers.add('non-literal component name');
        }

        return true;
    },

    inheritAttrs: (prop, ctx, collected) => {
        if (prop.type === 'ObjectProperty' && prop.value.type === 'BooleanLiteral') {
            collected.inheritAttrs = String(prop.value.value);
        } else {
            todo(ctx, 'non-literal inheritAttrs', raw(ctx, prop));
        }

        return true;
    },

    props: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            return false;
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
            todo(ctx, 'unsupported props declaration', raw(ctx, prop));
        }

        return true;
    },

    emits: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            return false;
        }

        collected.emitsNode = prop.value;
        collected.foreignNodes.push(prop.value);

        return true;
    },

    inject: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty') {
            return false;
        }

        const elements = prop.value.type === 'ArrayExpression' ? prop.value.elements : null;
        const names = elements?.map((element) =>
            element && element.type === 'StringLiteral' && IDENTIFIER.test(element.value) ? element.value : null,
        );

        if (names && names.every((injectName): injectName is string => injectName !== null)) {
            for (const injectName of names) {
                collected.injects.push(injectName);
                ctx.bindings.set(injectName, 'inject');
            }
        } else {
            todo(ctx, 'unsupported inject declaration (only the array form is migrated)', raw(ctx, prop));
        }

        return true;
    },

    data: (prop, ctx, collected) => {
        const fn = asFunction(prop);
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
            todo(ctx, 'data() does not directly return an object literal', raw(ctx, prop));
            return true;
        }

        for (const entry of returned.properties) {
            const entryName = entry.type === 'SpreadElement' ? null : keyName(entry);

            if (
                entry.type !== 'ObjectProperty' ||
                !entryName ||
                !IDENTIFIER.test(entryName) ||
                (entry.shorthand && entry.value.type === 'Identifier' && entry.value.name === entryName)
            ) {
                todo(ctx, 'unsupported data() entry', raw(ctx, entry));
                continue;
            }

            collected.dataEntries.push({ name: entryName, valueNode: entry.value });
            ctx.bindings.set(entryName, 'data');
        }

        return true;
    },

    computed: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            return false;
        }

        for (const entry of prop.value.properties) {
            collectFnMember(entry, ctx, collected, collected.computeds, 'computed', 'computed');
        }

        return true;
    },

    methods: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            return false;
        }

        for (const entry of prop.value.properties) {
            collectFnMember(entry, ctx, collected, collected.methods, 'method', 'methods');
        }

        return true;
    },

    watch: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ObjectExpression') {
            return false;
        }

        for (const entry of prop.value.properties) {
            const watchKey = entry.type === 'SpreadElement' ? null : keyName(entry);

            if (!watchKey || entry.type === 'SpreadElement') {
                todo(ctx, 'unsupported watch entry', raw(ctx, entry));
                continue;
            }

            collected.watchEntries.push({ key: watchKey, prop: entry });
        }

        return true;
    },

    created: (prop, ctx, collected) => {
        collected.createdFn = asFunction(prop);

        if (!collected.createdFn) {
            todo(ctx, 'created is not a plain function', raw(ctx, prop));
        }

        return true;
    },
};

/** Classifies every top-level option into the collected state, the TODO list, or the blockers. */
function classifyOptions(ctx: Ctx, options: t.ObjectExpression): Collected {
    const collected = createCollected();

    for (const prop of options.properties) {
        if (prop.type === 'SpreadElement') {
            ctx.blockers.add('root option spread');
            continue;
        }

        const name = keyName(prop);

        if (!name) {
            ctx.blockers.add('dynamic option key');
            continue;
        }

        if (SKIP_OPTIONS.has(name)) {
            ctx.blockers.add(name);
            continue;
        }

        const handler = OPTION_HANDLERS[name] ?? (LIFECYCLE_HOOKS[name] ? handleLifecycleHook : null);

        if (handler && handler(prop, ctx, collected)) {
            continue;
        }

        todo(ctx, TODO_OPTIONS.has(name) ? `convert '${name}' manually` : `unknown option '${name}'`, raw(ctx, prop));
    }

    return collected;
}

/**
 * Builds the `watch(...)` statements. Runs after classification because the sources need the
 * complete binding map; the returned thunks render at assembly time, after the rewrite pass.
 */
function buildWatchers(ctx: Ctx, collected: Collected): (() => string)[] {
    const watchers: (() => string)[] = [];

    for (const { key, prop } of collected.watchEntries) {
        const segments = key.split('.');
        const head = segments[0];
        const rest = segments.slice(1).join('.');
        const headKind = ctx.bindings.get(head);
        let sourceText: string | null = null;

        if (head === '$route') {
            ctx.helpers.add('route');
            sourceText = rest ? `() => route.${rest}` : 'route';
        } else if (headKind === 'prop') {
            ctx.helpers.add('props');
            sourceText = `() => props.${key}`;
        } else if (headKind === 'data' || headKind === 'computed') {
            sourceText = rest ? `() => ${head}.value.${rest}` : head;
        }

        if (!sourceText) {
            todo(ctx, `watch source '${key}' is not a known prop, data or computed`, raw(ctx, prop));
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
            todo(ctx, `unsupported watch entry '${key}'`, raw(ctx, prop));
            continue;
        }

        const optionsText = watchOptions.length > 0 ? `, { ${watchOptions.join(', ')} }` : '';
        const finalHandlerFn = handlerFn;
        const finalHandlerText = handlerText;
        const finalSourceText = sourceText;

        watchers.push(
            () =>
                `watch(${finalSourceText}, ${finalHandlerFn ? arrowText(ctx, finalHandlerFn) : finalHandlerText}${optionsText});`,
        );
    }

    return watchers;
}

export { type Collected, type OptionHandler, OPTION_HANDLERS, classifyOptions, buildWatchers };
