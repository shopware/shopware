/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, type ComposableMember, methodMembers, refMembers } from '../types';

/**
 * The CMS editor state, which reaches a component through either of two mixins: `cms-state` itself, or
 * `cms-element`, which declared it as its own mixin. A component that names only `cms-element` still
 * read these members off its instance, so the composable behind it returns them too and its descriptor
 * repeats them here.
 */
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
    ...methodMembers([
        'getSlotConfigForLanguage',
    ]),
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
