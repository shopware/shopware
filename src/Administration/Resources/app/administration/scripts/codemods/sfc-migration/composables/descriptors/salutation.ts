/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const SALUTATION_DESCRIPTOR: ComposableDescriptor = {
    id: 'salutation',
    mixinNames: ['salutation'],
    import: { source: 'src/app/composables/use-salutation', name: 'useSalutation' },
    members: methodMembers([
        'salutation',
    ]),
    unmappedMembers: [
        'salutationFilter',
    ],
};

export default SALUTATION_DESCRIPTOR;
