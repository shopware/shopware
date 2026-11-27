/**
 * @sw-package framework
 *
 * The advanced mode of the product detail page, which 6.8 removes without replacement, taken from the
 * `@deprecated tag:v6.8.0` markers of `sw-product-detail`, its store `swProductDetail` and `sw-product-settings-mode`.
 * Without it all tabs and cards are visible. `showModeSetting` of the seven form and view components reads the
 * removed store getter, so it is marked in their overrides too. The `sw_product_settings_mode*` blocks are handled by
 * `no-removed-twig-blocks`.
 */

const removed = (name) => `${name} is removed with the product detail advanced mode, without replacement. All tabs and cards of the product detail page are always visible.`;

const SHOW_MODE_SETTING_COMPONENTS = [
    'sw-product-detail',
    'sw-product-detail-base',
    'sw-product-detail-specifications',
    'sw-product-price-form',
    'sw-product-deliverability-form',
    'sw-product-deliverability-downloadable-form',
    'sw-product-category-form',
];

module.exports = {
    id: 'product-advanced-mode',
    description: 'Marks the product detail advanced mode, which 6.8 removes without replacement',
    components: {
        'sw-product-settings-mode': removed('sw-product-settings-mode'),
    },
    members: [
        { name: 'advancedModeSetting', unique: true, message: removed('advancedModeSetting') },
        { name: 'advanceModeEnabled', unique: true, message: removed('advanceModeEnabled') },
        { name: 'getModeSettingGeneralTab', unique: true, message: removed('sw-product-detail.getModeSettingGeneralTab') },
        { name: 'getModeSettingSpecificationsTab', unique: true, message: removed('sw-product-detail.getModeSettingSpecificationsTab') },
        { name: 'showAdvanceModeSetting', unique: true, message: removed('sw-product-detail.showAdvanceModeSetting') },
        { name: 'initAdvancedModeSettings', unique: true, message: removed('sw-product-detail.initAdvancedModeSettings()') },
        { name: 'createUserModeSetting', unique: true, message: removed('sw-product-detail.createUserModeSetting()') },
        { name: 'getAdvancedModeDefaultSetting', unique: true, message: removed('sw-product-detail.getAdvancedModeDefaultSetting()') },
        { name: 'getAdvancedModeSetting', unique: true, message: removed('sw-product-detail.getAdvancedModeSetting()') },
        { name: 'saveAdvancedMode', unique: true, message: removed('sw-product-detail.saveAdvancedMode()') },
        { name: 'changeModeSettings', unique: true, message: removed('sw-product-detail.changeModeSettings()') },
        { component: 'sw-product-detail', name: 'onChangeSetting', message: removed('sw-product-detail.onChangeSetting()') },
        { component: 'sw-product-detail', name: 'onChangeSettingItem', message: removed('sw-product-detail.onChangeSettingItem()') },
        { component: SHOW_MODE_SETTING_COMPONENTS, name: 'showModeSetting', message: removed('showModeSetting') },
    ],
    literals: [
        { value: 'mode.setting.advancedModeSettings', message: removed('The user config key mode.setting.advancedModeSettings') },
    ],
    /** Members of `Shopware.Store.get('swProductDetail')`; `advancedMode` is a key of its `loading` state. */
    store: {
        id: 'swProductDetail',
        members: ['advancedModeSetting', 'advanceModeEnabled', 'showModeSetting'],
        loadingKey: 'advancedMode',
        loadingMessage: removed("The loading state advancedMode of the swProductDetail store (setLoading(['advancedMode', …]), loading.advancedMode)"),
    },
};
