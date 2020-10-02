export default {
    namespaced: true,

    state() {
        return {
            apps: [],
            selectedIds: []
        };
    },

    getters: {
        navigation(state) {
            return state.apps.reduce((previousValue, app) => {
                previousValue.push(...getNavigationForApps(app));
                return previousValue;
            }, []);
        }
    },

    mutations: {
        setApps(state, apps) {
            state.apps = apps;
        },

        setSelectedIds(state, selectedIds) {
            state.selectedIds = selectedIds;
        }
    },

    actions: {
        setAppModules({ commit }, modules) {
            commit('setApps', modules);
        },

        setSelectedIds({ commit }, selectedIds) {
            commit('setSelectedIds', selectedIds);
        }
    }
};

function getNavigationForApps(app) {
    const locale = Shopware.State.get('session').currentLocale;
    const fallbackLocale = Shopware.Context.app.fallbackLocale;

    const appLabel = app.label[locale] || app.label[fallbackLocale];

    return app.modules.map((adminModule) => {
        const moduleLabel = adminModule.label[locale] || adminModule.label[fallbackLocale];

        return {
            id: `app-${app.name}-${adminModule.name}`,
            path: 'sw.my.apps.index',
            params: { appName: app.name, moduleName: adminModule.name },
            label: {
                translated: true,
                label: `${appLabel} - ${moduleLabel}`
            },
            color: '#9AA8B5',
            parent: 'sw-my-apps',
            children: []
        };
    });
}
