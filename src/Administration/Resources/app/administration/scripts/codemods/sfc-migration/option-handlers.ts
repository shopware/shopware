/**
 * @sw-package framework
 */

/**
 * One handler per supported top-level option. An option no handler claims falls into its
 * `OPTION_TIERS` tier or becomes an unknown-option TODO.
 *
 * Handlers only collect descriptors: rendering has to read the MagicString after the `this` rewrite.
 * `mixins` is only resolved here, because its guards need every member the other options declare;
 * `resolveMixins()` runs them once classification is complete.
 */

import { traverseFast } from '@babel/types';
import type * as t from '@babel/types';
import { isReservedBindingName } from '../../../build/vue-setup-transform/naming';
import {
    GENERATED_HELPER_NAMES,
    LIFECYCLE_HOOKS,
    OPTION_TIERS,
    sourceKeyed,
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
    keyName,
    raw,
    report,
    reportAtDeclaration,
    reportReview,
    snip,
    thisMemberNames,
} from './ast';
import {
    type ComposableDescriptor,
    type ComposableProvidedProp,
    type ComposableScaffold,
    composableCallbacks,
    findComposableDescriptor,
    scaffoldRunsUnread,
} from './composables';

type CollectedMember =
    | { kind: 'computed'; name: string; fn: FnLike }
    | { kind: 'method'; name: string; fn: FnLike }
    | { kind: 'writable-computed'; name: string; getFn: FnLike; setFn: FnLike };

type CollectedWatcher = { source: string; handler: FnLike | string; options: string };

type Collected = {
    propsNode: t.ObjectExpression | t.ArrayExpression | null;
    /** Whether a mixin's props can be merged into the `props` literal. */
    propsMergeable: boolean;
    propNames: Set<string>;
    providedProps: ComposableProvidedProp[];
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
    mixins: ComposableDescriptor[];
};

const ASYNC_CREATED = "async created() rejections bypass Vue's error handling";

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

/** A claimed option of an unrecognized shape reads, to the reader, like an unclaimed one. */
function unknownOption(ctx: Ctx, name: string, prop: t.ObjectMethod | t.ObjectProperty): void {
    report(ctx, 'todo', `unknown option '${name}'`, prop);
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
        // Any shape but a spread-free object literal keeps its declaration, but a mixin's props can
        // no longer be merged into it.
        collected.propsMergeable = false;

        if (prop.type !== 'ObjectProperty') {
            unknownOption(ctx, 'props', prop);
            return;
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
            // The Options API unwraps an injected ref on read and forwards writes to `.value`; the
            // setup binding cannot know whether the provider hands out a ref.
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

        const returned = dataObject(prop);

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

    mixins: (prop, ctx, collected) => {
        if (prop.type !== 'ObjectProperty' || prop.value.type !== 'ArrayExpression') {
            report(ctx, 'skip', 'unsupported mixins declaration');
            return;
        }

        for (const element of prop.value.elements) {
            const mixinName = element === null ? null : registeredMixinName(element);

            if (mixinName === null) {
                report(ctx, 'skip', `unsupported mixins entry${element ? ` '${raw(ctx, element)}'` : ''}`);
                continue;
            }

            const descriptor = findComposableDescriptor(mixinName);

            if (descriptor === undefined) {
                report(ctx, 'skip', `no composable registered for mixin '${mixinName}'`);
                continue;
            }

            if (!collected.mixins.includes(descriptor)) {
                collected.mixins.push(descriptor);
            }
        }
    },

    created: (prop, ctx, collected) => {
        collected.createdFn = asFunction(prop);

        if (!collected.createdFn) {
            report(ctx, 'todo', 'created is not a plain function', prop);
        } else if (collected.createdFn.async) {
            // Vue awaits a hook's promise to route its rejection; the inlined call is not a hook.
            reportReview(
                ctx,
                ASYNC_CREATED,
                'The draft runs as emitted; a rejection of the inlined created() body — check:',
                [
                    'no errorCaptured hook or app.config.errorHandler relies on seeing it, because it now surfaces as an unhandled rejection',
                ],
            );
        }
    },
});

