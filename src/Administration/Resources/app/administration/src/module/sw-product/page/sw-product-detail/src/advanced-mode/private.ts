/**
 * @sw-package inventory
 *
 * The deprecated advanced mode of the product detail page, which lets a merchant hide cards.
 * Delete this module with the advanced mode in 6.8.
 */
/** @private */
export { createGeneralTabSettings, createSpecificationsTabSettings, useAdvancedMode } from './src/advanced-mode';
/** @private */
export type { AdvancedMode, AdvancedModeDependencies, AdvancedModeSetting, ModeSettingItem } from './src/advanced-mode';
