import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import Plugin from 'src/plugin-system/plugin.class';

export default class DynamicBreadcrumbPlugin extends Plugin {
    static options = {
        cookieName: 'sw-referer-category-id',
    };

    init() {
        this.setRefererCategoryCookie();
    }

    setRefererCategoryCookie() {
        const refererCategoryId = window.activeNavigationId;

        if (!refererCategoryId) {
            CookieStorageHelper.removeItem(this.options.cookieName);

            return;
        }

        CookieStorageHelper.setItem(this.options.cookieName, refererCategoryId, 1);
    }
}
