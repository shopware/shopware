/**
 * @sw-package framework
 */

import {
    componentMember,
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
        componentMember({ components: ['sw-product-detail'], member: 'advancedModeSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'getModeSettingGeneralTab' }),
        componentMember({ components: ['sw-product-detail'], member: 'getModeSettingSpecificationsTab' }),
        componentMember({ components: ['sw-product-detail'], member: 'showAdvanceModeSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'initAdvancedModeSettings' }),
        componentMember({ components: ['sw-product-detail'], member: 'createUserModeSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'getAdvancedModeDefaultSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'getAdvancedModeSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'saveAdvancedMode' }),
        componentMember({ components: ['sw-product-detail'], member: 'onChangeSetting' }),
        componentMember({ components: ['sw-product-detail'], member: 'changeModeSettings' }),
        componentMember({ components: ['sw-product-detail'], member: 'onChangeSettingItem' }),
        componentMember({ components: ['sw-product-detail'], member: 'advanceModeEnabled' }),
        componentReference({ component: 'sw-product-settings-mode' }),
        storeMember({ store: 'swProductDetail', member: 'advancedModeSetting' }),
        storeMember({ store: 'swProductDetail', member: 'showModeSetting' }),
        storeMember({ store: 'swProductDetail', member: 'advanceModeEnabled' }),
        memberUsage({ from: '*.advanceModeEnabled' }),
        stringValue({ from: 'mode.setting.advancedModeSettings' }),
    ],
});
