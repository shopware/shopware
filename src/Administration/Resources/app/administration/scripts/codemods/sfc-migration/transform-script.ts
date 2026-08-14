/**
 * @sw-package framework
 */

/**
 * Converts an Options API component script into a native setup `<script setup>` body ending in
 * `swDefinePublic({ ... })`.
 *
 * Strategy: parse once with @babel/parser for positions, rewrite every component-bound `this.*`
 * reference in place via magic-string, then assemble the new script from verbatim source slices.
 * Indentation is left to prettier; correctness is left to the validation gate (validate.ts).
 *
 * Extension points are data tables, not code paths: SKIP_OPTIONS / TODO_OPTIONS decide an option's
 * tier, INSTANCE_PROPS drives `this.$xyz` rewrites, LIFECYCLE_HOOKS maps hook names. Anything a
 * table does not claim becomes a `// TODO(sfc-migration)` comment (partial migration) or a blocker
 * (component skipped) — never a silent guess.
 */

import { parse } from '@babel/parser';
import { VISITOR_KEYS } from '@babel/types';
import type * as t from '@babel/types';
import MagicString from 'magic-string';

type ScriptResult = {
    script: string | null;
    todos: string[];
    blockers: string[];
};

type MemberKind = 'prop' | 'data' | 'computed' | 'method' | 'inject';

type HelperName = 't' | 'router' | 'route' | 'emit' | 'props' | 'slots' | 'attrs' | 'nextTick';

type TodoEntry = { reason: string; code?: string };

type FnLike = {
    fnNode: t.ObjectMethod | t.FunctionExpression | t.ArrowFunctionExpression;
    params: t.Node[];
    body: t.BlockStatement | t.Expression;
    async: boolean;
};

// Options whose presence makes the whole component non-migratable.
const SKIP_OPTIONS = new Set([
    'mixins',
    'render',
    'renderError',
]);

// Options that are kept as TODO comments; everything unknown lands here too (see classify()).
const TODO_OPTIONS = new Set([
    'metaInfo',
    'shortcuts',
    'provide',
    'filters',
    'compatConfig',
    'components',
    'directives',
    'validations',
    'model',
    'expose',
    'setup',
    'i18n',
    'beforeCreate',
    'beforeRouteEnter',
    'beforeRouteLeave',
    'beforeRouteUpdate',
]);

// `this.$super` / `this.$parent` are structural — the component is skipped entirely.
const SKIP_INSTANCE_PROPS = new Set([
    '$super',
    '$parent',
]);

// `this.$xyz` → replacement identifier; `helper` requests the matching setup declaration/import.
const INSTANCE_PROPS: Record<string, { replacement: string; helper?: HelperName }> = {
    $t: { replacement: 't', helper: 't' },
    $tc: { replacement: 't', helper: 't' },
    $emit: { replacement: 'emit', helper: 'emit' },
    $props: { replacement: 'props', helper: 'props' },
    $router: { replacement: 'router', helper: 'router' },
    $route: { replacement: 'route', helper: 'route' },
    $nextTick: { replacement: 'nextTick', helper: 'nextTick' },
    $slots: { replacement: 'slots', helper: 'slots' },
    $attrs: { replacement: 'attrs', helper: 'attrs' },
};

const HELPER_SETUP_LINES: Record<HelperName, string | null> = {
    t: 'const { t } = useI18n();',
    router: 'const router = useRouter();',
    route: 'const route = useRoute();',
    slots: 'const slots = useSlots();',
    attrs: 'const attrs = useAttrs();',
    nextTick: null,
    emit: null,
    props: null,
};

const LIFECYCLE_HOOKS: Record<string, string> = {
    beforeMount: 'onBeforeMount',
    mounted: 'onMounted',
    beforeUpdate: 'onBeforeUpdate',
    updated: 'onUpdated',
    beforeUnmount: 'onBeforeUnmount',
    beforeDestroy: 'onBeforeUnmount',
    unmounted: 'onUnmounted',
    destroyed: 'onUnmounted',
    activated: 'onActivated',
    deactivated: 'onDeactivated',
};

