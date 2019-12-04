import { initializeUserNotifications } from 'src/app/state/notification.store';

export default function initializeUserContext() {
    return new Promise((resolve) => {
        const loginService = Shopware.Service('loginService');
        const userService = Shopware.Service('userService');

        // The user isn't logged in
        if (!loginService.isLoggedIn()) {
            // Remove existing login info from the locale storage
            loginService.logout();
            resolve();
            return;
        }

        userService.getUser().then((response) => {
            const data = response.data;
            delete data.password;

            Shopware.State.commit('setCurrentUser', data);
            initializeUserNotifications();
            resolve();
        }).catch(() => {
            // An error occurred which means the user isn't logged in so get rid of the information in local storage
            loginService.logout();
            resolve();
        });
    });
}
