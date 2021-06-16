const { Application } = Shopware;

/**
 * @module core/service/plugin-updates-listener
 */

/**
 *
 * @memberOf module:core/service/plugin-updates-listener
 * @method addPluginUpdatesListener
 * @param loginService
 * @param serviceContainer
 */
export default function addPluginUpdatesListener(loginService, serviceContainer) {
    /** @var {String} localStorage token */
    const localStorageKey = 'lastPluginUpdateCheck';
    let applicationRoot = null;

    loginService.addOnLoginListener(checkForPluginUpdates);

    function checkForPluginUpdates() {
        const lastUpdate = localStorage.getItem(localStorageKey);
        const oneDay = 24 * 60 * 60 * 1000;

        if (lastUpdate < Date.now() - oneDay) {
            serviceContainer.storeService.getUpdateList()
                .then((response) => {
                    if (response.total > 0) {
                        createUpdatesAvailableNotification();
                    }
                })
                .catch();

            localStorage.setItem(localStorageKey, Date.now());
        }
    }

    function createUpdatesAvailableNotification() {
        const notification = {
            title: getApplicationRootReference().$tc(
                'global.notification-center.plugin-updates-listener.updatesAvailableTitle',
            ),
            message: getApplicationRootReference().$tc(
                'global.notification-center.plugin-updates-listener.updatesAvailableMessage',
            ),
            variant: 'info',
            growl: true,
            system: true,
        };

        getApplicationRootReference().$store.dispatch(
            'notification/createNotification',
            notification,
        );
    }

    function getApplicationRootReference() {
        if (!applicationRoot) {
            applicationRoot = Application.getApplicationRoot();
        }

        return applicationRoot;
    }
}
