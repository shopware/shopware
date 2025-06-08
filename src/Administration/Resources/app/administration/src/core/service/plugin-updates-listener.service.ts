/**
 * @sw-package framework
 *
 * @module core/service/plugin-updates-listener
 */
import type { LoginService } from './login.service';
import type { NotificationType } from '../../app/store/notification.store';
import useSession from '../../app/composables/use-session';

type UpdatedListResponse = {
    total: number;
    [key: string]: unknown;
};

/**
 * @private
 */
export default function addPluginUpdatesListener(loginService: LoginService, serviceContainer: ServiceContainer): void {
    const localStorageKey = 'lastPluginUpdateCheck';

    function checkForPluginUpdates(innerServiceContainer: ServiceContainer) {
        // @ts-expect-error - localStorage.getItem() might return null but then Number.parseInt() will return NaN
        const lastUpdate: number = Number.parseInt(localStorage.getItem(localStorageKey), 10);
        const oneDay = 24 * 60 * 60 * 1000;

        if (Number.isNaN(lastUpdate) || lastUpdate < Date.now() - oneDay) {
            void innerServiceContainer.storeService
                .getUpdateList()
                // @ts-expect-error
                .then((response: UpdatedListResponse) => {
                    localStorage.setItem(localStorageKey, Date.now().toString());
                    if (response.total > 0 && canUpdateExtensions()) {
                        createUpdatesAvailableNotification();
                    }
                })
                .catch(() => {
                    /* ignore notification could not be created */
                });
        }
    }

    function createUpdatesAvailableNotification(): void {
        const root = Shopware.Application.getApplicationRoot();

        if (!root) {
            throw new Error('could not find applicationRoot');
        }

        const notification: NotificationType = {
            title: root.$tc('global.notification-center.plugin-updates-listener.updatesAvailableTitle'),
            message: root.$tc('global.notification-center.plugin-updates-listener.updatesAvailableMessage'),
            variant: 'info',
            growl: true,
            system: true,
        };

        void Shopware.Store.get('notification').createNotification(notification);
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

    Shopware.Vue.watch(useSession().currentUser, (newValue) => {
        if (!newValue) {
            return;
        }

        // only check when user is given
        checkForPluginUpdates(serviceContainer);
    });
}
