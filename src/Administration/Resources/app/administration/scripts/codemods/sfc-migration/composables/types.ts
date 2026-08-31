/**
 * @sw-package framework
 * @private
 */

/**
 * The shape of a descriptor and the two helpers its member map is written with. The registry itself is
 * assembled in descriptors/index.ts.
 */

/** `ref` appends `.value` on rewrite; `value` and `method` use the binding as written. */
type ComposableMemberKind = 'value' | 'ref' | 'method';

type ComposableMember = {
    kind: ComposableMemberKind;
    /** Property of the composable's return value, when it differs from the `this.<member>` key. */
    sourceKey?: string;
};

/**
 * A prop the mixin declared, which every component using it inherited. A composable cannot declare
 * props, so the codemod merges these into the component's own `defineProps` literal instead.
 */
type ComposableProvidedProp = {
    name: string;
    /** Source text of the prop definition, e.g. `{ type: Object, required: true }`. */
    definition: string;
};

/** A callback is invoked for its effect, a getter is read for its value. */
type ComposableCallbackKind = 'callback' | 'getter';

type ComposableCallback = {
    /** The member the mixin reached for on its host, which is also the options key. */
    name: string;
    kind: ComposableCallbackKind;
    /** The composable has a default, so a component without the member converts regardless. */
    optional?: boolean;
};

/**
 * A mixin that was an abstract controller rather than a helper: it owned the state a component worked
 * against — its own, or a prop it wrote to — and often a lifecycle and a member the component
 * implemented. Such a composable can be wired up mechanically, but not proven equivalent, so its output
 * is always a draft for a human to finish.
 */
type ComposableScaffold = {
    /**
     * The member the mixin called on its host, which the composable takes as a callback instead. A
     * mixin that drove one also owned the lifecycle that called it, which is why its composable runs
     * whether or not the component reads anything back from it.
     */
    iocMember?: string;
    /**
     * State keys a component set in its own `data()` purely to configure the mixin. They reach the
     * composable through its options object instead of staying local refs.
     */
    configKeys?: string[];
    /** What the reviewer of the draft has to check, listed in the summary TODO. */
    checks: string[];
    /** Never `full`: the codemod cannot decide the questions above. */
    forcesPartial: true;
};

type ComposableDescriptor = {
    id: string;
    /** Matches `Mixin.getByName('x')` and the bare string form `mixins: ['x']` alike. */
    mixinNames: string[];
    import: { source: string; name: string };
    /** Keyed by the `this.<member>` access the descriptor answers. */
    members: Record<string, ComposableMember>;
    /**
     * Members the composable calls internally. A component override of one cannot take effect after
     * the migration — the composable keeps calling its own copy — so the component is refused.
     */
    internallyReferencedMembers?: string[];
    /**
     * Members the mixin puts on `this` that the composable does not return, because it inlines them
     * (typically the mixin's internal computeds). Reading one is refused, unless the component
     * declares its own member of that name, which shadowed the mixin's to begin with.
     */
    unmappedMembers?: string[];
    /**
     * The events the mixin emitted, keyed by the callback the composable takes for each. The codemod
     * merges the event names into `defineEmits` and hands `emit` over through those callbacks, so the
     * composable names the intent instead of carrying event strings.
     */
    emits?: Record<string, string>;
    /**
     * Props the mixin read off the instance, passed as `() => props.<name>` getters. A component that
     * does not declare one is refused: the prop came from the mixin's own `props` option, and nothing
     * would supply it after the migration.
     */
    propArgs?: string[];
    /**
     * Members the mixin expected its host to define — the Options API's inversion of control. The
     * codemod passes the component's own member into the options object.
     */
    callbackArgs?: ComposableCallback[];
    /**
     * The props the mixin declared itself. Unlike the instance dependencies above, every declared
     * mixin contributes these whether its composable ends up being called or not — the Options API
     * merged them into the component the same way.
     */
    providedProps?: ComposableProvidedProp[];
    /**
     * Present for a mixin the codemod can only scaffold. Its composable is called even when the
     * component reads none of its members, because it is the one running the lifecycle.
     */
    scaffold?: ComposableScaffold;
};

/** Members that are plain methods on both sides — the common case. */
function methodMembers(names: string[]): Record<string, ComposableMember> {
    return Object.fromEntries(
        names.map((name) => [
            name,
            { kind: 'method' as const },
        ]),
    );
}

/** Members a mixin held as reactive state or a computed, which a composable returns as a ref. */
function refMembers(names: string[]): Record<string, ComposableMember> {
    return Object.fromEntries(
        names.map((name) => [
            name,
            { kind: 'ref' as const },
        ]),
    );
}

export {
    type ComposableCallback,
    type ComposableCallbackKind,
    type ComposableDescriptor,
    type ComposableMember,
    type ComposableMemberKind,
    type ComposableProvidedProp,
    type ComposableScaffold,
    methodMembers,
    refMembers,
};
