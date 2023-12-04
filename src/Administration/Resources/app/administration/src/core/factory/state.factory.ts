/**
 * @package admin
 */

/* eslint-disable @typescript-eslint/no-unsafe-assignment */
import type { Store as StoreV2 } from 'vuex_v2';

class State {
    public _registerProperty(name: string, property: $TSFixMe): this {
        Object.defineProperty(this, name, {
            value: property,
            writable: false,
            enumerable: true,
            configurable: true,
        });

        return this;
    }

    public _registerPrivateProperty(name: string, property: $TSFixMe): this {
        Object.defineProperty(this, name, {
            value: property,
            writable: false,
            enumerable: true,
            configurable: true,
        });

        return this;
    }

    public _registerGetterMethod(name: string, getMethod: $TSFixMeFunction, setMethod?: $TSFixMeFunction): this {
        Object.defineProperty(this, name, {
            get: getMethod,
            set: setMethod,
            enumerable: true,
            configurable: true,
        });

        return this;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface FullState extends State {
    _store: StoreV2<VuexRootState>,
    list: () => (keyof VuexRootState)[],
    get: <NAME extends keyof VuexRootState>(name: NAME) => VuexRootState[NAME],
    getters: StoreV2<VuexRootState>['getters'],
    commit: StoreV2<VuexRootState>['commit'],
    dispatch: StoreV2<VuexRootState>['dispatch'],
    watch: StoreV2<VuexRootState>['watch'],
    subscribe: StoreV2<VuexRootState>['subscribe'],
    subscribeAction: StoreV2<VuexRootState>['subscribeAction'],
    registerModule: StoreV2<VuexRootState>['registerModule'],
    unregisterModule: StoreV2<VuexRootState>['unregisterModule'],
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function stateFactory(): FullState {
    // force the additional properties (added in "state.init")
    return new State() as FullState;
}