/**
 * The registered name of one `mixins` entry. A bare string means the same as `Mixin.getByName()`,
 * which Shopware's vue adapter calls on it; the callee object is not checked, so a destructured
 * `Mixin` matches too.
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
 * Every name the component puts on the instance, read off the AST rather than `Collected`: an entry
 * dropped as unsupported still shadows a mixin's member at runtime.
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
    /** The members the component uses, in descriptor order; a renamed one carries its review TODO. */
    entries: { member: string; sourceKey: string; binding: string; renameTodo?: TodoEntry }[];
    args: string[];
    /** `data()` entries routed into the options object, rendered after the rewrite pass. */
    config: { key: string; valueNode: t.Node }[];
};

function emitsEventNames(node: t.Node): string[] | null {
    if (node.type !== 'ArrayExpression') {
        return null;
    }

    const names = node.elements.map((element) => (element?.type === 'StringLiteral' ? element.value : null));

    return names.every((name): name is string => name !== null) ? names : null;
}

/**
 * Refuses a component that does not supply what a mixin took from its host: the mixin's `props`,
 * `emits` and overridable methods are gone afterwards, so each has to come from the component.
 */
function refuseUnmetDependencies(
    ctx: Ctx,
    descriptor: ComposableDescriptor,
    collected: Collected,
    ownMembers: ReadonlySet<string>,
): void {
    const events = Object.values(descriptor.emits ?? {});

    if (events.length > 0 && collected.emitsNode && emitsEventNames(collected.emitsNode) === null) {
        report(
            ctx,
            'skip',
            `emits is not a plain list of event names, so the '${descriptor.id}' mixin's events cannot be merged`,
        );
    }

    for (const prop of descriptor.propArgs ?? []) {
        if (!collected.propNames.has(prop)) {
            report(ctx, 'skip', `component does not declare the '${prop}' prop the '${descriptor.id}' mixin reads`);
        }
    }

    for (const callback of composableCallbacks(descriptor)) {
        const kind = ctx.bindings.get(callback.name);

        if (kind === undefined) {
            // Declared, but classification dropped it; the member still exists at runtime.
            if (ownMembers.has(callback.name)) {
                report(
                    ctx,
                    'skip',
                    `'${callback.name}' is declared in a shape that cannot be handed to the '${descriptor.id}' composable`,
                );
            } else if (!callback.optional) {
                report(
                    ctx,
                    'skip',
                    `component does not define '${callback.name}', which the '${descriptor.id}' composable calls`,
                );
            }

            continue;
        }

        if (callback.kind === 'callback' && kind !== 'method') {
            report(ctx, 'skip', `'${callback.name}' is not a method, but the '${descriptor.id}' composable calls it`);
        }
    }
}

