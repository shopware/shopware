import type { ShopwareClass } from 'src/core/shopware';
import extensionStore from './extensions.store';
import useSession from '../../../app/composables/use-session';

/**
 * @package checkout
 * @private
 */
export default function initState(Shopware: ShopwareClass): void {
    Shopware.State.registerModule('shopwareExtensions', extensionStore);

    Shopware.Vue.watch(useSession().languageId, async (languageId) => {
        if (!Shopware.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
        if (languageId === '') {
            return;
        }

        await Shopware.Service('shopwareExtensionService').updateExtensionData();
    });
}
