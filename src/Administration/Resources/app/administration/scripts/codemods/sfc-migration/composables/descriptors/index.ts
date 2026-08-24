/**
 * @sw-package framework
 * @private
 */

/**
 * The registration point of the conversion table: every descriptor the codemod knows, in one array.
 * Each descriptor lives in descriptors/<id>.ts, named after its own `id`, so supporting another mixin
 * is one new file plus one line here — a descriptor file nothing imports here converts nothing while
 * looking supported, which is what the drift guard in mixin-composables.spec.ts fails on.
 */

import { type ComposableDescriptor } from '../types';
import CMS_ELEMENT_DESCRIPTOR from './cms-element';
import CMS_STATE_DESCRIPTOR from './cms-state';
import LISTING_DESCRIPTOR from './listing';
import MEDIA_GRID_LISTENER_DESCRIPTOR from './media-grid-listener';
import MEDIA_SIDEBAR_MODAL_DESCRIPTOR from './media-sidebar-modal';
import NOTIFICATION_DESCRIPTOR from './notification';
import NOTIFICATION_TRANSLATION_DESCRIPTOR from './notification-translation';
import PLACEHOLDER_DESCRIPTOR from './placeholder';
import POSITION_DESCRIPTOR from './position';
import RULE_BETWEEN_OPERATOR_DESCRIPTOR from './rule-between-operator';
import RULE_CONTAINER_DESCRIPTOR from './rule-container';
import SALUTATION_DESCRIPTOR from './salutation';
import SW_INLINE_SNIPPET_DESCRIPTOR from './sw-inline-snippet';
import TRANSLATE_WITH_FALLBACK_DESCRIPTOR from './translate-with-fallback';
import USER_SETTINGS_DESCRIPTOR from './user-settings';
import VALIDATION_DESCRIPTOR from './validation';
import VIDEO_COVER_DESCRIPTOR from './video-cover';

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

export { COMPOSABLE_DESCRIPTORS };
