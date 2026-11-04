import Store from 'src/app/store';
import 'src/app/store/action-buttons/register';
import 'src/app/store/admin-help-center/register';
import 'src/app/store/admin-menu/register';
import 'src/app/store/block-override/register';
import 'src/app/store/context/register';
import 'src/app/store/error/register';
import 'src/app/store/extension-component-sections/register';
import 'src/app/store/extension-entry-routes/register';
import 'src/app/store/extension-sdk-module/register';
import 'src/app/store/extensions/register';
import 'src/app/store/in-app-purchase-checkout/register';
import 'src/app/store/license-violation/register';
import 'src/app/store/main-module/register';
import 'src/app/store/marketing/register';
import 'src/app/store/media-modal/register';
import 'src/app/store/menu-item/register';
import 'src/app/store/modals/register';
import 'src/app/store/notification/register';
import 'src/app/store/rule-conditions-config/register';
import 'src/app/store/sdk-location/register';
import 'src/app/store/session/register';
import 'src/app/store/settings-item/register';
import 'src/app/store/shopware-apps/register';
import 'src/app/store/sidebar/register';
import 'src/app/store/sw-bulk-edit/register';
import 'src/app/store/system/register';
import 'src/app/store/tabs/register';
import 'src/app/store/teaser-popover/register';
import 'src/app/store/topbar-button/register';

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
