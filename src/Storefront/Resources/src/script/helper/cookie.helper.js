export default class CookieHandler {

    /**
     * Sets cookie with name, value and expiration date
     * @param {string} cookieName
     * @param {string} cookieValue
     * @param {number} expirationDays
     */
    static setCookie(cookieName, cookieValue, expirationDays) {
        const date = new Date();
        date.setTime(date.getTime() + (expirationDays * 24 * 60 * 60 * 1000));

        const expires = 'expires=' + date.toUTCString();
        document.cookie = cookieName + '=' + cookieValue + ';' + expires + ';path=/';
    }

    /**
     * Gets cookie value through the cookie name
     * @param {string} cookieName
     * @returns {string} cookieValue
     */
    static getCookie(cookieName) {
        const name = cookieName + '=';
        const allCookies = document.cookie.split(';');

        for (let i = 0; i < allCookies.length; i++) {
            let singleCookie = allCookies[i];

            while (singleCookie.charAt(0) === ' ') {
                singleCookie = singleCookie.substring(1);
            }

            if (singleCookie.indexOf(name) === 0) {
                return singleCookie.substring(name.length, singleCookie.length);
            }
        }
        return '';
    }

    /**
     * Checks if there is already a cookie permission set
     * Hides cookie bar if cookie permission is already set
     * If there is no cookie permission set it initializes the cookie bar
     */
    static hasCookie(cookieName, cookieValue) {
        return this.getCookie(cookieName) === cookieValue;
    }
}