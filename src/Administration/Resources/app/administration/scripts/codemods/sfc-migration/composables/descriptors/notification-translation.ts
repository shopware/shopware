/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const NOTIFICATION_TRANSLATION_DESCRIPTOR: ComposableDescriptor = {
    id: 'notification-translation',
    mixinNames: ['notification-translation'],
    import: {
        source: 'src/app/composables/use-notification-translation',
        name: 'useNotificationTranslation',
    },
    members: methodMembers([
        'getTranslatedTitle',
        'getTranslatedMessage',
    ]),
};

export default NOTIFICATION_TRANSLATION_DESCRIPTOR;