// Top-level binding names the Shopware setup transform reserves or that would shadow a generated
// helper. Producing one of these is a hard skip.
const RESERVED_BINDING = /^(__swSetup|__swOverride$|__proto__$|Shopware$)/;
const GENERATED_HELPER_NAMES = new Set([
    't',
    'router',
    'route',
    'emit',
    'props',
    'slots',
    'attrs',
    'nextTick',
]);

const IDENTIFIER = /^[A-Za-z_$][A-Za-z0-9_$]*$/;

type Ctx = {
    source: string;
    ms: MagicString;
    bindings: Map<string, MemberKind>;
    templateRefs: Set<string>;
    helpers: Set<HelperName>;
    inferredEmits: string[];
    todos: TodoEntry[];
    blockers: Set<string>;
};

function snip(ctx: Ctx, node: t.Node): string {
    return ctx.ms.snip(node.start as number, node.end as number).toString();
}

function raw(ctx: Ctx, node: t.Node): string {
    return ctx.source.slice(node.start as number, node.end as number);
}

function overwrite(ctx: Ctx, node: t.Node, text: string): void {
    ctx.ms.overwrite(node.start as number, node.end as number, text);
}

function todo(ctx: Ctx, reason: string, code?: string): void {
    if (!ctx.todos.some((entry) => entry.reason === reason && entry.code === code)) {
        ctx.todos.push({ reason, code });
    }
}

function keyName(prop: t.ObjectMethod | t.ObjectProperty): string | null {
    if (prop.computed) {
        return null;
    }

    if (prop.key.type === 'Identifier') {
        return prop.key.name;
    }

    if (prop.key.type === 'StringLiteral') {
        return prop.key.value;
    }

    return null;
}

/**
 * Normalizes an object member to its function, regardless of `foo() {}` / `foo: function () {}` /
 * `foo: () => {}` authoring style.
 */
function asFunction(prop: t.ObjectMethod | t.ObjectProperty | t.SpreadElement): FnLike | null {
    if (prop.type === 'ObjectMethod' && prop.kind === 'method' && !prop.generator) {
        return { fnNode: prop, params: prop.params, body: prop.body, async: prop.async };
    }

    if (
        prop.type === 'ObjectProperty' &&
        (prop.value.type === 'FunctionExpression' || prop.value.type === 'ArrowFunctionExpression') &&
        !prop.value.generator
    ) {
        return { fnNode: prop.value, params: prop.value.params, body: prop.value.body, async: prop.value.async };
    }

    return null;
}

function visitChildren(node: t.Node, visit: (child: t.Node) => void): void {
    const keys = VISITOR_KEYS[node.type] ?? [];

    for (const key of keys) {
        const child = (node as unknown as Record<string, unknown>)[key];

        if (Array.isArray(child)) {
            for (const entry of child) {
                if (entry && typeof (entry as t.Node).type === 'string') {
                    visit(entry as t.Node);
                }
            }
        } else if (child && typeof (child as t.Node).type === 'string') {
            visit(child as t.Node);
        }
    }
}

function isThisMember(node: t.Node): node is t.MemberExpression {
    return node.type === 'MemberExpression' && node.object.type === 'ThisExpression';
}

function memberName(node: t.MemberExpression): string | null {
    if (!node.computed && node.property.type === 'Identifier') {
        return node.property.name;
    }

    if (node.computed && node.property.type === 'StringLiteral') {
        return node.property.value;
    }

    return null;
}

/**
 * Rewrites every component-bound `this.*` inside `node`. `thisIsComponent` is false inside nested
 * non-arrow functions, whose `this` is not the component — those references become TODOs instead of
 * wrong rewrites.
 */
