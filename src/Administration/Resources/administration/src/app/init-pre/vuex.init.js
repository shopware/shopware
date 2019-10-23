import Vue from 'vue';
import Vuex from 'vuex';

export default function initializeVuex() {
    Vue.use(Vuex);

    const store = new Vuex.Store({
        modules: {},
        strict: false
    });

    Shopware.State._setStore(store);
    Shopware.State._registerProperty('get', (name) => store.state[name]);
    Shopware.State._registerProperty('getters', store.getters);
    Shopware.State._registerProperty('commit', store.commit);
    Shopware.State._registerProperty('dispatch', store.dispatch);
    Shopware.State._registerProperty('watch', store.watch);
    Shopware.State._registerProperty('subscribe', store.subscribe);
    Shopware.State._registerProperty('subscribeAction', store.subscribeAction);
    Shopware.State._registerProperty('registerModule', store.registerModule);
    Shopware.State._registerProperty('registerModule', store.registerModule);
    Shopware.State._registerProperty('unregisterModule', store.unregisterModule);

    return store;
}
