import Vue from 'vue';
import Vuex from 'vuex';

export default function initializeVuex() {
    Vue.use(Vuex);

    const store = new Vuex.Store({
        modules: {},
        strict: false
    });

    Shopware.State._registerPrivateProperty('_store', store);
    Shopware.State._registerProperty('list', () => Object.keys(store.state));
    Shopware.State._registerProperty('get', (name) => store.state[name]);
    Shopware.State._registerGetterMethod('getters', () => store.getters);
    Shopware.State._registerProperty('commit', (...args) => store.commit(...args));
    Shopware.State._registerProperty('dispatch', (...args) => store.dispatch(...args));
    Shopware.State._registerProperty('watch', (...args) => store.watch(...args));
    Shopware.State._registerProperty('subscribe', (...args) => store.subscribe(...args));
    Shopware.State._registerProperty('subscribeAction', (...args) => store.subscribeAction(...args));
    Shopware.State._registerProperty('registerModule', (...args) => store.registerModule(...args));
    Shopware.State._registerProperty('unregisterModule', (...args) => store.unregisterModule(...args));

    return store;
}
