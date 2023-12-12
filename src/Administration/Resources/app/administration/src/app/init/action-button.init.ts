/**
 * @package admin
 *
 * @private
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActionButtons(): void {
    Shopware.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        Shopware.State.commit('actionButtons/add', configuration);
    });
}
