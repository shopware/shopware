/**
 * @sw-package framework
 * @private
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
