import type { ShopwareClass } from 'src/core/shopware';
import extensionStore from './extensions.store';

/**
 * @package merchant-services
 * @private
 */
export default function initState(Shopware: ShopwareClass): void {
    Shopware.State.registerModule('shopwareExtensions', extensionStore);

    let languageId = Shopware.State.get('session').languageId;
    Shopware.State._store.subscribe(async ({ type }, state): Promise<void> => {
        if (!Shopware.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        if (type === 'setAdminLocale' && state.session.languageId !== '' && languageId !== state.session.languageId) {
            // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
            if (languageId === '') {
                languageId = state.session.languageId;
                return;
            }

            languageId = state.session.languageId;
            await Shopware.Service('shopwareExtensionService').updateExtensionData().then();
        }
    });
}