function rewriteThis(ctx: Ctx, node: t.Node, thisIsComponent: boolean): void {
    // `this.$emit('event', ...)`: infer the emits declaration from literal event names.
    if (
        node.type === 'CallExpression' &&
        thisIsComponent &&
        isThisMember(node.callee) &&
        memberName(node.callee) === '$emit'
    ) {
        const event = node.arguments[0];

        if (event && event.type === 'StringLiteral') {
            if (!ctx.inferredEmits.includes(event.value)) {
                ctx.inferredEmits.push(event.value);
            }
        } else {
            todo(ctx, 'dynamic $emit event name', raw(ctx, node));
        }
    }

    // `this.$refs.x` → `x.value` (handled on the outer member so the ref name is known).
    if (node.type === 'MemberExpression' && isThisMember(node.object) && memberName(node.object) === '$refs') {
        if (!thisIsComponent) {
            todo(ctx, '`this.$refs` inside a nested function keeps its own `this`', raw(ctx, node));
            return;
        }

        const refName = memberName(node);

        if (refName && IDENTIFIER.test(refName) && !ctx.bindings.has(refName)) {
            ctx.templateRefs.add(refName);
            overwrite(ctx, node, `${refName}.value`);
            return;
        }

        todo(
            ctx,
            refName ? `template ref '${refName}' collides with an existing binding` : 'dynamic this.$refs access',
            raw(ctx, node),
        );

        if (node.computed) {
            rewriteThis(ctx, node.property, thisIsComponent);
        }

        return;
    }

    if (isThisMember(node)) {
        rewriteThisMember(ctx, node, thisIsComponent);
        return;
    }

    if (node.type === 'ThisExpression') {
        todo(ctx, thisIsComponent ? 'bare `this` usage' : '`this` inside a nested function');
        return;
    }

    // Nested non-arrow functions rebind `this`; arrows inherit the current binding.
    const rebindsThis =
        node.type === 'FunctionExpression' ||
        node.type === 'FunctionDeclaration' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod';

    visitChildren(node, (child) => rewriteThis(ctx, child, rebindsThis ? false : thisIsComponent));
}

function rewriteThisMember(ctx: Ctx, node: t.MemberExpression, thisIsComponent: boolean): void {
    const name = memberName(node);

    if (!name) {
        todo(ctx, 'dynamic `this[...]` access', raw(ctx, node));
        rewriteThis(ctx, node.property, thisIsComponent);
        return;
    }

    if (!thisIsComponent) {
        todo(ctx, `\`this.${name}\` inside a nested function keeps its own \`this\``);
        return;
    }

    if (SKIP_INSTANCE_PROPS.has(name)) {
        ctx.blockers.add(`this.${name}`);
        return;
    }

    const instanceProp = INSTANCE_PROPS[name];

    if (instanceProp) {
        if (instanceProp.helper) {
            ctx.helpers.add(instanceProp.helper);
        }

        overwrite(ctx, node, instanceProp.replacement);
        return;
    }

    const kind = ctx.bindings.get(name);

    if (kind === 'prop') {
        ctx.helpers.add('props');
        overwrite(ctx, node, `props.${name}`);
    } else if (kind === 'data' || kind === 'computed') {
        overwrite(ctx, node, `${name}.value`);
    } else if (kind === 'method' || kind === 'inject') {
        overwrite(ctx, node, name);
    } else {
        todo(ctx, `unmapped this.${name}`);
    }
}

/**
 * Walks a member function's children with component `this` semantics. Arrow-function members never
 * had component `this` in the Options API either, so their contents are treated as foreign.
 */
function rewriteMemberFn(ctx: Ctx, fn: FnLike): void {
    const thisIsComponent = fn.fnNode.type !== 'ArrowFunctionExpression';

    visitChildren(fn.fnNode, (child) => rewriteThis(ctx, child, thisIsComponent));
}

