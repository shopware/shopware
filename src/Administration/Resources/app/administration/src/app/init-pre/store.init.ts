import Store from 'src/app/store';
import '../store/admin-menu.store';
import '../store/block-override.store';
import 'src/app/store/extension-entry-routes.store';
import 'src/app/store/extension-sdk-module.store';
import 'src/app/store/extensions.store';
import 'src/app/store/error.store';
import 'src/app/store/admin-help-center.store';
import 'src/app/store/license-violation.store';
import 'src/app/store/main-module.store';
import 'src/app/store/marketing.store';
import 'src/app/store/sdk-location.store';
import 'src/app/store/rule-conditions-config.store';
import 'src/app/store/settings-item.store';
import 'src/app/store/shopware-apps.store';
import 'src/app/store/system.store';
import 'src/app/store/modals.store';
import 'src/app/store/menu-item.store';
import 'src/app/store/tabs.store';
import 'src/app/store/usage-data.store';
import 'src/app/store/session.store';

/**
 * @sw-package framework
 * @private
 */
export default function initStore() {
    const app = Shopware.Application?.view?.app;

    /**
     * This code does two things:
     * 1. Initializing the Pinia singleton by accessing the instance getter.
     * 2. Registering the Pinia plugin with Vue before the first store is trying to be registered.
     */
    if (app) {
        app.use(Store.instance._rootState);
    }
}
