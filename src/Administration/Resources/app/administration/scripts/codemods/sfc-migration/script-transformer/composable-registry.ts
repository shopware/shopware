import type { IdentifierToken } from './identifier-template';
import { ident } from './identifier-template';
import { attrsIdent, routeIdent, routerIdent, slotsIdent, tIdent } from './identifiers';

/**
 * The single declarative configuration layer that maps a `this.<member>` access
 * — whether a Vue global (`$router`, `$t`, …) or a member provided by a mixin
 * (`createNotificationSuccess`, …) — onto an imported composable call.
 *
 * Both globals and mixins share one descriptor shape. The codemod reads this
 * registry to: detect which composables a component uses, rewrite the matching
 * `this.<member>` accesses, and emit the imports + declarations.
 *
 * Scope boundary: the registry only owns the "import a composable and call it"
 * family. `$emit` (a defineEmits macro), `$nextTick` (a bare `vue` import with no
 * declaration), `$props` (the defineProps binding) and the placeholder/thrower
 * globals (`$el`, `$store`, `$parent`, …) are NOT composables and stay hardcoded
 * in rewrite-this.ts.
 */

/** `ref` → append `.value` when rewriting; `value`/`method` → use the binding as-is. */
export type ComposableMemberKind = 'value' | 'ref' | 'method';

export type DeclarationStyle = 'whole' | 'destructure';

export interface ComposableMemberBinding {
    /** The local setup binding this `this.<key>` rewrites to (renameable on collision). */
    ident: IdentifierToken;
    kind: ComposableMemberKind;
    /**
     * For `destructure` descriptors: the property name on the composable's return
     * value. Two `this.<key>`s can share one property (e.g. `$t` and `$tc` both map
     * to `t`). Ignored for `whole` descriptors — there the binding is the composable.
     */
    sourceKey?: string;
}

export type ComposableTrigger =
    // A global fires whenever any of its member keys is accessed via `this`.
    | { type: 'global' }
    // A mixin fires when the component opts into it in `mixins: [...]`, matched by
    // its registered name. `unmappedMembers` lists members the mixin exposes on
    // `this` that the composable does NOT provide (e.g. internal computeds); reading
    // one forces the backoff. `internallyReferencedMembers` lists members the
    // composable calls internally, so a component override of one cannot take effect
    // and must force the backoff.
    | {
          type: 'mixin';
          mixinNames: readonly string[];
          unmappedMembers?: readonly string[];
          internallyReferencedMembers?: readonly string[];
      };

/** An event the mixin emitted on the host component via `this.$emit`. */
export interface ComposableEmitDependency {
    /** Property name on the composable's options argument. */
    option: string;
    /** The event the generated callback forwards to `emit`. */
    event: string;
}

/** A prop the mixin read from the host component. */
export interface ComposablePropDependency {
    option: string;
    /** The prop the component must declare; passed as a getter so reads stay reactive. */
    prop: string;
}

/** A member the mixin expected the host component to define (an overridable). */
export interface ComposableCallbackDependency {
    option: string;
    /** The component member the generated callback reads. */
    member: string;
}

/**
 * What a mixin needs from the component instance. A composable has no `$emit`,
 * no props and no overridable members, so a mixin that uses any of them can only
 * become a composable if the descriptor declares what to hand over. The codemod
 * passes every declared dependency in one options argument:
 * `useVideoCover({ item: () => props.item })`.
 */
export interface ComposableInstanceDependencies {
    emits?: readonly ComposableEmitDependency[];
    props?: readonly ComposablePropDependency[];
    callbacks?: readonly ComposableCallbackDependency[];
}

export interface ComposableDescriptor {
    id: string;
    trigger: ComposableTrigger;
    import: { source: string; name: string };
    declarationStyle: DeclarationStyle;
    /** `whole` descriptors only: the single binding token (`const <binding> = useX()`). */
    binding?: IdentifierToken;
    /** key = the `this.<key>` access this descriptor answers. */
    members: Record<string, ComposableMemberBinding>;
    instanceDependencies?: ComposableInstanceDependencies;
}

