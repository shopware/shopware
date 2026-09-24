/**
 * @sw-package framework
 * @private
 */

/** `ref` appends `.value` on rewrite; `value` and `method` use the binding as written. */
type ComposableMemberKind = 'value' | 'ref' | 'method';

type ComposableMember = {
    kind: ComposableMemberKind;
    /** Property of the composable's return value, when it differs from the `this.<member>` key. */
    sourceKey?: string;
};

/** A prop the mixin declared; a composable cannot, so it is merged into the component's `defineProps`. */
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
 * A mixin that was a controller rather than a helper: it owned the state a component worked against,
 * and often a lifecycle and a member the component implemented. Its output is always a draft.
 */
type ComposableScaffold = {
    /** The member the mixin called on its host; its composable runs even when nothing is read back. */
    iocMember?: string;
    /** `data()` keys that only configured the mixin; they move into the composable's options. */
    configKeys?: string[];
    /** What the reviewer of the draft has to check, listed in the summary TODO. */
    checks: string[];
};

type ComposableDescriptor = {
    id: string;
    /** Matches `Mixin.getByName('x')` and the bare string form `mixins: ['x']` alike. */
    mixinNames: string[];
    import: { source: string; name: string };
    /** Keyed by the `this.<member>` access the descriptor answers. */
    members: Record<string, ComposableMember>;
    /** Members the composable calls internally, so a component override of one is refused. */
    internallyReferencedMembers?: string[];
    /** Mixin members the composable inlines; reading one is refused unless the component shadows it. */
    unmappedMembers?: string[];
    /** The events the mixin emitted, keyed by the callback the composable takes for each. */
    emits?: Record<string, string>;
    /** Props the mixin read, passed as getters; a component not declaring one is refused. */
    propArgs?: string[];
    /** Members the mixin expected its host to define, passed into the options object. */
    callbackArgs?: ComposableCallback[];
    /** Merged for every declared mixin, whether its composable is called or not. */
    providedProps?: ComposableProvidedProp[];
    scaffold?: ComposableScaffold;
};

/** Members that are plain methods on both sides — the common case. */
function methodMembers(names: string[]): Record<string, ComposableMember> {
    return Object.fromEntries(names.map((name) => [name, { kind: 'method' as const }]));
}

/** Members a mixin held as reactive state or a computed, which a composable returns as a ref. */
function refMembers(names: string[]): Record<string, ComposableMember> {
    return Object.fromEntries(names.map((name) => [name, { kind: 'ref' as const }]));
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
