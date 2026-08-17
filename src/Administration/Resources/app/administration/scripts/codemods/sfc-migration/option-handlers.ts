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
 *
 * Two options cannot be finished inside the loop, because they depend on what the remaining options
 * declare: `mixins` (resolveMixins) and `watch` (buildWatchers) each get a second pass that runs
 * once classification is complete.
 */

import type * as t from '@babel/types';
import {
    GENERATED_HELPER_NAMES,
    LIFECYCLE_HOOKS,
    RESERVED_BINDING,
    SKIP_OPTIONS,
    TODO_OPTIONS,
    type MemberKind,
    type TodoEntry,
} from './tables';
import {
    type Ctx,
    type FnLike,
    IDENTIFIER,
    arrowText,
    asFunction,
    bindingName,
    collectAssignedThisMemberNames,
    collectThisMemberNames,
    keyName,
    raw,
    snip,
    todo,
    todoAtDeclaration,
    todoReview,
    visitChildren,
} from './ast';
import {
    type ComposableDescriptor,
    type ComposableProvidedProp,
    type ComposableScaffold,
    composableCallbacks,
    findComposableDescriptor,
    scaffoldRunsUnread,
} from './composables';

type NamedText = { name: string; text: () => string };

/** Everything the classification pass collects for the later rewrite and assembly steps. */
type Collected = {
    propsNode: t.ObjectExpression | t.ArrayExpression | null;
    /** Whether the `props` option is an object literal a mixin's own props can be merged into. */
    propsMergeable: boolean;
    propNames: Set<string>;
    /** The props the resolved mixins declared, in descriptor order. */
    providedProps: ComposableProvidedProp[];
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
    mixins: ComposableDescriptor[];
};

type OptionHandler = (prop: t.ObjectMethod | t.ObjectProperty, ctx: Ctx, collected: Collected) => boolean;

function containsThisAccess(node: t.Node): boolean {
    if (node.type === 'ThisExpression') {
        return true;
    }

    if (node.type === 'MemberExpression' && node.object.type === 'ThisExpression') {
        return true;
    }

    let found = false;
    visitChildren(node, (child) => {
        if (!found && containsThisAccess(child)) {
            found = true;
        }
    });

    return found;
}

function memberAccess(base: string, segments: string[]): string {
    return segments.reduce(
        (value, segment) => (IDENTIFIER.test(segment) ? `${value}.${segment}` : `${value}[${JSON.stringify(segment)}]`),
        base,
    );
}

