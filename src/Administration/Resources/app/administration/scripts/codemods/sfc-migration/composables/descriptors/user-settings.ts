/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers } from '../types';

const USER_SETTINGS_DESCRIPTOR: ComposableDescriptor = {
    id: 'user-settings',
    mixinNames: ['user-settings'],
    import: { source: 'src/app/composables/use-user-settings', name: 'useUserSettings' },
    members: methodMembers([
        'getUserSettingsEntity',
        'getUserSettings',
        'saveUserSettings',
        'userGridSettingsCriteria',
    ]),
    // get/saveUserSettings read through getUserSettingsEntity, which builds its own criteria.
    internallyReferencedMembers: [
        'getUserSettingsEntity',
        'userGridSettingsCriteria',
    ],
    // The mixin's own computeds, plus the `acl` it injected for the component; the composable
    // resolves all three itself and returns none of them.
    unmappedMembers: [
        'acl',
        'currentUser',
        'userConfigRepository',
    ],
};

export default USER_SETTINGS_DESCRIPTOR;
