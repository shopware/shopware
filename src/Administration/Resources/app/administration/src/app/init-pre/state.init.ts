/**
 * @package admin
 */

import VuexModules from 'src/app/state/index';
import type { FullState } from 'src/core/factory/state.factory';
import type { Module, Store } from 'vuex';
import { createStore } from 'vuex';
import Vue from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initState() {
    initVuexState(Shopware.State);
    // @ts-expect-error - vuex modules import is not typed
    initVuexModules(VuexModules, Shopware.State);

    return true;
}

function initVuexState(state: FullState) {
    const store = createStore<VuexRootState>({
        modules: {},
        strict: false,
    });

    Vue.use(store);

    registerProperties(state, store);

    return state;
}


function registerProperties(state: FullState, store: Store<VuexRootState>) {
    // eslint-disable-next-line max-len
    /* eslint-disable @typescript-eslint/no-unsafe-return, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument, max-len */
    state._registerPrivateProperty('_store', store);
    state._registerProperty('list', () => Object.keys(store.state));
    state._registerProperty('get', <N extends keyof VuexRootState>(name: N) => store.state[name]);
    state._registerGetterMethod('getters', () => store.getters);
    state._registerProperty('commit', (...args: Parameters<typeof store.commit>) => store.commit(...args));
    state._registerProperty('dispatch', (...args: Parameters<typeof store.dispatch>) => store.dispatch(...args));
    state._registerProperty('watch', (...args: Parameters<typeof store.watch>) => store.watch(...args));
    state._registerProperty('subscribe', (...args: Parameters<typeof store.subscribe>) => store.subscribe(...args));
    state._registerProperty('subscribeAction', (...args: Parameters<typeof store.subscribeAction>) => store.subscribeAction(...args));
    state._registerProperty('registerModule', (...args: Parameters<typeof store.registerModule>) => store.registerModule(...args));
    state._registerProperty('unregisterModule', (...args: Parameters<typeof store.unregisterModule>) => store.unregisterModule(...args));
    /* eslint-enable @typescript-eslint/no-unsafe-return, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-argument, max-len */
}

function initVuexModules(modules: { [moduleName: string]: Module<keyof VuexRootState, VuexRootState> }, state: FullState) {
    Object.entries(modules).forEach(([moduleName, module]) => {
        state.registerModule(moduleName, module);
    });
}
