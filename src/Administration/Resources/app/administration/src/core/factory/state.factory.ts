/* eslint-disable @typescript-eslint/no-unsafe-assignment */
import type { Store, Commit, Dispatch } from 'vuex';

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
    /* eslint-disable @typescript-eslint/no-explicit-any */
    _store: Store<VuexRootState>,
    list: any,
    get: any,
    getters: any,
    commit: Commit,
    dispatch: Dispatch,
    watch: any,
    subscribe: any,
    subscribeAction: any,
    registerModule: any,
    unregisterModule: any,
}

export default function stateFactory(): FullState {
    // force the additional properties (added in "state.init")
    return new State() as FullState;
}
