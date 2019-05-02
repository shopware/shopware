export default function registerLoginBackgroundCheck(container) {
    const application = this;
    const httpClient = container.httpClient;
    const serviceContainer = application.getContainer('service');
    const loginService = serviceContainer.loginService;
    const checkUserInterval = 30000;
    const interval = setInterval(requestUserInfo, checkUserInterval);

    document.addEventListener('visibilitychange', onHandleVisibilityChange, false);

    /**
     * Requests the user information from the REST api, logs out the user and redirects the user to the login form
     * when the session was expired.
     * @return {void}
     */
    function requestUserInfo() {
        if (!loginService.getBearerAuthentication('access')) {
            return;
        }

        const basicHeaders = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${loginService.getToken()}`,
            'Content-Type': 'application/json'
        };

        httpClient.get('_info/ping', { headers: basicHeaders }).catch(() => {
            const router = application.getApplicationRoot().$router;
            loginService.logout();
            router.push({ name: 'sw.login.index' });
        });
    }

    /**
     * Event handler which will be fired when the page comes back from background.
     *
     * @return {boolean}
     */
    function onHandleVisibilityChange() {
        if (document.hidden) {
            clearInterval(interval);
            return false;
        }
        requestUserInfo();
        setInterval(requestUserInfo, checkUserInterval);

        return true;
    }

    return true;
}
