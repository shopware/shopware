import type { ShopwareClass } from 'src/core/shopware';
import extensionStore from './extensions.store';

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
