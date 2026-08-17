/**
 * @sw-package framework
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const POSITION_DESCRIPTOR: ComposableDescriptor = {
    id: 'position',
    mixinNames: ['position'],
    import: { source: 'src/app/composables/use-position', name: 'usePosition' },
    members: methodMembers([
        'getNewPosition',
        'lowerPositionValue',
        'raisePositionValue',
        'changePosition',
        'getSiblingIndex',
        'getSibling',
        'renumberPositions',
    ]),
    // lower/raisePositionValue swap through changePosition, getSibling through getSiblingIndex.
    internallyReferencedMembers: [
        'changePosition',
        'getSiblingIndex',
    ],
};

export { POSITION_DESCRIPTOR };
