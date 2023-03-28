/**
 * @package admin
 *
 * @module core/service/plugin-updates-listener
 */
import type { LoginService } from './login.service';

type UpdatedListResponse = {
    total: number,
    [key: string]: unknown,
};


/**
 * @deprecated tag:v6.6.0 - Will be private
 * @memberOf module:core/service/plugin-updates-listener
 * @method addPluginUpdatesListener
 * @param loginService
 * @param serviceContainer
 */
export default function addPluginUpdatesListener(loginService: LoginService, serviceContainer: ServiceContainer): void {
    const localStorageKey = 'lastPluginUpdateCheck';
    let applicationRoot: Vue | false = false;

    function checkForPluginUpdates(innerServiceContainer: ServiceContainer) {
        const lastUpdate = localStorage.getItem(localStorageKey);
        const oneDay = 24 * 60 * 60 * 1000;

        if (lastUpdate && parseInt(lastUpdate, 10) < Date.now() - oneDay) {
            // @ts-expect-error
            void innerServiceContainer.storeService.getUpdateList().then((response: UpdatedListResponse) => {
                if (response.total > 0 && canUpdateExtensions()) {
                    createUpdatesAvailableNotification();
                }
            });

            localStorage.setItem(localStorageKey, Date.now().toString());
        }
    }

    function createUpdatesAvailableNotification(): void {
        const root = getApplicationRootReference() as Vue;
        const notification = {
            title: root.$tc(
                'global.notification-center.plugin-updates-listener.updatesAvailableTitle',
            ),
            message: root.$tc(
                'global.notification-center.plugin-updates-listener.updatesAvailableMessage',
            ),
            variant: 'info',
            growl: true,
            system: true,
        };

        void root.$store.dispatch(
            'notification/createNotification',
            notification,
        );
    }

    function getApplicationRootReference(): Vue | null {
        if (!applicationRoot) {
            // @ts-expect-error
            applicationRoot = Shopware.Application.getApplicationRoot();
        }

        return applicationRoot || null;
    }

    function canUpdateExtensions(): boolean {
        let hasNeededPrivileges = false;
        const neededPrivileges = [
            'plugin:update',
            'app.all',
        ];

        neededPrivileges.forEach((privilegeKey) => {
            // at least one privilege is needed
            if (serviceContainer.acl.can(privilegeKey)) {
                hasNeededPrivileges = true;
            }
        });

        return hasNeededPrivileges;
    }

    Shopware.State.watch(
        (state) => state.session.currentUser,
        (newValue, oldValue) => {
            if (newValue === oldValue || newValue === null) {
                return;
            }

            // only check when user is given
            checkForPluginUpdates(serviceContainer);
        },
    );
}
