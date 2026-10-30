export default class NavigationHelper
{
    /**
     * Whether the browser reached the current page through a redirect.
     *
     * The storefront answers a successful login, registration or guest conversion with a redirect.
     * A failure, such as an invalid field, a rejected captcha or a throttled login, renders a page
     * within the response to the submit instead, without a redirect.
     *
     * @returns {boolean}
     */
    static isRedirected() {
        const [navigation] = window.performance?.getEntriesByType?.('navigation') ?? [];

        return (navigation?.redirectCount ?? 0) > 0;
    }
}
