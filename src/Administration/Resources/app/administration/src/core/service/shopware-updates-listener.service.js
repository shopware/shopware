const { Application } = Shopware;

/**
 * @package admin
 *
 * @module core/service/shopware-updates-listener
 */

/**
 *
 * @memberOf module:core/service/shopware-updates-listener
 * @method addShopwareUpdatesListener
 * @param loginService
 * @param serviceContainer
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function addShopwareUpdatesListener(loginService, serviceContainer) {
    /** @var {String} localStorage token */
    let applicationRoot = null;

    loginService.addOnLoginListener(() => {
        if (!Shopware.Service('acl').can('system.core_update')) {
            return;
        }

        serviceContainer.updateService.checkForUpdates()
            .then((response) => {
                if (response.version) {
                    createUpdatesAvailableNotification(response);
                }
            })
            .catch();
    });

    function createUpdatesAvailableNotification(response) {
        const cancelLabel =
            getApplicationRootReference().$tc('global.default.cancel');
        const updateLabel =
            getApplicationRootReference().$tc('global.notification-center.shopware-updates-listener.updateNow');

        const notification = {
            title: getApplicationRootReference().$t(
                'global.notification-center.shopware-updates-listener.updatesAvailableTitle',
                {
                    version: response.version,
                },
            ),
            message: getApplicationRootReference().$t(
                'global.notification-center.shopware-updates-listener.updatesAvailableMessage',
                {
                    version: response.version,
                },
            ),
            variant: 'info',
            growl: true,
            system: true,
            actions: [{
                label: updateLabel,
                route: { name: 'sw.settings.shopware.updates.wizard' },
            }, {
                label: cancelLabel,
            }],
            autoClose: false,
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
