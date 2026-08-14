/**
 * @sw-package framework
 */

/**
 * The mixin conversion table: one descriptor per mixin that has a composable equivalent.
 *
 * A descriptor answers everything the `mixins` handler needs to know — which registered mixin names
 * it covers, which composable replaces them, and which `this.<member>` accesses that composable
 * answers. Member kinds mirror the `MemberKind` tiers of tables.ts, so mixin members flow through
 * the same `ctx.bindings` rewrite as a component's own members instead of a second mechanism.
 *
 * The two safety flags carry the cases where a composable is not a drop-in for the mixin's `this`
 * semantics. Both refuse the component rather than emitting something that compiles and behaves
 * differently, which is also why a mixin without a descriptor keeps the whole component on the
 * Options API: a half-converted `mixins` array has no safe meaning.
 *
 * Composables are default exports, following src/app/composables/, so `import.name` is the local
 * binding of a default import rather than a named one.
 */

/** `ref` appends `.value` on rewrite; `value` and `method` use the binding as written. */
type ComposableMemberKind = 'value' | 'ref' | 'method';

type ComposableMember = {
    kind: ComposableMemberKind;
    /** Property of the composable's return value, when it differs from the `this.<member>` key. */
    sourceKey?: string;
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

const COMPOSABLE_DESCRIPTORS: ComposableDescriptor[] = [
    {
        id: 'notification',
        mixinNames: ['notification'],
        import: { source: 'src/app/composables/use-notification', name: 'useNotification' },
        members: methodMembers([
            'createNotification',
            'createNotificationSuccess',
            'createNotificationInfo',
            'createNotificationWarning',
            'createNotificationError',
            'createSystemNotificationSuccess',
            'createSystemNotificationInfo',
            'createSystemNotificationWarning',
            'createSystemNotificationError',
            'createSystemNotification',
        ]),
        // Every create* helper routes through createNotification.
        internallyReferencedMembers: [
            'createNotification',
        ],
    },
    {
        id: 'salutation',
        mixinNames: ['salutation'],
        import: { source: 'src/app/composables/use-salutation', name: 'useSalutation' },
        members: methodMembers([
            'salutation',
        ]),
        unmappedMembers: [
            'salutationFilter',
        ],
    },
];

/** The descriptor covering a mixin registered under `name`, if one exists. */
function findComposableDescriptor(name: string): ComposableDescriptor | undefined {
    return COMPOSABLE_DESCRIPTORS.find((descriptor) => descriptor.mixinNames.includes(name));
}

export {
    type ComposableDescriptor,
    type ComposableMember,
    type ComposableMemberKind,
    COMPOSABLE_DESCRIPTORS,
    findComposableDescriptor,
};
