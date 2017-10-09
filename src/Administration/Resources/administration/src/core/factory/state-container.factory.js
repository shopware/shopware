import Vue from 'vue';
import utils from 'src/core/service/util.service';

export default function createStateContainer(eventSystem) {
    return {
        createStore
    };

    function createStore(storeDefinition) {
        const reducerMutations = storeDefinition.mutations;
        const storeGetter = storeDefinition.getter;
        const initialState = storeDefinition.state;
        const currentState = {};

        let savedState = window.sessionStorage.getItem('currentState');

        Vue.util.defineReactive(currentState, 'state', initialState);

        if (savedState && savedState.length) {
            savedState = JSON.parse(savedState);
            Vue.set(currentState, 'state', savedState);
        }

        const getter = Object.keys(storeGetter).reduce((map, key) => {
            map[key] = function reactiveGetter() {
                return storeGetter[key].call(null, currentState.state);
            };
            return map;
        }, {});

        const subjects = Object.keys(reducerMutations).reduce((map, mutationName) => {
            map[mutationName] = eventSystem.createSubject();
            return map;
        }, {});

        const intents = Object.keys(reducerMutations).reduce((map, mutationName) => {
            map[mutationName] = subjects[mutationName].map(reducerMutations[mutationName]);
            return map;
        }, {});

        const mutations = Object.keys(reducerMutations).reduce((map, mutationName) => {
            map[mutationName] = (...args) => subjects[mutationName].next(...args);
            return map;
        }, {});

        const store = eventSystem.getObservable()
            .merge(...Object.keys(intents).map(key => intents[key]))
            .scan((state, reducer) => {
                Vue.set(currentState, 'state', utils.merge(state, reducer(state)));
                window.sessionStorage.setItem('currentState', JSON.stringify(currentState.state));
                return currentState.state;
            }, initialState)
            .startWith(initialState)
            .skip(1);

        store.publish().connect();

        function commit(mutationName, args) {
            if (Object.keys(mutations).indexOf(mutationName) === -1) {
                return false;
            }

            return mutations[mutationName].call(null, args);
        }

        function subscribe(args) {
            store.subscribe(args);
        }

        function mapState(mapProps) {
            return mapProps.reduce((map, item) => {
                const path = item.split('.');
                const value = path.reduce((o, i) => {
                    return o[i];
                }, currentState.state);

                if (path.length > 1) {
                    path.reduce((o, i) => {
                        const lastPathElement = path[path.length - 1];
                        if (lastPathElement === i) {
                            o[i] = value;
                            return o[i];
                        }

                        o[i] = {};
                        return o[i];
                    }, map);
                } else {
                    map[item] = typeof getter[item] === 'function' ? getter[item]() : value;
                }

                return map;
            }, {});
        }

        return {
            state: currentState.state,
            getter,
            mapState,
            commit,
            subscribe
        };
    }
}