// --- Globals -----------------------------------------------------------------
// Order matters: it fixes the import-line and declaration order in the emitted
// script (router, route, slots, attrs, i18n). slots/attrs import from 'vue' and
// merge into the shared vue import; router/route and i18n get their own lines.

const routerDescriptor: ComposableDescriptor = {
    id: 'router',
    trigger: { type: 'global' },
    import: { source: 'vue-router', name: 'useRouter' },
    declarationStyle: 'whole',
    binding: routerIdent,
    members: { $router: { ident: routerIdent, kind: 'value' } },
};

const routeDescriptor: ComposableDescriptor = {
    id: 'route',
    trigger: { type: 'global' },
    import: { source: 'vue-router', name: 'useRoute' },
    declarationStyle: 'whole',
    binding: routeIdent,
    members: { $route: { ident: routeIdent, kind: 'value' } },
};

const slotsDescriptor: ComposableDescriptor = {
    id: 'slots',
    trigger: { type: 'global' },
    import: { source: 'vue', name: 'useSlots' },
    declarationStyle: 'whole',
    binding: slotsIdent,
    members: { $slots: { ident: slotsIdent, kind: 'value' } },
};

const attrsDescriptor: ComposableDescriptor = {
    id: 'attrs',
    trigger: { type: 'global' },
    import: { source: 'vue', name: 'useAttrs' },
    declarationStyle: 'whole',
    binding: attrsIdent,
    members: { $attrs: { ident: attrsIdent, kind: 'value' } },
};

const i18nDescriptor: ComposableDescriptor = {
    id: 'i18n',
    trigger: { type: 'global' },
    import: { source: 'vue-i18n', name: 'useI18n' },
    declarationStyle: 'destructure',
    // `$t` and `$tc` both resolve to the single destructured `t` binding.
    members: {
        $t: { ident: tIdent, kind: 'value', sourceKey: 't' },
        $tc: { ident: tIdent, kind: 'value', sourceKey: 't' },
    },
};

export const GLOBAL_DESCRIPTORS: readonly ComposableDescriptor[] = [
    routerDescriptor,
    routeDescriptor,
    slotsDescriptor,
    attrsDescriptor,
    i18nDescriptor,
];

// --- Mixins ------------------------------------------------------------------
// A mixin descriptor lists the members the mixin exposes on `this`, each mapped
// to a destructured binding from the composable. `mixinNames` matches
// `Shopware.Mixin.getByName('<name>')`.

/** Build a destructured-members map from a list of pure-method member names. */
function methodMembers(names: readonly string[]): Record<string, ComposableMemberBinding> {
    return Object.fromEntries(
        names.map((name) => [name, { ident: ident(name), kind: 'method' as const, sourceKey: name }]),
    );
}

/**
 * Build a destructured-members map for members the composable returns as refs —
 * the mixin's `data()` entries and computeds. Reads and writes are rewritten to
 * `<binding>.value`.
 */
function refMembers(names: readonly string[]): Record<string, ComposableMemberBinding> {
    return Object.fromEntries(names.map((name) => [name, { ident: ident(name), kind: 'ref' as const, sourceKey: name }]));
}

const notificationDescriptor: ComposableDescriptor = {
    id: 'notification',
    trigger: {
        type: 'mixin',
        mixinNames: ['notification'],
        // The create* helpers call `createNotification` internally, so a component
        // that overrides it would be silently ignored by the composable.
        internallyReferencedMembers: ['createNotification'],
    },
    import: { source: 'src/app/composables/use-notification', name: 'useNotification' },
    declarationStyle: 'destructure',
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
};

const placeholderDescriptor: ComposableDescriptor = {
    id: 'placeholder',
    trigger: { type: 'mixin', mixinNames: ['placeholder'] },
    import: { source: 'src/app/composables/use-placeholder', name: 'usePlaceholder' },
    declarationStyle: 'destructure',
    members: methodMembers(['placeholder']),
};

