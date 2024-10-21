import Store from 'src/app/store';

/**
 * @package admin
 * @private
 */
export default function initStore() {
    const app = Shopware.Application?.view?.app;

    /**
     * This code does two things:
     * 1. Initializing the Pinia singleton by accessing the instance getter.
     * 2. Registering the Pinia plugin with Vue before the first store is trying to be registered.
     */
    if (app) {
        app.use(Store.instance._rootState);
    }
}
