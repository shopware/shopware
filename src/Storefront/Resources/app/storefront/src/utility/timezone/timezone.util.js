const TIMEZONE_COOKIE = 'timezone';

import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

/**
 * @package storefront
 */
export default class TimezoneUtil {

    /**
     * Constructor
     */
    constructor() {
        if (!CookieStorageHelper.isSupported()) {
            return;
        }

        CookieStorageHelper.setItem(
            TIMEZONE_COOKIE,
            Intl.DateTimeFormat().resolvedOptions().timeZone,
            30
        );
    }

}