function arrowText(ctx: Ctx, fn: FnLike): string {
    const params =
        fn.params.length > 0
            ? snip(ctx, {
                  start: fn.params[0].start,
                  end: fn.params[fn.params.length - 1].end,
              } as t.Node)
            : '';
    const asyncPrefix = fn.async ? 'async ' : '';

    return `${asyncPrefix}(${params}) => ${snip(ctx, fn.body)}`;
}

/** Text of a block body's statements, without the surrounding braces. */
function blockInner(ctx: Ctx, fn: FnLike): string {
    if (fn.body.type !== 'BlockStatement') {
        return `${snip(ctx, fn.body)};`;
    }

    return ctx.ms
        .snip((fn.body.start as number) + 1, (fn.body.end as number) - 1)
        .toString()
        .trim();
}

function todoBlock(entry: TodoEntry): string {
    const header = `// TODO(sfc-migration): ${entry.reason}`;

    if (!entry.code) {
        return header;
    }

    const codeLines = entry.code.split('\n').map((line) => `// ${line}`);

    return [
        `${header} — original code:`,
        ...codeLines,
    ].join('\n');
}

function unwrapOptions(declaration: t.Node): t.ObjectExpression | null {
    if (declaration.type === 'ObjectExpression') {
        return declaration;
    }

    if (declaration.type === 'TSAsExpression' || declaration.type === 'TSSatisfiesExpression') {
        return unwrapOptions(declaration.expression);
    }

    if (declaration.type === 'CallExpression' && declaration.arguments.length >= 1) {
        const callee = declaration.callee;
        const calleeName =
            callee.type === 'Identifier'
                ? callee.name
                : callee.type === 'MemberExpression' && callee.property.type === 'Identifier'
                  ? callee.property.name
                  : null;

        if (
            (calleeName === 'wrapComponentConfig' || calleeName === 'defineComponent') &&
            declaration.arguments[0].type === 'ObjectExpression'
        ) {
            return declaration.arguments[0];
        }
    }

    return null;
}

