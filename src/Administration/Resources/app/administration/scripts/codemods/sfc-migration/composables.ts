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
 * A mixin that reaches into the instance — `$emit`, a prop it read, a method it expected the host to
 * define — declares that as `emits`, `propArgs` and `callbackArgs`. The composable takes all three as
 * one options object; the codemod fills it in from what the component declares and refuses the
 * component when it declares none of it. What the mixin gave the instance instead of taking from it —
 * its own `props` option — travels the other way, as `providedProps`.
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
 * A mixin that was an abstract controller rather than a helper: it owned reactive state and lifecycle
 * and drove a member the component implemented. Such a composable can be wired up mechanically, but
 * not proven equivalent, so its output is always a draft for a human to finish.
 */
type ComposableScaffold = {
    /** The member the mixin called on its host, which the composable takes as a callback instead. */
    iocMember: string;
    /**
     * State keys a component set in its own `data()` purely to configure the mixin. They reach the
     * composable through its options object instead of staying local refs.
     */
    configKeys: string[];
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

const COMPOSABLE_DESCRIPTORS: ComposableDescriptor[] = [
    {
        id: 'cms-state',
        mixinNames: ['cms-state'],
        import: { source: 'src/app/composables/use-cms-state', name: 'useCmsState' },
        members: {
            ...refMembers([
                'cmsPageState',
                'selectedBlock',
                'selectedSection',
                'currentDeviceView',
                'isSystemDefaultLanguage',
                'category',
                'product',
                'landingPage',
                'contentEntity',
                'inheritedSlotConfig',
            ]),
            ...methodMembers([
                'getSlotConfigForLanguage',
            ]),
        },
        // The store every other member reads, the entity chain behind contentEntity, and the lookup
        // inheritedSlotConfig merges through.
        internallyReferencedMembers: [
            'cmsPageState',
            'category',
            'product',
            'landingPage',
            'contentEntity',
            'getSlotConfigForLanguage',
        ],
    },
    {
        id: 'listing',
        mixinNames: ['listing'],
        import: { source: 'src/app/composables/use-listing', name: 'useListing' },
        members: {
            ...refMembers([
                'page',
                'limit',
                'total',
                'sortBy',
                'sortDirection',
                'naturalSorting',
                'selection',
                'term',
                'disableRouteParams',
                'searchConfigEntity',
                'entitySearchable',
                'freshSearchTerm',
                'previousRouteName',
                'storeKey',
                'filterCriteria',
                'maxPage',
                'routeName',
                'selectionArray',
                'selectionCount',
                'searchRankingFields',
                'currentSortBy',
            ]),
            ...methodMembers([
                'updateData',
                'updateRoute',
                'resetListing',
                'getMainListingParams',
                'updateSelection',
                'onPageChange',
                'onSearch',
                'onSwitchFilter',
                'onSort',
                'onSortColumn',
                'onRefresh',
                'isValidTerm',
                'addQueryScores',
                'parseBooleanQueryParams',
                'updateCriteria',
            ]),
        },
        // The whole listing state, which the route watcher, the lifecycle hook and every on* handler
        // read back, plus the four methods they route through.
        internallyReferencedMembers: [
            'page',
            'limit',
            'total',
            'sortBy',
            'sortDirection',
            'naturalSorting',
            'selection',
            'term',
            'disableRouteParams',
            'searchConfigEntity',
            'entitySearchable',
            'freshSearchTerm',
            'previousRouteName',
            'storeKey',
            'filterCriteria',
            'selectionArray',
            'updateData',
            'updateRoute',
            'resetListing',
            'isValidTerm',
            'parseBooleanQueryParams',
        ],
        // The two services the mixin injected, which the composable resolves itself, and the `filters`
        // computed it defaulted to an empty list — a component that reads one without declaring it
        // would read nothing after the migration.
        unmappedMembers: [
            'feature',
            'searchRankingService',
            'filters',
        ],
        // `filters` was the mixin's own computed and the component's override at once, so it arrives as
        // an optional getter: a component without filters keeps the mixin's empty list.
        callbackArgs: [
            { name: 'filters', kind: 'getter', optional: true },
        ],
        scaffold: {
            iocMember: 'getList',
            configKeys: [
                'page',
                'limit',
                'total',
                'sortBy',
                'sortDirection',
                'naturalSorting',
                'selection',
                'term',
                'disableRouteParams',
                'searchConfigEntity',
                'entitySearchable',
                'freshSearchTerm',
                'storeKey',
                'filterCriteria',
            ],
            checks: [
                'getList() is passed to useListing() and still resolves everything it reads and writes',
                'the initial load runs on mounted now, one hook later than the mixin loaded it',
                'route parameter handling, which the composable owns from here on',
            ],
            forcesPartial: true,
        },
    },
    {
        id: 'media-grid-listener',
        mixinNames: ['media-grid-listener'],
        import: { source: 'src/app/composables/use-media-grid-listener', name: 'useMediaGridListener' },
        members: {
            ...refMembers([
                'selectedItems',
                'listSelectionStartItem',
                'mediaItemSelectionHandler',
                'isListSelect',
            ]),
            ...methodMembers([
                'isItemSelected',
                'showItemSelected',
                'clearSelection',
                'navigateToFolder',
                'showDetails',
                'handleMediaItemClicked',
                'handleMediaGridItemSelected',
                'handleMediaGridItemUnselected',
            ]),
        },
        internallyReferencedMembers: [
            'selectedItems',
            'listSelectionStartItem',
            'isListSelect',
            'isItemSelected',
            'navigateToFolder',
            'handleMediaItemClicked',
            'handleMediaGridItemSelected',
            'handleMediaGridItemUnselected',
        ],
        // The mixin's selection bookkeeping, which the composable keeps to itself. `showDetails` is the
        // public equivalent of `_singleSelect`.
        unmappedMembers: [
            '_singleSelect',
            '_startListSelect',
            '_handleSelection',
            '_removeItemFromSelection',
            '_addItemToSelection',
            '_handleShiftSelect',
            '_findSelectionIndices',
        ],
        emits: {
            onFolderChange: 'media-folder-change',
        },
        // The mixin's own `selectableItems` computed returned an empty list; a range selection only
        // works against the host's.
        callbackArgs: [
            { name: 'selectableItems', kind: 'getter' },
        ],
    },
    {
        id: 'media-sidebar-modal',
        mixinNames: ['media-sidebar-modal-mixin'],
        import: { source: 'src/app/composables/use-media-sidebar-modal', name: 'useMediaSidebarModal' },
        members: {
            ...refMembers([
                'showModalReplace',
                'showModalDelete',
                'showFolderSettings',
                'showFolderDissolve',
                'showModalMove',
            ]),
            ...methodMembers([
                'openModalReplace',
                'closeModalReplace',
                'openModalDelete',
                'closeModalDelete',
                'openFolderSettings',
                'closeFolderSettings',
                'openFolderDissolve',
                'closeFolderDissolve',
                'openModalMove',
                'closeModalMove',
                'deleteSelectedItems',
                'onFolderDissolved',
                'onFolderMoved',
            ]),
        },
        // The three handlers close their own modal before emitting; every open/close writes its flag.
        internallyReferencedMembers: [
            'showModalReplace',
            'showModalDelete',
            'showFolderSettings',
            'showFolderDissolve',
            'showModalMove',
            'closeModalDelete',
            'closeFolderDissolve',
            'closeModalMove',
        ],
        // The mixin injected both for its own permission checks; the composable resolves them itself.
        unmappedMembers: [
            'acl',
            'mediaService',
        ],
        emits: {
            onItemsDelete: 'media-sidebar-items-delete',
            onFolderItemsDissolve: 'media-sidebar-folder-items-dissolve',
            onItemsMove: 'media-sidebar-items-move',
        },
    },
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
        id: 'notification-translation',
        mixinNames: ['notification-translation'],
        import: {
            source: 'src/app/composables/use-notification-translation',
            name: 'useNotificationTranslation',
        },
        members: methodMembers([
            'getTranslatedTitle',
            'getTranslatedMessage',
        ]),
    },
    {
        id: 'placeholder',
        mixinNames: ['placeholder'],
        import: { source: 'src/app/composables/use-placeholder', name: 'usePlaceholder' },
        members: methodMembers([
            'placeholder',
        ]),
    },
    {
        id: 'position',
        mixinNames: ['position'],
        import: { source: 'src/app/composables/use-position', name: 'usePosition' },
        members: methodMembers([
            'getNewPosition',
            'lowerPositionValue',
            'raisePositionValue',
            'changePosition',
            'getSiblingIndex',
            'getSibling',
            'renumberPositions',
        ]),
        // lower/raisePositionValue swap through changePosition, getSibling through getSiblingIndex.
        internallyReferencedMembers: [
            'changePosition',
            'getSiblingIndex',
        ],
    },
    {
        id: 'rule-between-operator',
        mixinNames: ['rule-between-operator'],
        import: { source: 'src/app/composables/use-rule-between-operator', name: 'useRuleBetweenOperator' },
        members: refMembers([
            'isBetween',
            'betweenValue',
        ]),
        propArgs: ['condition'],
        // The mixin created the condition's value through the host before writing the pair back.
        callbackArgs: [
            { name: 'ensureValueExist', kind: 'callback' },
        ],
    },
    {
        id: 'rule-container',
        mixinNames: ['ruleContainer'],
        import: { source: 'src/app/composables/use-rule-container', name: 'useRuleContainer' },
        members: {
            ...refMembers([
                'conditionDataProviderService',
                'childAssociationField',
                'containerRowClass',
                'nextPosition',
            ]),
            ...methodMembers([
                'createCondition',
                'insertNodeIntoTree',
                'removeNodeFromTree',
            ]),
        },
        // nextPosition counts the children under the provided association field, and the watcher reads
        // it back before asking for a placeholder.
        internallyReferencedMembers: [
            'childAssociationField',
            'nextPosition',
        ],
        propArgs: [
            'condition',
            'level',
            'disabled',
        ],
        callbackArgs: [
            { name: 'onAddPlaceholder', kind: 'callback' },
        ],
        // `parentCondition` is the fourth prop the mixin declared, which its own logic never read.
        providedProps: [
            { name: 'condition', definition: '{\ntype: Object,\nrequired: true,\n}' },
            { name: 'parentCondition', definition: '{\ntype: Object,\nrequired: false,\ndefault: null,\n}' },
            { name: 'level', definition: '{\ntype: Number,\nrequired: true,\n}' },
            { name: 'disabled', definition: '{\ntype: Boolean,\nrequired: false,\ndefault: false,\n}' },
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
    {
        id: 'sw-inline-snippet',
        mixinNames: ['sw-inline-snippet'],
        import: { source: 'src/app/composables/use-inline-snippet', name: 'useInlineSnippet' },
        members: methodMembers([
            'getInlineSnippet',
        ]),
        unmappedMembers: [
            'swInlineSnippetLocale',
            'swInlineSnippetFallbackLocale',
        ],
    },
    {
        id: 'translate-with-fallback',
        mixinNames: ['translate-with-fallback'],
        import: {
            source: 'src/app/composables/use-translate-with-fallback',
            name: 'useTranslateWithFallback',
        },
        members: methodMembers([
            'tWithFallback',
        ]),
    },
    {
        id: 'user-settings',
        mixinNames: ['user-settings'],
        import: { source: 'src/app/composables/use-user-settings', name: 'useUserSettings' },
        members: methodMembers([
            'getUserSettingsEntity',
            'getUserSettings',
            'saveUserSettings',
            'userGridSettingsCriteria',
        ]),
        // get/saveUserSettings read through getUserSettingsEntity, which builds its own criteria.
        internallyReferencedMembers: [
            'getUserSettingsEntity',
            'userGridSettingsCriteria',
        ],
        // The mixin's own computeds, plus the `acl` it injected for the component; the composable
        // resolves all three itself and returns none of them.
        unmappedMembers: [
            'acl',
            'currentUser',
            'userConfigRepository',
        ],
    },
    {
        id: 'validation',
        mixinNames: ['validation'],
        import: { source: 'src/app/composables/use-validation', name: 'useValidation' },
        members: {
            validationService: { kind: 'value' },
            ...methodMembers([
                'validate',
                'validateRule',
            ]),
        },
        internallyReferencedMembers: ['validateRule'],
        // The mixin's computed read the host's current value under whichever of `currentValue`, `value`
        // or `selections` existed, a name the composable cannot know; its callers pass the value to
        // `validate()` instead.
        unmappedMembers: ['isValid'],
        propArgs: ['validation'],
        providedProps: [
            {
                name: 'validation',
                definition: '{\ntype: [String, Array, Object, Boolean],\nrequired: false,\ndefault: null,\n}',
            },
        ],
    },
    {
        id: 'video-cover',
        mixinNames: ['video-cover'],
        import: { source: 'src/app/composables/use-video-cover', name: 'useVideoCover' },
        members: {
            ...refMembers([
                'showCoverSelectionModal',
                'isVideoMedia',
                'hasVideoCover',
            ]),
            ...methodMembers([
                'openCoverSelectionModal',
                'closeCoverSelectionModal',
                'onCoverSelectionChange',
                'persistCoverMedia',
                'isImage',
                'isVideo',
                'removeVideoCover',
                'getCoverMediaId',
            ]),
        },
        internallyReferencedMembers: [
            'showCoverSelectionModal',
            'isVideoMedia',
            'closeCoverSelectionModal',
            'persistCoverMedia',
            'isImage',
            'isVideo',
            'getCoverMediaId',
        ],
        // The mixin injected both for its own use; the composable resolves them itself.
        unmappedMembers: [
            'acl',
            'mediaService',
        ],
        propArgs: ['item'],
    },
];

/** The descriptor covering a mixin registered under `name`, if one exists. */
function findComposableDescriptor(name: string): ComposableDescriptor | undefined {
    return COMPOSABLE_DESCRIPTORS.find((descriptor) => descriptor.mixinNames.includes(name));
}

/**
 * Every member a composable takes from its host. A scaffold's inverted member is one of them: the
 * mixin called it on the instance, so the component has to define it and the composable is handed it.
 */
function composableCallbacks(descriptor: ComposableDescriptor): ComposableCallback[] {
    return [
        ...(descriptor.callbackArgs ?? []),
        ...(descriptor.scaffold ? [{ name: descriptor.scaffold.iocMember, kind: 'callback' as const }] : []),
    ];
}

export {
    type ComposableCallback,
    type ComposableCallbackKind,
    type ComposableDescriptor,
    type ComposableMember,
    type ComposableMemberKind,
    type ComposableProvidedProp,
    type ComposableScaffold,
    COMPOSABLE_DESCRIPTORS,
    composableCallbacks,
    findComposableDescriptor,
};
