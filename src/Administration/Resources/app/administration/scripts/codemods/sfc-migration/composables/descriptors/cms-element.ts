/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';
import { CMS_STATE_INTERNAL_MEMBERS, CMS_STATE_MEMBERS } from './cms-state';

const CMS_ELEMENT_DESCRIPTOR: ComposableDescriptor = {
    id: 'cms-element',
    mixinNames: ['cms-element'],
    import: {
        source: 'src/app/composables/use-cms-element-deprecated',
        name: 'useCmsElementDeprecated',
    },
    members: {
        ...CMS_STATE_MEMBERS,
        ...refMembers([
            'cmsElements',
        ]),
        ...methodMembers([
            'initElementConfig',
            'initBaseConfig',
            'applyContentOverride',
            'initElementData',
            'getDemoValue',
        ]),
    },
    // initElementConfig runs the other two, both config steps look the element type up in the
    // registry, and the override reads the inherited config of the content entity.
    internallyReferencedMembers: [
        ...CMS_STATE_INTERNAL_MEMBERS,
        'inheritedSlotConfig',
        'cmsElements',
        'initBaseConfig',
        'applyContentOverride',
    ],
    // The service the mixin injected, which the composable resolves itself.
    unmappedMembers: [
        'cmsService',
    ],
    propArgs: [
        'element',
        'defaultConfig',
    ],
    // `disabled` is the third prop the mixin declared, which its own logic never read.
    providedProps: [
        { name: 'element', definition: '{\ntype: Object,\nrequired: true,\n}' },
        { name: 'defaultConfig', definition: '{\ntype: Object,\nrequired: false,\ndefault: null,\n}' },
        { name: 'disabled', definition: '{\ntype: Boolean,\nrequired: false,\ndefault: false,\n}' },
    ],
    scaffold: {
        checks: [
            'the config writes still reach the element object itself, which is the slot the cmsPage store shares with the rest of the editor',
            'the cmsElements registry and getDemoValue resolve the cms service the same way the injection did',
            'the defaults are merged at the point in the lifecycle the component expects them',
            'useCmsElementDeprecated is a stopover: useCmsElement routes the same writes through the cmsPage store',
        ],
        forcesPartial: true,
    },
};

export default CMS_ELEMENT_DESCRIPTOR;