function createCollected(): Collected {
    return {
        propsNode: null,
        propsMergeable: true,
        propNames: new Set(),
        providedProps: [],
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
        mixins: [],
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

/** The object literal a `data()` option returns directly, or null for any other shape. */
function dataObject(prop: t.ObjectMethod | t.ObjectProperty): t.ObjectExpression | null {
    const fn = asFunction(prop);

    if (!fn) {
        return null;
    }

    if (fn.body.type === 'ObjectExpression') {
        return fn.body;
    }

    if (
        fn.body.type === 'BlockStatement' &&
        fn.body.body.length === 1 &&
        fn.body.body[0].type === 'ReturnStatement' &&
        fn.body.body[0].argument?.type === 'ObjectExpression'
    ) {
        return fn.body.body[0].argument;
    }

    return null;
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
        // Every shape below the object literal keeps its own declaration, but a mixin's props can no
        // longer be merged into it.
        collected.propsMergeable = false;

        if (prop.type !== 'ObjectProperty') {
            return false;
        }

        if (prop.value.type === 'ObjectExpression' || prop.value.type === 'ArrayExpression') {
            collected.propsNode = prop.value;

            if (prop.value.type === 'ObjectExpression') {
                collected.propsMergeable = prop.value.properties.every((entry) => entry.type !== 'SpreadElement');

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
            // Vue's Options API unwraps injected refs on reads and forwards writes to `.value`.
            // The generated binding cannot prove whether a provider returns a primitive, reactive
            // object, or Ref, so keep the draft but make the result partial until the runtime
            // representation is deliberately normalized and covered.
            todo(ctx, 'array inject declaration requires runtime ref-unwrapping verification', raw(ctx, prop));

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

        if (fn && fn.params.length > 0) {
            todo(ctx, 'parameterized data() requires an explicit vm mapping', raw(ctx, prop));
            return true;
        }

        const returned = dataObject(prop);

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

            if (containsThisAccess(entry.value)) {
                todo(ctx, 'data() initializer reads component this and is not runtime-equivalent', raw(ctx, entry));
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

    // Resolution only: the guards need every member name the component declares, which the
    // classification loop has not seen yet. resolveMixins() runs them once it has.
    mixins: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ArrayExpression') {
            ctx.blockers.add('unsupported mixins declaration');
            return true;
        }

        for (const element of prop.value.elements) {
            const mixinName = element === null ? null : registeredMixinName(element);

            if (mixinName === null) {
                ctx.blockers.add(`unsupported mixins entry${element ? ` '${raw(ctx, element)}'` : ''}`);
                continue;
            }

            const descriptor = findComposableDescriptor(mixinName);

            if (descriptor === undefined) {
                ctx.blockers.add(`no composable registered for mixin '${mixinName}'`);
                continue;
            }

            if (!collected.mixins.includes(descriptor)) {
                collected.mixins.push(descriptor);
            }
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

/**
 * The registered name of one `mixins` array entry, or null for a shape no descriptor can be looked
 * up from. Both authoring forms resolve to the same lookup, because Shopware's vue adapter puts a
 * bare string through `Mixin.getByName()` itself; the callee object is not checked, so the
 * destructured `Mixin.getByName(...)` form is recognized next to `Shopware.Mixin.getByName(...)`.
 */
function registeredMixinName(element: t.Node): string | null {
    if (element.type === 'StringLiteral') {
        return element.value;
    }

    if (
        element.type === 'CallExpression' &&
        element.callee.type === 'MemberExpression' &&
        !element.callee.computed &&
        element.callee.property.type === 'Identifier' &&
        element.callee.property.name === 'getByName' &&
        element.arguments.length === 1 &&
        element.arguments[0].type === 'StringLiteral'
    ) {
        return element.arguments[0].value;
    }

    return null;
}

/**
 * Every name the component itself puts on the instance, read off the options AST rather than off
 * `Collected`: an option entry the codemod dropped as unsupported is missing from `Collected` but
 * still shadows a mixin's member at runtime, which is exactly what the override guard asks about.
 */
function collectOwnMemberNames(options: t.ObjectExpression): Set<string> {
    const names = new Set<string>();

    const addKeys = (node: t.Node): void => {
        if (node.type === 'ObjectExpression') {
            for (const member of node.properties) {
                const name = member.type === 'SpreadElement' ? null : keyName(member);

                if (name) {
                    names.add(name);
                }
            }
        }

        if (node.type === 'ArrayExpression') {
            for (const element of node.elements) {
                if (element?.type === 'StringLiteral') {
                    names.add(element.value);
                }
            }
        }
    };

    for (const option of options.properties) {
        if (option.type === 'SpreadElement') {
            continue;
        }

        const optionName = keyName(option);

        if (optionName === 'data') {
            const returned = dataObject(option);

            if (returned) {
                addKeys(returned);
            }
        } else if (
            (optionName === 'props' || optionName === 'computed' || optionName === 'methods' || optionName === 'inject') &&
            option.type === 'ObjectProperty'
        ) {
            addKeys(option.value);
        }
    }

    return names;
}

type ResolvedComposable = {
    descriptor: ComposableDescriptor;
    /**
     * The members the component actually uses, in descriptor order. A renamed one carries the TODO
     * that asks the reader to review the generated name, to be emitted above the destructure.
     */
    entries: { member: string; sourceKey: string; binding: string; renameTodo?: TodoEntry }[];
    /** `key: value` texts of the options object the composable is called with. */
    args: string[];
    /** `data()` entries routed into that options object, rendered after the rewrite pass. */
    config: { key: string; valueNode: t.Node }[];
};

/** The event names an `emits` array option declares, or null for any shape that is not one. */
function emitsEventNames(node: t.Node): string[] | null {
    if (node.type !== 'ArrayExpression') {
        return null;
    }

    const names = node.elements.map((element) => (element?.type === 'StringLiteral' ? element.value : null));

    return names.every((name): name is string => name !== null) ? names : null;
}

/**
 * Refuses a component that does not supply what a mixin took from its host instance. All three
 * dependencies reach the composable as call arguments, so each needs something the codemod can pass:
 * the mixin's own `props` option, its `emits` list and its overridable methods are gone afterwards.
 */
function refuseUnmetDependencies(
    ctx: Ctx,
    descriptor: ComposableDescriptor,
    collected: Collected,
    ownMembers: ReadonlySet<string>,
): void {
    const events = Object.values(descriptor.emits ?? {});

    if (events.length > 0 && collected.emitsNode && emitsEventNames(collected.emitsNode) === null) {
        ctx.blockers.add(
            `emits is not a plain list of event names, so the '${descriptor.id}' mixin's events cannot be merged`,
        );
    }

    for (const prop of descriptor.propArgs ?? []) {
        if (!collected.propNames.has(prop)) {
            ctx.blockers.add(`component does not declare the '${prop}' prop the '${descriptor.id}' mixin reads`);
        }
    }

    for (const callback of composableCallbacks(descriptor)) {
        const kind = ctx.bindings.get(callback.name);

        if (kind === undefined) {
            // Declared, but classification dropped it — a decorated method, an unsupported key. The
            // member exists at runtime, so the composable cannot be left without it either.
            if (ownMembers.has(callback.name)) {
                ctx.blockers.add(
                    `'${callback.name}' is declared in a shape that cannot be handed to the '${descriptor.id}' composable`,
                );
            } else if (!callback.optional) {
                ctx.blockers.add(
                    `component does not define '${callback.name}', which the '${descriptor.id}' composable calls`,
                );
            }

            continue;
        }

        if (callback.kind === 'callback' && kind !== 'method') {
            ctx.blockers.add(`'${callback.name}' is not a method, but the '${descriptor.id}' composable calls it`);
        }
    }
}

/**
 * Registers the props the declared mixins brought along, so that `this.<prop>` rewrites to
 * `props.<prop>` and the propArgs check above sees them. A component prop of the same name wins,
 * mirroring Vue's option merge, and only what is left to merge can refuse the component.
 */
function resolveProvidedProps(ctx: Ctx, collected: Collected): void {
    for (const descriptor of collected.mixins) {
        for (const provided of descriptor.providedProps ?? []) {
            if (collected.propNames.has(provided.name)) {
                continue;
            }

            if (!collected.propsMergeable) {
                ctx.blockers.add(
                    `props are not a plain object literal, so the '${descriptor.id}' mixin's props cannot be merged`,
                );
            }

            collected.propNames.add(provided.name);
            collected.providedProps.push(provided);
            ctx.bindings.set(provided.name, 'prop');
        }
    }
}

/**
 * Moves the `data()` entries a scaffolded mixin only takes as configuration out of the component and
 * into its composable's options object. Such an entry initialized the mixin's own state through Vue's
 * option merge, so it was never a member of its own: it stops being a local ref, its binding comes from
 * the composable instead, and it no longer counts as the component redefining a mixin member.
 */
function routeScaffoldConfig(
    collected: Collected,
    ownMembers: Set<string>,
): Map<ComposableDescriptor, ResolvedComposable['config']> {
    const routed = new Map<ComposableDescriptor, ResolvedComposable['config']>();
    const routedNames = new Set<string>();

    for (const descriptor of collected.mixins) {
        const config: ResolvedComposable['config'] = [];

        for (const key of descriptor.scaffold?.configKeys ?? []) {
            const dataEntry = collected.dataEntries.find((entry) => entry.name === key);

            if (!dataEntry) {
                continue;
            }

            config.push({ key, valueNode: dataEntry.valueNode });
            routedNames.add(key);
            ownMembers.delete(key);
        }

        routed.set(descriptor, config);
    }

    collected.dataEntries = collected.dataEntries.filter((entry) => !routedNames.has(entry.name));

    return routed;
}

/**
 * The options object a composable is called with. Every argument defers the read: the call sits above
 * the member sections it points at, so an eager reference would hit their temporal dead zone.
 */
function composableArguments(ctx: Ctx, descriptor: ComposableDescriptor): string[] {
    const args: string[] = [];

    for (const [
        callbackName,
        event,
    ] of Object.entries(descriptor.emits ?? {})) {
        ctx.helpers.add('emit');
        // The payload travels through untouched, so the descriptor does not have to know the arity of
        // each event.
        args.push(`${callbackName}: (...args) => emit('${event}', ...args)`);
    }

    for (const prop of descriptor.propArgs ?? []) {
        ctx.helpers.add('props');
        args.push(`${prop}: () => props.${prop}`);
    }

    for (const callback of composableCallbacks(descriptor)) {
        const kind = ctx.bindings.get(callback.name);

        if (kind !== undefined) {
            args.push(`${callback.name}: ${instanceMemberText(ctx, callback.name, kind)}`);
        }
    }

    return args;
}

/** How one of the component's own members reaches a composable: state by value, methods by call. */
function instanceMemberText(ctx: Ctx, member: string, kind: MemberKind): string {
    const binding = bindingName(ctx, member);

    switch (kind) {
        case 'prop':
            ctx.helpers.add('props');
            return `() => props.${member}`;
        case 'data':
        case 'computed':
            return `() => ${binding}.value`;
        case 'method':
            return `(...args) => ${binding}(...args)`;
        default:
            return `() => ${binding}`;
    }
}

/** True when the script or the template reads at least one member the descriptor answers. */
function readsAnyMember(descriptor: ComposableDescriptor, readMembers: ReadonlySet<string>): boolean {
    return Object.keys(descriptor.members).some((member) => readMembers.has(member));
}

/**
 * A setup binding name for a composable member. A name another declaration already claims — a
 * module-level prelude binding, a generated helper, another mixin's member — is disambiguated with a
 * `$n` suffix rather than downgrading the migration; `ctx.renamedBindings` carries the rename into
 * the `this.<member>` rewrite.
 */
function freeBindingName(member: string, claimed: ReadonlySet<string>): string {
    if (!claimed.has(member) && !RESERVED_BINDING.test(member)) {
        return member;
    }

    for (let suffix = 1; ; suffix += 1) {
        const candidate = `${member}$${suffix}`;

        if (!claimed.has(candidate)) {
            return candidate;
        }
    }
}

/**
 * Leaves a generated binding name up for review. The rename is what keeps the component migratable,
 * but a generated name is nobody's choice and it costs the member its place in `swDefinePublic`, so
 * the draft says so where the name is introduced.
 */
function noteBindingRename(ctx: Ctx, member: string, binding: string): TodoEntry {
    return todoAtDeclaration(
        ctx,
        `'${member}' was renamed to '${binding}' — its name is already taken by another binding`,
        'The draft runs as emitted; a renamed member stays out of swDefinePublic, so rename it and its uses to have it public or prettier',
    );
}

/**
 * Turns the resolved mixin descriptors into setup bindings, or refuses the component.
 *
 * Runs between classification and the rewrite pass: it needs the component's complete member set to
 * check the guards, and the rewrite pass needs its bindings registered. Refusing comes first and as a
 * whole, so a component that fails one guard never gets half of its mixins converted.
 *
 * Only members the script or the template actually reads are bound. A member the template alone reads
 * still counts — the template cannot be rewritten, so its binding has to exist under that exact name.
 * A descriptor nothing reads is dropped: its composable only provides members, so calling it for its
 * side effects is not a thing the mixin did either, and its instance dependencies go unasked for. A
 * scaffold that drove a member of its host is the exception, because its side effects are the point:
 * it owns the lifecycle that called it.
 */
function resolveMixins(
    ctx: Ctx,
    collected: Collected,
    options: t.ObjectExpression,
    preludeBindings: ReadonlySet<string>,
): ResolvedComposable[] {
    if (collected.mixins.length === 0) {
        return [];
    }

    const ownMembers = collectOwnMemberNames(options);
    const readMembers = new Set<string>(ctx.templateIdentifiers);
    const assignedMembers = new Set<string>();

    collectThisMemberNames(options, readMembers);
    collectAssignedThisMemberNames(options, assignedMembers);

    // A watch entry names its source as a string instead of reaching for it through `this`, but the
    // watcher reads that member all the same and needs a binding for it.
    for (const { key } of collected.watchEntries) {
        readMembers.add(key.split('.')[0]);
    }

    resolveProvidedProps(ctx, collected);

    const routedConfig = routeScaffoldConfig(collected, ownMembers);
    const active = collected.mixins.filter(
        (descriptor) => scaffoldRunsUnread(descriptor) || readsAnyMember(descriptor, readMembers),
    );

    for (const descriptor of collected.mixins) {
        const internal = descriptor.internallyReferencedMembers ?? [];

        for (const member of internal) {
            if (ownMembers.has(member)) {
                ctx.blockers.add(
                    `component redefines '${member}', which the '${descriptor.id}' composable calls internally`,
                );
            }
        }

        for (const [
            member,
            spec,
        ] of Object.entries(descriptor.members)) {
            // A leaf override would work under Vue's merge rules — the component's member simply wins —
            // but after the migration the composable binding and the component's own binding would
            // share one name, so the component keeps the Options API instead.
            if (ownMembers.has(member) && !internal.includes(member)) {
                ctx.blockers.add(`component redefines '${member}' from the '${descriptor.id}' mixin`);
            }

            // A destructured member is a `const`. Reactive state still takes `x.value = …`; anything
            // else was a method or a plain value on the instance proxy, where the write has no
            // equivalent — the mixin's own copy would keep being the one that runs.
            if (spec.kind !== 'ref' && assignedMembers.has(member)) {
                ctx.blockers.add(
                    `'${member}' is assigned to, but the '${descriptor.id}' composable returns it as a constant`,
                );
            }
        }

        for (const member of descriptor.unmappedMembers ?? []) {
            if (ownMembers.has(member)) {
                continue;
            }

            if (readMembers.has(member)) {
                ctx.blockers.add(`'${member}' is read but the '${descriptor.id}' composable does not provide it`);
            }
        }
    }

    for (const descriptor of active) {
        refuseUnmetDependencies(ctx, descriptor, collected, ownMembers);
    }

    if (ctx.blockers.size > 0) {
        return [];
    }

    const claimed = new Set<string>([
        ...preludeBindings,
        ...GENERATED_HELPER_NAMES,
    ]);
    const resolved: ResolvedComposable[] = [];

    for (const descriptor of active) {
        const entries: ResolvedComposable['entries'] = [];

        for (const [
            member,
            spec,
        ] of Object.entries(descriptor.members)) {
            if (!readMembers.has(member)) {
                continue;
            }

            const binding = freeBindingName(member, claimed);
            let renameTodo: TodoEntry | undefined;

            if (binding !== member) {
                if (ctx.templateIdentifiers.has(member)) {
                    ctx.blockers.add(`'${member}' is read in the template and its binding name is already taken`);
                    continue;
                }

                ctx.renamedBindings.set(member, binding);
                renameTodo = noteBindingRename(ctx, member, binding);
            }

            claimed.add(binding);
            ctx.bindings.set(member, spec.kind === 'ref' ? 'data' : 'method');
            entries.push({ member, sourceKey: spec.sourceKey ?? member, binding, renameTodo });
        }

        const config = routedConfig.get(descriptor) ?? [];

        if (descriptor.scaffold) {
            noteScaffoldReview(ctx, descriptor, descriptor.scaffold, config);
        }

        resolved.push({ descriptor, entries, args: composableArguments(ctx, descriptor), config });
    }

    return resolved;
}

/**
 * Keeps a scaffolded component a draft. Wiring up an abstract controller is mechanical, but whether the
 * result still behaves the same is not something the codemod can answer, so it says so in the output
 * and the outcome follows from there being a TODO at all.
 */
function noteScaffoldReview(
    ctx: Ctx,
    descriptor: ComposableDescriptor,
    scaffold: ComposableScaffold,
    config: ResolvedComposable['config'],
): void {
    const routedKeys = config.map(({ key }) => key);

    todoReview(
        ctx,
        `${descriptor.import.name}() replaces the '${descriptor.id}' mixin`,
        'Nothing is missing from the draft; what the codemod cannot decide is whether it behaves the same — check:',
        [
            ...scaffold.checks,
            ...(routedKeys.length > 0
                ? [`these were routed into the composable options instead of staying state: ${routedKeys.join(', ')}`]
                : []),
        ],
    );
}

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
        const rest = segments.slice(1);
        const headKind = ctx.bindings.get(head);
        let sourceText: string | null = null;

        if (head === '$route') {
            if (rest.length === 0) {
                todo(ctx, `watch source '${key}' has exact $route semantics that need runtime verification`, raw(ctx, prop));
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

export {
    type Collected,
    type OptionHandler,
    type ResolvedComposable,
    OPTION_HANDLERS,
    classifyOptions,
    collectOwnMemberNames,
    emitsEventNames,
    resolveMixins,
    buildWatchers,
};