const inlineSnippetDescriptor: ComposableDescriptor = {
    id: 'sw-inline-snippet',
    trigger: {
        type: 'mixin',
        mixinNames: ['sw-inline-snippet'],
        // The mixin's locale computeds have no composable equivalent; a component
        // that reads them directly must keep the Options-API backoff.
        unmappedMembers: [
            'swInlineSnippetLocale',
            'swInlineSnippetFallbackLocale',
        ],
    },
    import: { source: 'src/app/composables/use-inline-snippet', name: 'useInlineSnippet' },
    declarationStyle: 'destructure',
    members: methodMembers(['getInlineSnippet']),
};

const salutationDescriptor: ComposableDescriptor = {
    id: 'salutation',
    trigger: {
        type: 'mixin',
        mixinNames: ['salutation'],
        // `salutationFilter` is the mixin's internal computed; the composable
        // inlines it and does not expose it, so a direct read must back off.
        unmappedMembers: ['salutationFilter'],
    },
    import: { source: 'src/app/composables/use-salutation', name: 'useSalutation' },
    declarationStyle: 'destructure',
    members: methodMembers(['salutation']),
};

const translateWithFallbackDescriptor: ComposableDescriptor = {
    id: 'translate-with-fallback',
    trigger: { type: 'mixin', mixinNames: ['translate-with-fallback'] },
    import: { source: 'src/app/composables/use-translate-with-fallback', name: 'useTranslateWithFallback' },
    declarationStyle: 'destructure',
    members: methodMembers(['tWithFallback']),
};

const positionDescriptor: ComposableDescriptor = {
    id: 'position',
    trigger: {
        type: 'mixin',
        mixinNames: ['position'],
        // lower/raisePositionValue call changePosition, and getSibling calls
        // getSiblingIndex, internally — a component override of those would be
        // ignored by the composable, so back off.
        internallyReferencedMembers: ['changePosition', 'getSiblingIndex'],
    },
    import: { source: 'src/app/composables/use-position', name: 'usePosition' },
    declarationStyle: 'destructure',
    members: methodMembers([
        'getNewPosition',
        'lowerPositionValue',
        'raisePositionValue',
        'changePosition',
        'getSiblingIndex',
        'getSibling',
        'renumberPositions',
    ]),
};

const notificationTranslationDescriptor: ComposableDescriptor = {
    id: 'notification-translation',
    trigger: { type: 'mixin', mixinNames: ['notification-translation'] },
    import: {
        source: 'src/app/composables/use-notification-translation',
        name: 'useNotificationTranslation',
    },
    declarationStyle: 'destructure',
    members: methodMembers(['getTranslatedTitle', 'getTranslatedMessage']),
};

const userSettingsDescriptor: ComposableDescriptor = {
    id: 'user-settings',
    trigger: {
        type: 'mixin',
        mixinNames: ['user-settings'],
        // get/saveUserSettings call getUserSettingsEntity internally.
        internallyReferencedMembers: ['getUserSettingsEntity'],
        // Computeds the mixin exposed but the composable inlines and does not
        // provide. Reading one backs off — unless the component declares its own
        // member of that name (the shadow check in resolveComponentMixins), so a
        // component with its own `currentUser` still migrates.
        unmappedMembers: ['currentUser', 'userConfigRepository'],
    },
    import: { source: 'src/app/composables/use-user-settings', name: 'useUserSettings' },
    declarationStyle: 'destructure',
    members: methodMembers(['getUserSettingsEntity', 'getUserSettings', 'saveUserSettings', 'userGridSettingsCriteria']),
};

const cmsStateDescriptor: ComposableDescriptor = {
    id: 'cms-state',
    trigger: {
        type: 'mixin',
        mixinNames: ['cms-state'],
        // The store-backed computeds and contentEntity feed each other inside the
        // composable, so a component override of one would not take effect.
        internallyReferencedMembers: [
            'cmsPageState',
            'category',
            'product',
            'landingPage',
            'contentEntity',
            'getSlotConfigForLanguage',
        ],
    },
    import: { source: 'src/module/sw-cms/composables/use-cms-state', name: 'useCmsState' },
    declarationStyle: 'destructure',
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
        ...methodMembers(['getSlotConfigForLanguage']),
    },
};

