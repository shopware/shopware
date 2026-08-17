/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const NOTIFICATION_DESCRIPTOR: ComposableDescriptor = {
    id: 'notification',
    mixinNames: ['notification'],
    import: { source: 'src/app/composables/use-notification', name: 'useNotification' },
    members: methodMembers([
        'createNotification',
        'createNotificationSuccess',
        'createNotificationInfo',
        'createNotificationWarning',
        'createNotificationError',
        'createSystemNotificationSuccess',
        'createSystemNotificationInfo',
        'createSystemNotificationWarning',
        'createSystemNotificationError',
        'createSystemNotification',
    ]),
    // Every create* helper routes through createNotification.
    internallyReferencedMembers: [
        'createNotification',
    ],
};

export default NOTIFICATION_DESCRIPTOR;
