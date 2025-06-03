import type { ShopwareClass } from 'src/core/shopware';
import useSession from '../../../app/composables/use-session';
import './extensions.store';

let initialLoad = false;

/**
 * @sw-package checkout
 * @private
 */
export default function initState(Shopware: ShopwareClass): void {
    Shopware.Vue.watch(useSession().languageId, async () => {
        if (!Shopware.Service('acl').can('system.plugin_maintain')) {
            return;
        }

        // Always on page load setAdminLocale will be called once. Catch it to not load refresh extensions
        if (!initialLoad) {
            initialLoad = true;
            return;
        }

        await Shopware.Service('shopwareExtensionService').updateExtensionData(false);
    });
}