/** A component prop wins over a mixin's prop of the same name, mirroring Vue's option merge. */
function resolveProvidedProps(ctx: Ctx, collected: Collected): void {
    for (const descriptor of collected.mixins) {
        for (const provided of descriptor.providedProps ?? []) {
            if (collected.propNames.has(provided.name)) {
                continue;
            }

            if (!collected.propsMergeable) {
                report(
                    ctx,
                    'skip',
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
 * Moves the `data()` entries that only configure a scaffolded mixin into its composable's options.
 * Such an entry initialized the mixin's state through the option merge, so it never was a member of
 * its own and does not count as the component redefining one.
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

/** Every argument defers its read: the call sits above the members it points at (TDZ). */
function composableArguments(ctx: Ctx, descriptor: ComposableDescriptor): string[] {
    const args: string[] = [];

    for (const [callbackName, event] of Object.entries(descriptor.emits ?? {})) {
        ctx.helpers.add('emit');
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

function readsAnyMember(descriptor: ComposableDescriptor, readMembers: ReadonlySet<string>): boolean {
    return Object.keys(descriptor.members).some((member) => readMembers.has(member));
}

/** A claimed name gets a `$n` suffix; `ctx.renamedBindings` carries it into the `this.` rewrite. */
function freeBindingName(member: string, claimed: ReadonlySet<string>): string {
    if (!claimed.has(member) && !isReservedBindingName(member)) {
        return member;
    }

    for (let suffix = 1; ; suffix += 1) {
        const candidate = `${member}$${suffix}`;

        if (!claimed.has(candidate)) {
            return candidate;
        }
    }
}

/** A generated name costs the member its place in `swDefinePublic`, so it is left up for review. */
function noteBindingRename(ctx: Ctx, member: string, binding: string): TodoEntry {
    return reportAtDeclaration(
        ctx,
        `'${member}' was renamed to '${binding}' — its name is already taken by another binding`,
        'The draft runs as emitted; a renamed member stays out of swDefinePublic, so rename it and its uses to have it public or prettier',
    );
}

/** Wiring up a controller mixin is mechanical, proving it equivalent is not, so it stays a draft. */
function noteScaffoldReview(
    ctx: Ctx,
    descriptor: ComposableDescriptor,
    scaffold: ComposableScaffold,
    config: ResolvedComposable['config'],
): void {
    const routedKeys = config.map(({ key }) => key);

    reportReview(
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

/**
 * Turns the resolved mixin descriptors into setup bindings, or refuses the component as a whole, so
 * no component gets half of its mixins converted.
 *
 * Only members the script or the template reads are bound; the template cannot be rewritten, so its
 * names stay exact. A descriptor nothing reads is dropped, except a scaffold that drives a member of
 * its host: it owns the lifecycle that calls it.
 */
function resolveMixins(
    ctx: Ctx,
    collected: Collected,
    options: t.ObjectExpression,
    outerNames: ReadonlySet<string>,
): ResolvedComposable[] {
    if (collected.mixins.length === 0) {
        return [];
    }

    const ownMembers = collectOwnMemberNames(options);
    const readMembers = new Set<string>([...ctx.templateIdentifiers, ...thisMemberNames(options)]);
    const assignedMembers = thisMemberNames(options, { assigned: true });

    // A watch key names its source as a string, but the watcher reads that member all the same.
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
                report(
                    ctx,
                    'skip',
                    `component redefines '${member}', which the '${descriptor.id}' composable calls internally`,
                );
            }
        }

        for (const [member, spec] of Object.entries(descriptor.members)) {
            // Vue's merge would let the component's member win; as setup bindings the two would
            // share one name.
            if (ownMembers.has(member) && !internal.includes(member)) {
                report(ctx, 'skip', `component redefines '${member}' from the '${descriptor.id}' mixin`);
            }

            // A destructured member is a `const`: only a ref still takes the write.
            if (spec.kind !== 'ref' && assignedMembers.has(member)) {
                report(
                    ctx,
                    'skip',
                    `'${member}' is assigned to, but the '${descriptor.id}' composable returns it as a constant`,
                );
            }
        }

        for (const member of descriptor.unmappedMembers ?? []) {
            if (ownMembers.has(member)) {
                continue;
            }

            if (readMembers.has(member)) {
                report(ctx, 'skip', `'${member}' is read but the '${descriptor.id}' composable does not provide it`);
            }
        }
    }

    for (const descriptor of active) {
        refuseUnmetDependencies(ctx, descriptor, collected, ownMembers);
    }

    if (ctx.reports.some((entry) => entry.kind === 'skip')) {
        return [];
    }

    const claimed = new Set<string>([
        ...outerNames,
        ...GENERATED_HELPER_NAMES,
        // A binding named after a component tag would be resolved instead of the component.
        ...ctx.templateComponentTags,
    ]);
    const resolved: ResolvedComposable[] = [];

    for (const descriptor of active) {
        const entries: ResolvedComposable['entries'] = [];

        for (const [member, spec] of Object.entries(descriptor.members)) {
            if (!readMembers.has(member)) {
                continue;
            }

            const binding = freeBindingName(member, claimed);
            let renameTodo: TodoEntry | undefined;

            if (binding !== member) {
                if (ctx.templateIdentifiers.has(member)) {
                    report(ctx, 'skip', `'${member}' is read in the template and its binding name is already taken`);
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

/** Runs after classification (sources need the binding map) and before the rewrite (handlers). */
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

/** Render phase: only valid once the `this` rewrite has run. */
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

function renderWatcher(ctx: Ctx, watcher: CollectedWatcher): string {
    const handler = typeof watcher.handler === 'string' ? watcher.handler : arrowText(ctx, watcher.handler);

    return `watch(${watcher.source}, ${handler}${watcher.options});`;
}

export {
    ASYNC_CREATED,
    type Collected,
    type CollectedMember,
    type CollectedWatcher,
    type OptionHandler,
    type ResolvedComposable,
    OPTION_HANDLERS,
    classifyOptions,
    collectOwnMemberNames,
    collectWatchers,
    emitsEventNames,
    renderMember,
    renderWatcher,
    resolveMixins,
};
