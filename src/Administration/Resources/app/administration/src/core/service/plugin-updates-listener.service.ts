/**
 * @package admin
 *
 * @module core/service/plugin-updates-listener
 */
import type Vue from 'vue';
import type { LoginService } from './login.service';

type UpdatedListResponse = {
    total: number,
    [key: string]: unknown,
};


/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default function addPluginUpdatesListener(loginService: LoginService, serviceContainer: ServiceContainer): void {
    const localStorageKey = 'lastPluginUpdateCheck';

    function checkForPluginUpdates(innerServiceContainer: ServiceContainer) {
        // @ts-expect-error - localStorage.getItem() might return null but then Number.parseInt() will return NaN
        const lastUpdate: number | Number.NaN = Number.parseInt(localStorage.getItem(localStorageKey), 10);
        const oneDay = 24 * 60 * 60 * 1000;

        if (Number.isNaN(lastUpdate) || lastUpdate < Date.now() - oneDay) {
            // @ts-expect-error
            void innerServiceContainer.storeService.getUpdateList().then((response: UpdatedListResponse) => {
                localStorage.setItem(localStorageKey, Date.now().toString());
                if (response.total > 0 && canUpdateExtensions()) {
                    createUpdatesAvailableNotification();
                }
            }).catch(() => { /* ignore notification could not be created */ });
        }
    }

    function createUpdatesAvailableNotification(): void {
        const root: Vue | boolean = Shopware.Application.getApplicationRoot();

        if (!root) {
            throw new Error('could not find applicationRoot');
        }

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

    function canUpdateExtensions(): boolean {
        const neededPrivileges = [
            'plugin:update',
            'app.all',
        ];

        return neededPrivileges.some((privilegeKey) => {
            return serviceContainer.acl.can(privilegeKey);
        });
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
