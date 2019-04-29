export default function registerLoginBackgroundCheck() {
    const application = this;
    const serviceContainer = application.getContainer('service');
    const loginService = serviceContainer.loginService;
    const checkUserInterval = 30000;
    const interval = setInterval(requestUserInfo, checkUserInterval);

    document.addEventListener('visibilitychange', onHandleVisibilityChange, false);

    /**
     * Requests the user information from the REST api, logs out the user and redirects the user to the login form
     * when the session was expired.
     * @return {Promise<T | never>}
     */
    function requestUserInfo() {
        const userService = application.getContainer('service').userService;
        return userService.getUser().catch(() => {
            const router = application.getApplicationRoot().$router;
            loginService.logout();
            router.push({ to: '/login' });
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
