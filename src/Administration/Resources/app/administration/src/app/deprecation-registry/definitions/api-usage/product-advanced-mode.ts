/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, removedComponent, stringLiteral, upgradeGuide } from '../helpers';

const MESSAGE =
    'The product detail advanced mode is removed in 6.8 without replacement. ' +
    'Stop extending the mode settings and show your card without showProductCard().';

export default apiUsageMigration({
    id: 'api.sw-product-detail.advanced-mode',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The advanced mode of the product detail page was removed without replacement.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-the-product-detail-advanced-mode'),
    ],
    usage: [
        componentMember({
            components: ['sw-product-detail'],
            names: [
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
            message: MESSAGE,
        }),
        removedComponent({ names: ['sw-product-settings-mode'], message: MESSAGE }),
        memberUsage({
            store: 'swProductDetail',
            properties: [
                'advancedModeSetting',
                'showModeSetting',
                'advanceModeEnabled',
            ],
            message: MESSAGE,
        }),
        stringLiteral({ values: ['mode.setting.advancedModeSettings'], message: MESSAGE }),
    ],
});
