export default function createApplicationState(stateContainer) {
    const store = stateContainer.createStore({
        state: {
            count: 0,
            bearerToken: null,
            user: {},
            menuEntries: [],
            modules: {},
            shortcuts: [],
            routes: []
        },
        mutations: {
            setUser: (user) => () => {
                return {
                    user
                };
            },

            setToken: (token) => () => {
                return {
                    bearerToken: token
                };
            },

            logoutUser: () => () => {
                return {
                    bearerToken: null,
                    user: {}
                };
            },

            setMenuEntry: (entry) => state => {
                const menuEntries = state.menuEntries;
                menuEntries.push(entry);

                return {
                    menuEntries
                };
            },

            setModule: (moduleDefinition) => state => {
                const id = moduleDefinition.id;
                const modules = state.modules;

                modules[id] = moduleDefinition;

                return {
                    modules
                };
            },

            setShortcut: (shortcutDefinition) => state => {
                const shortcuts = state.shortcuts;
                shortcuts.push(shortcutDefinition);

                return {
                    shortcuts
                };
            },

            setRoute: (routeDefinition) => state => {
                const routes = state.routes;
                routes.push(routeDefinition);

                return {
                    routes
                };
            }
        },

        getter: {
            getToken: (state) => {
                return state.bearerToken;
            },

            isLoggedIn: (state) => {
                return (state.bearerToken.length && state.user.username);
            },

            getTokenHeader: (state) => {
                return {
                    Authorization: `Bearer ${state.bearerToken}`
                };
            }
        }
    });

    return store;
}
