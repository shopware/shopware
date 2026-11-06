/**
 * @sw-package framework
 */

import {
    componentMembers,
    componentReference,
    jsApiMigration,
    memberUsage,
    storeMember,
    stringValue,
    upgradeGuide,
} from '../helpers';

export default jsApiMigration({
    id: 'js-api.product-advanced-mode',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The product detail advanced mode is removed without replacement. Show your card without showProductCard().',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-the-product-detail-advanced-mode')],
    usage: [
        ...componentMembers({
            components: ['sw-product-detail'],
            members: [
                'advancedModeSetting',
                'getModeSettingGeneralTab',
                'getModeSettingSpecificationsTab',
                'showAdvanceModeSetting',
                'initAdvancedModeSettings',
                'createUserModeSetting',
                'getAdvancedModeDefaultSetting',
                'getAdvancedModeSetting',
                'saveAdvancedMode',
                'onChangeSetting',
                'changeModeSettings',
                'onChangeSettingItem',
                'advanceModeEnabled',
            ],
        }),
        componentReference({ component: 'sw-product-settings-mode' }),
        storeMember({ store: 'swProductDetail', member: 'advancedModeSetting' }),
        storeMember({ store: 'swProductDetail', member: 'showModeSetting' }),
        storeMember({ store: 'swProductDetail', member: 'advanceModeEnabled' }),
        memberUsage({ from: '*.advanceModeEnabled' }),
        stringValue({ from: 'mode.setting.advancedModeSettings' }),
    ],
});
