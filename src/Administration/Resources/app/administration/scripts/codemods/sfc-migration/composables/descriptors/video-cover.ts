/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';

const VIDEO_COVER_DESCRIPTOR: ComposableDescriptor = {
    id: 'video-cover',
    mixinNames: ['video-cover'],
    import: { source: 'src/app/composables/use-video-cover', name: 'useVideoCover' },
    members: {
        ...refMembers([
            'showCoverSelectionModal',
            'isVideoMedia',
            'hasVideoCover',
        ]),
        ...methodMembers([
            'openCoverSelectionModal',
            'closeCoverSelectionModal',
            'onCoverSelectionChange',
            'persistCoverMedia',
            'isImage',
            'isVideo',
            'removeVideoCover',
            'getCoverMediaId',
        ]),
    },
    internallyReferencedMembers: [
        'showCoverSelectionModal',
        'isVideoMedia',
        'closeCoverSelectionModal',
        'persistCoverMedia',
        'isImage',
        'isVideo',
        'getCoverMediaId',
    ],
    // The mixin injected both for its own use; the composable resolves them itself.
    unmappedMembers: [
        'acl',
        'mediaService',
    ],
    propArgs: ['item'],
};

export default VIDEO_COVER_DESCRIPTOR;
