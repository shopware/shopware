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
 * Each descriptor lives in descriptors/<id>.ts, named after its own `id`.
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

import {
    type ComposableCallback,
    type ComposableCallbackKind,
    type ComposableDescriptor,
    type ComposableMember,
    type ComposableMemberKind,
    type ComposableProvidedProp,
    type ComposableScaffold,
} from './types';
import { CMS_ELEMENT_DESCRIPTOR } from './descriptors/cms-element';
import { CMS_STATE_DESCRIPTOR } from './descriptors/cms-state';
import { LISTING_DESCRIPTOR } from './descriptors/listing';
import { MEDIA_GRID_LISTENER_DESCRIPTOR } from './descriptors/media-grid-listener';
import { MEDIA_SIDEBAR_MODAL_DESCRIPTOR } from './descriptors/media-sidebar-modal';
import { NOTIFICATION_DESCRIPTOR } from './descriptors/notification';
import { NOTIFICATION_TRANSLATION_DESCRIPTOR } from './descriptors/notification-translation';
import { PLACEHOLDER_DESCRIPTOR } from './descriptors/placeholder';
import { POSITION_DESCRIPTOR } from './descriptors/position';
import { RULE_BETWEEN_OPERATOR_DESCRIPTOR } from './descriptors/rule-between-operator';
import { RULE_CONTAINER_DESCRIPTOR } from './descriptors/rule-container';
import { SALUTATION_DESCRIPTOR } from './descriptors/salutation';
import { SW_INLINE_SNIPPET_DESCRIPTOR } from './descriptors/sw-inline-snippet';
import { TRANSLATE_WITH_FALLBACK_DESCRIPTOR } from './descriptors/translate-with-fallback';
import { USER_SETTINGS_DESCRIPTOR } from './descriptors/user-settings';
import { VALIDATION_DESCRIPTOR } from './descriptors/validation';
import { VIDEO_COVER_DESCRIPTOR } from './descriptors/video-cover';

const COMPOSABLE_DESCRIPTORS: ComposableDescriptor[] = [
    CMS_ELEMENT_DESCRIPTOR,
    CMS_STATE_DESCRIPTOR,
    LISTING_DESCRIPTOR,
    MEDIA_GRID_LISTENER_DESCRIPTOR,
    MEDIA_SIDEBAR_MODAL_DESCRIPTOR,
    NOTIFICATION_DESCRIPTOR,
    NOTIFICATION_TRANSLATION_DESCRIPTOR,
    PLACEHOLDER_DESCRIPTOR,
    POSITION_DESCRIPTOR,
    RULE_BETWEEN_OPERATOR_DESCRIPTOR,
    RULE_CONTAINER_DESCRIPTOR,
    SALUTATION_DESCRIPTOR,
    SW_INLINE_SNIPPET_DESCRIPTOR,
    TRANSLATE_WITH_FALLBACK_DESCRIPTOR,
    USER_SETTINGS_DESCRIPTOR,
    VALIDATION_DESCRIPTOR,
    VIDEO_COVER_DESCRIPTOR,
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
    const iocMember = descriptor.scaffold?.iocMember;

    return [
        ...(descriptor.callbackArgs ?? []),
        ...(iocMember ? [{ name: iocMember, kind: 'callback' as const }] : []),
    ];
}

/**
 * A scaffold the codemod calls even when the component reads nothing from it. Driving a member of its
 * host is what made a mixin a controller: something of its own — a lifecycle hook, a watcher — did the
 * calling, and that keeps running after the migration. A scaffold that only provides members is dropped
 * like any other descriptor nothing reads.
 */
function scaffoldRunsUnread(descriptor: ComposableDescriptor): boolean {
    return descriptor.scaffold?.iocMember !== undefined;
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
    scaffoldRunsUnread,
};