function transformScript(source: string, componentName: string): ScriptResult {
    const ctx: Ctx = {
        source,
        ms: new MagicString(source),
        bindings: new Map(),
        templateRefs: new Set(),
        helpers: new Set(),
        inferredEmits: [],
        todos: [],
        blockers: new Set(),
    };

    let ast: t.File;

    try {
        ast = parse(source, { sourceType: 'module', plugins: ['typescript'] });
    } catch (error) {
        return { script: null, todos: [], blockers: [`script parse error: ${(error as Error).message}`] };
    }

    const body = ast.program.body;
    const exportDefault = body.find(
        (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
    );

    if (!exportDefault) {
        return { script: null, todos: [], blockers: ['no default export'] };
    }

    const options = unwrapOptions(exportDefault.declaration);

    if (!options) {
        return { script: null, todos: [], blockers: ['unsupported default export shape'] };
    }

    // --- classify the top-level options ------------------------------------------------------

    let propsNode: t.ObjectExpression | t.ArrayExpression | null = null;
    const propNames = new Set<string>();
    let emitsNode: t.Node | null = null;
    let inheritAttrs: string | null = null;
    const injects: string[] = [];
    const dataEntries: { name: string; valueNode: t.Node }[] = [];
    const computeds: { name: string; text: () => string }[] = [];
    const methods: { name: string; text: () => string }[] = [];
    const watchEntries: { key: string; prop: t.ObjectProperty | t.ObjectMethod }[] = [];
    const hooks: { hook: string; fn: FnLike }[] = [];
    const rewriteFns: FnLike[] = [];
    const foreignNodes: t.Node[] = [];
    let createdFn: FnLike | null = null;

    const collectFnMember = (
        prop: t.ObjectMethod | t.ObjectProperty | t.SpreadElement,
        bucket: { name: string; text: () => string }[],
        kind: MemberKind,
        optionLabel: string,
    ): void => {
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
                rewriteFns.push(getFn, setFn);
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

        rewriteFns.push(fn);
        ctx.bindings.set(name, kind);

        if (kind === 'computed') {
            bucket.push({ name, text: () => `const ${name} = computed(${arrowText(ctx, fn)});` });
        } else {
            bucket.push({ name, text: () => `const ${name} = ${arrowText(ctx, fn)};` });
        }
    };

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

        if (name === 'template') {
            continue;
        }

        if (name === 'name') {
            if (prop.type === 'ObjectProperty' && prop.value.type === 'StringLiteral') {
                if (prop.value.value !== componentName) {
                    ctx.blockers.add(`name '${prop.value.value}' does not match the directory name`);
                }
            } else {
                ctx.blockers.add('non-literal component name');
            }
            continue;
        }

        if (name === 'inheritAttrs') {
            if (prop.type === 'ObjectProperty' && prop.value.type === 'BooleanLiteral') {
                inheritAttrs = String(prop.value.value);
            } else {
                todo(ctx, 'non-literal inheritAttrs', raw(ctx, prop));
            }
            continue;
        }

        if (name === 'props' && prop.type === 'ObjectProperty') {
            if (prop.value.type === 'ObjectExpression' || prop.value.type === 'ArrayExpression') {
                propsNode = prop.value;

                if (prop.value.type === 'ObjectExpression') {
                    for (const propEntry of prop.value.properties) {
                        const propName = propEntry.type === 'SpreadElement' ? null : keyName(propEntry);

                        if (propName) {
                            propNames.add(propName);
                            ctx.bindings.set(propName, 'prop');
                        }
                    }
                } else {
                    for (const element of prop.value.elements) {
                        if (element && element.type === 'StringLiteral') {
                            propNames.add(element.value);
                            ctx.bindings.set(element.value, 'prop');
                        }
                    }
                }

                foreignNodes.push(prop.value);
            } else {
                todo(ctx, 'unsupported props declaration', raw(ctx, prop));
            }
            continue;
        }

        if (name === 'emits' && prop.type === 'ObjectProperty') {
            emitsNode = prop.value;
            foreignNodes.push(prop.value);
            continue;
        }

        if (name === 'inject' && prop.type === 'ObjectProperty') {
            const elements = prop.value.type === 'ArrayExpression' ? prop.value.elements : null;
            const names = elements?.map((element) =>
                element && element.type === 'StringLiteral' && IDENTIFIER.test(element.value) ? element.value : null,
            );

            if (names && names.every((injectName): injectName is string => injectName !== null)) {
                for (const injectName of names) {
                    injects.push(injectName);
                    ctx.bindings.set(injectName, 'inject');
                }
            } else {
                todo(ctx, 'unsupported inject declaration (only the array form is migrated)', raw(ctx, prop));
            }
            continue;
        }

        if (name === 'data') {
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
                continue;
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

                dataEntries.push({ name: entryName, valueNode: entry.value });
                ctx.bindings.set(entryName, 'data');
            }
            continue;
        }

        if (name === 'computed' && prop.type === 'ObjectProperty' && prop.value.type === 'ObjectExpression') {
            for (const entry of prop.value.properties) {
                collectFnMember(entry, computeds, 'computed', 'computed');
            }
            continue;
        }

        if (name === 'methods' && prop.type === 'ObjectProperty' && prop.value.type === 'ObjectExpression') {
            for (const entry of prop.value.properties) {
                collectFnMember(entry, methods, 'method', 'methods');
            }
            continue;
        }

        if (name === 'watch' && prop.type === 'ObjectProperty' && prop.value.type === 'ObjectExpression') {
            for (const entry of prop.value.properties) {
                const watchKey = entry.type === 'SpreadElement' ? null : keyName(entry);

                if (!watchKey || entry.type === 'SpreadElement') {
                    todo(ctx, 'unsupported watch entry', raw(ctx, entry));
                    continue;
                }

                watchEntries.push({ key: watchKey, prop: entry });
            }
            continue;
        }

        if (name === 'created') {
            createdFn = asFunction(prop);

            if (!createdFn) {
                todo(ctx, 'created is not a plain function', raw(ctx, prop));
            }
            continue;
        }

        if (LIFECYCLE_HOOKS[name]) {
            const fn = asFunction(prop);

            if (fn) {
                hooks.push({ hook: LIFECYCLE_HOOKS[name], fn });
            } else {
                todo(ctx, `${name} is not a plain function`, raw(ctx, prop));
            }
            continue;
        }

        todo(ctx, TODO_OPTIONS.has(name) ? `convert '${name}' manually` : `unknown option '${name}'`, raw(ctx, prop));
    }

    // --- process watchers now that the binding map is complete --------------------------------

    // Deferred: watcher text is rendered at assembly time, after the rewrite pass has run.
    const watchers: (() => string)[] = [];

    for (const { key, prop } of watchEntries) {
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
            rewriteFns.push(handlerFn);
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

    // --- name safety checks --------------------------------------------------------------------

    const setupBindingNames = [
        ...injects,
        ...dataEntries.map((entry) => entry.name),
        ...computeds.map((computedEntry) => computedEntry.name),
        ...methods.map((method) => method.name),
    ];

    for (const bindingName of setupBindingNames) {
        if (RESERVED_BINDING.test(bindingName)) {
            ctx.blockers.add(`binding '${bindingName}' uses a reserved name`);
        }

        if (GENERATED_HELPER_NAMES.has(bindingName)) {
            ctx.blockers.add(`binding '${bindingName}' collides with a generated helper`);
        }

        // The runtime strips declared prop keys from returned setup state, so such a binding would
        // silently render as `undefined`.
        if (propNames.has(bindingName)) {
            ctx.blockers.add(`'${bindingName}' is declared as both a prop and a component member`);
        }
    }

    if (ctx.blockers.size > 0) {
        return { script: null, todos: [], blockers: [...ctx.blockers] };
    }

    // --- rewrite pass ---------------------------------------------------------------------------

    if (createdFn) {
        rewriteFns.push(createdFn);
    }

    for (const { fn } of hooks) {
        rewriteFns.push(fn);
    }

    for (const fn of rewriteFns) {
        rewriteMemberFn(ctx, fn);
    }

    for (const entry of dataEntries) {
        rewriteThis(ctx, entry.valueNode, true);
    }

    for (const node of foreignNodes) {
        rewriteThis(ctx, node, false);
    }

    if (ctx.blockers.size > 0) {
        return { script: null, todos: [], blockers: [...ctx.blockers] };
    }

    // --- prelude (module-level code outside the component options) ------------------------------

    const templateImport = body.find(
        (statement): statement is t.ImportDeclaration =>
            statement.type === 'ImportDeclaration' && statement.source.value.endsWith('.html.twig'),
    );

    if (templateImport) {
        const end = ctx.source.indexOf('\n', templateImport.end as number);
        ctx.ms.remove(templateImport.start as number, end === -1 ? (templateImport.end as number) : end + 1);
    }

    const preludeBefore = ctx.ms
        .snip(0, exportDefault.start as number)
        .toString()
        .trim();
    const preludeAfter = ctx.ms
        .snip(exportDefault.end as number, source.length)
        .toString()
        .trim();

    // --- assembly --------------------------------------------------------------------------------

    const usesEmit = ctx.helpers.has('emit');
    const usesProps = ctx.helpers.has('props');
    const vueImports = [
        ...(dataEntries.length > 0 || ctx.templateRefs.size > 0 ? ['ref'] : []),
        ...(computeds.length > 0 ? ['computed'] : []),
        ...(watchers.length > 0 ? ['watch'] : []),
        ...(injects.length > 0 ? ['inject'] : []),
        ...(ctx.helpers.has('nextTick') ? ['nextTick'] : []),
        ...(ctx.helpers.has('slots') ? ['useSlots'] : []),
        ...(ctx.helpers.has('attrs') ? ['useAttrs'] : []),
        ...[...new Set(hooks.map((hook) => hook.hook))],
    ];
    const routerImports = [
        ...(ctx.helpers.has('router') ? ['useRouter'] : []),
        ...(ctx.helpers.has('route') ? ['useRoute'] : []),
    ];

    const emitsText = emitsNode
        ? snip(ctx, emitsNode)
        : ctx.inferredEmits.length > 0 || usesEmit
          ? `[${ctx.inferredEmits.map((event) => `'${event}'`).join(', ')}]`
          : null;
    const propsText = propsNode ? snip(ctx, propsNode) : usesProps ? '{}' : null;

    // One-line declarations are grouped into contiguous blocks so the output reads hand-written;
    // multi-line members keep a blank line between them.
    const importBlock = [
        vueImports.length > 0 ? `import { ${vueImports.join(', ')} } from 'vue';` : null,
        ctx.helpers.has('t') ? "import { useI18n } from 'vue-i18n';" : null,
        routerImports.length > 0 ? `import { ${routerImports.join(', ')} } from 'vue-router';` : null,
    ]
        .filter(Boolean)
        .join('\n');
    const helperBlock = (
        [
            't',
            'router',
            'route',
            'slots',
            'attrs',
        ] as const
    )
        .filter((helper) => ctx.helpers.has(helper))
        .map((helper) => HELPER_SETUP_LINES[helper])
        .join('\n');
    const injectBlock = injects.map((injectName) => `const ${injectName} = inject('${injectName}');`).join('\n');
    const dataBlock = dataEntries.map((entry) => `const ${entry.name} = ref(${snip(ctx, entry.valueNode)});`).join('\n');
    const refBlock = [...ctx.templateRefs].map((refName) => `const ${refName} = ref(null);`).join('\n');

    const sections: (string | null)[] = [
        importBlock || null,
        preludeBefore || null,
        preludeAfter || null,
        helperBlock || null,
        injectBlock || null,
        inheritAttrs !== null ? `defineOptions({ inheritAttrs: ${inheritAttrs} });` : null,
        propsText !== null
            ? usesProps
                ? `const props = defineProps(${propsText});`
                : `defineProps(${propsText});`
            : null,
        emitsText !== null
            ? usesEmit
                ? `const emit = defineEmits(${emitsText});`
                : `defineEmits(${emitsText});`
            : null,
        ...methods.map((method) => method.text()),
        ...computeds.map((computedEntry) => computedEntry.text()),
        dataBlock || null,
        refBlock || null,
        ...watchers.map((watcher) => watcher()),
        ...hooks.map(({ hook, fn }) => `${hook}(${arrowText(ctx, fn)});`),
        createdFn
            ? createdFn.async
                ? `void (async () => ${snip(ctx, createdFn.body)})();`
                : blockInner(ctx, createdFn) || null
            : null,
        ...ctx.todos.map(todoBlock),
    ];

    const publicNames = [
        ...injects,
        ...dataEntries.map((entry) => entry.name),
        ...ctx.templateRefs,
        ...computeds.map((computedEntry) => computedEntry.name),
        ...methods.map((method) => method.name),
    ];
    const publicText =
        publicNames.length > 0
            ? `swDefinePublic({\n${publicNames.map((publicName) => `${publicName},`).join('\n')}\n});`
            : 'swDefinePublic({});';

    sections.push(publicText);

    return {
        script: sections.filter((section): section is string => Boolean(section)).join('\n\n'),
        todos: ctx.todos.map((entry) => entry.reason),
        blockers: [],
    };
}

export { transformScript, type ScriptResult };
