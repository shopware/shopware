/* eslint-disable @typescript-eslint/no-unsafe-assignment */
import type { Store } from 'vuex';

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

    public _registerGetterMethod(name: string, getMethod: $TSFixMeFunction, setMethod: $TSFixMeFunction): this {
        Object.defineProperty(this, name, {
            get: getMethod,
            set: setMethod,
            enumerable: true,
            configurable: true,
        });

        return this;
    }
}

interface FullState extends State {
    _store: Store<VuexRootState>,
    list: () => (keyof VuexRootState)[],
    get: <NAME extends keyof VuexRootState>(name: NAME) => VuexRootState[NAME],
    getters: Store<VuexRootState>['getters'],
    commit: Store<VuexRootState>['commit'],
    dispatch: Store<VuexRootState>['dispatch'],
    // eslint-disable-next-line max-len
    watch: Store<VuexRootState>['watch'],
    subscribe: Store<VuexRootState>['subscribe'],
    subscribeAction: Store<VuexRootState>['subscribeAction'],
    registerModule: Store<VuexRootState>['registerModule'],
    unregisterModule: Store<VuexRootState>['unregisterModule'],
}

export default function stateFactory(): FullState {
    // force the additional properties (added in "state.init")
    return new State() as FullState;
}
