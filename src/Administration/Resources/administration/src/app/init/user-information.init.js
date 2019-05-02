import { State } from 'src/core/shopware';
import { initializeUserNotifications } from 'src/app/state/notification.store';

export default function initializeUserContext(container) {
    const serviceContainer = this.getContainer('service');
    const loginService = serviceContainer.loginService;
    const userService = serviceContainer.userService;
    const contextService = container.contextService;

    return new Promise((resolve) => {
        // The user isn't logged in
        if (!loginService.isLoggedIn()) {
            // Remove existing login info from the locale storage
            loginService.logout();
            resolve();
            return;
        }

        userService.getUser().then((response) => {
            // Populate the current user to the context object
            const data = response.data;
            delete data.password;

            contextService.currentUser = data;
            State.getStore('adminUser').state.currentUser = data;
            initializeUserNotifications();
            resolve();
        }).catch(() => {
            // An error occurred which means the user isn't logged in so get rid of the information in local storage
            loginService.logout();
            resolve();
        });
    });
}