const mediaSidebarModalDescriptor: ComposableDescriptor = {
    id: 'media-sidebar-modal',
    trigger: {
        type: 'mixin',
        mixinNames: ['media-sidebar-modal-mixin'],
        // The delete/dissolve/move handlers close their own modal first.
        internallyReferencedMembers: [
            'closeModalDelete',
            'closeFolderDissolve',
            'closeModalMove',
        ],
    },
    import: { source: 'src/module/sw-media/composables/use-media-sidebar-modal', name: 'useMediaSidebarModal' },
    declarationStyle: 'destructure',
    instanceDependencies: {
        emits: [
            { option: 'onItemsDelete', event: 'media-sidebar-items-delete' },
            { option: 'onFolderItemsDissolve', event: 'media-sidebar-folder-items-dissolve' },
            { option: 'onItemsMove', event: 'media-sidebar-items-move' },
        ],
    },
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
};

const videoCoverDescriptor: ComposableDescriptor = {
    id: 'video-cover',
    trigger: {
        type: 'mixin',
        mixinNames: ['video-cover'],
        // The cover computeds and persist flow call these helpers internally.
        internallyReferencedMembers: [
            'showCoverSelectionModal',
            'isVideoMedia',
            'isVideo',
            'isImage',
            'getCoverMediaId',
            'closeCoverSelectionModal',
            'persistCoverMedia',
        ],
    },
    import: { source: 'src/module/sw-media/composables/use-video-cover', name: 'useVideoCover' },
    declarationStyle: 'destructure',
    instanceDependencies: {
        props: [{ option: 'item', prop: 'item' }],
    },
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
};

const mediaGridListenerDescriptor: ComposableDescriptor = {
    id: 'media-grid-listener',
    trigger: {
        type: 'mixin',
        mixinNames: ['media-grid-listener'],
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
        // The mixin's underscore-prefixed selection internals stay private to the
        // composable, so a component that calls one directly must back off.
        unmappedMembers: [
            '_singleSelect',
            '_startListSelect',
            '_handleSelection',
            '_removeItemFromSelection',
            '_addItemToSelection',
            '_handleShiftSelect',
            '_findSelectionIndices',
        ],
    },
    import: { source: 'src/module/sw-media/composables/use-media-grid-listener', name: 'useMediaGridListener' },
    declarationStyle: 'destructure',
    instanceDependencies: {
        emits: [{ option: 'onFolderChange', event: 'media-folder-change' }],
        // The mixin declared an empty `selectableItems` computed purely so the host
        // could override it; the composable takes it as a callback instead.
        callbacks: [{ option: 'selectableItems', member: 'selectableItems' }],
    },
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
};

export const MIXIN_DESCRIPTORS: readonly ComposableDescriptor[] = [
    notificationDescriptor,
    placeholderDescriptor,
    inlineSnippetDescriptor,
    salutationDescriptor,
    translateWithFallbackDescriptor,
    positionDescriptor,
    notificationTranslationDescriptor,
    userSettingsDescriptor,
    cmsStateDescriptor,
    mediaSidebarModalDescriptor,
    videoCoverDescriptor,
    mediaGridListenerDescriptor,
];

export const COMPOSABLE_REGISTRY: readonly ComposableDescriptor[] = [
    ...GLOBAL_DESCRIPTORS,
    ...MIXIN_DESCRIPTORS,
];

/** The global descriptor that answers a given `this.<key>` access, if any. */
export function findGlobalDescriptorByThisKey(key: string): ComposableDescriptor | undefined {
    return GLOBAL_DESCRIPTORS.find((descriptor) => key in descriptor.members);
}

/** Every `this.<key>` the global descriptors answer (e.g. `$router`, `$t`, `$tc`). */
export function globalThisKeys(): string[] {
    return GLOBAL_DESCRIPTORS.flatMap((descriptor) => Object.keys(descriptor.members));
}

/** The mixin descriptor registered under `Shopware.Mixin.getByName('<name>')`. */
export function findMixinDescriptorByName(name: string): ComposableDescriptor | undefined {
    return MIXIN_DESCRIPTORS.find(
        (descriptor) => descriptor.trigger.type === 'mixin' && descriptor.trigger.mixinNames.includes(name),
    );
}
