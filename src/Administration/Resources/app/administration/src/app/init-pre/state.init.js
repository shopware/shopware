import Vue from 'vue';
import Vuex from 'vuex';
import VuexModules from 'src/app/state/index';

export default function initState() {
    initVuexState(Shopware.State);
    initVuexModules(VuexModules, Shopware.State);

    return true;
}

function initVuexState(state) {
    Vue.use(Vuex);

    const store = new Vuex.Store({
        modules: {},
        strict: false,
    });

    state._registerPrivateProperty('_store', store);
    state._registerProperty('list', () => Object.keys(store.state));
    state._registerProperty('get', (name) => store.state[name]);
    state._registerGetterMethod('getters', () => store.getters);
    state._registerProperty('commit', (...args) => store.commit(...args));
    state._registerProperty('dispatch', (...args) => store.dispatch(...args));
    state._registerProperty('watch', (...args) => store.watch(...args));
    state._registerProperty('subscribe', (...args) => store.subscribe(...args));
    state._registerProperty('subscribeAction', (...args) => store.subscribeAction(...args));
    state._registerProperty('registerModule', (...args) => store.registerModule(...args));
    state._registerProperty('unregisterModule', (...args) => store.unregisterModule(...args));

    return state;
}

function initVuexModules(modules, state) {
    Object.keys(modules).forEach((storeModule) => {
        state.registerModule(storeModule, modules[storeModule]);
    });
}
