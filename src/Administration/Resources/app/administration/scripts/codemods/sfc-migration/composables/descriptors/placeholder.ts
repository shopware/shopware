/**
 * @sw-package framework
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const PLACEHOLDER_DESCRIPTOR: ComposableDescriptor = {
    id: 'placeholder',
    mixinNames: ['placeholder'],
    import: { source: 'src/app/composables/use-placeholder', name: 'usePlaceholder' },
    members: methodMembers([
        'placeholder',
    ]),
};

export { PLACEHOLDER_DESCRIPTOR };
