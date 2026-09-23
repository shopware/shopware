/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, type ComposableMember, methodMembers, refMembers } from '../types';

/** Shared with `cms-element`, which declared `cms-state` as its own mixin. */
const CMS_STATE_MEMBERS: Record<string, ComposableMember> = {
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
};

/**
 * The store every other CMS state member reads, the entity chain behind contentEntity, and the lookup
 * inheritedSlotConfig merges through.
 */
const CMS_STATE_INTERNAL_MEMBERS = [
    'cmsPageState',
    'category',
    'product',
    'landingPage',
    'contentEntity',
    'getSlotConfigForLanguage',
];

const CMS_STATE_DESCRIPTOR: ComposableDescriptor = {
    id: 'cms-state',
    mixinNames: ['cms-state'],
    import: { source: 'src/app/composables/use-cms-state', name: 'useCmsState' },
    members: CMS_STATE_MEMBERS,
    internallyReferencedMembers: CMS_STATE_INTERNAL_MEMBERS,
};

export { CMS_STATE_INTERNAL_MEMBERS, CMS_STATE_MEMBERS };

export default CMS_STATE_DESCRIPTOR;
