/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const PLACEHOLDER_DESCRIPTOR: ComposableDescriptor = {
    id: 'placeholder',
    legacyCompatible: true,
    mixinNames: ['placeholder'],
    import: { source: 'src/app/composables/use-placeholder', name: 'usePlaceholder' },
    members: methodMembers([
        'placeholder',
    ]),
};

export default PLACEHOLDER_DESCRIPTOR;
