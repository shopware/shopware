/**
 * @sw-package framework
 * @private
 */

/**
 * The mixin conversion table: one descriptor per mixin with a composable equivalent. Its members
 * flow through the same `ctx.bindings` rewrite as a component's own. Composables are default
 * exports, so `import.name` is a default import's local binding.
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
import { COMPOSABLE_DESCRIPTORS } from './descriptors';

function findComposableDescriptor(name: string): ComposableDescriptor | undefined {
    return COMPOSABLE_DESCRIPTORS.find((descriptor) => descriptor.mixinNames.includes(name));
}

/** Every member a composable takes from its host, a scaffold's inverted member included. */
function composableCallbacks(descriptor: ComposableDescriptor): ComposableCallback[] {
    const iocMember = descriptor.scaffold?.iocMember;

    return [...(descriptor.callbackArgs ?? []), ...(iocMember ? [{ name: iocMember, kind: 'callback' as const }] : [])];
}

/** Driving a host member made the mixin a controller, whose own lifecycle keeps calling it. */
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
