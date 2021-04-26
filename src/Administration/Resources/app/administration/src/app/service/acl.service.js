export default class AclService {
    state;

    constructor(state) {
        this.state = state;
    }

    /**
     *
     * @returns {boolean}
     */
    isAdmin() {
        return !!this.state.get('session').currentUser && !!this.state.get('session').currentUser.admin;
    }

    /**
     *
     * @param privilegeKey {string}
     * @returns {boolean}
     */
    can(privilegeKey) {
        if (this.isAdmin() || !privilegeKey) {
            return true;
        }

        return this.state.getters.userPrivileges.includes(privilegeKey);
    }

    /**
     *
     * @param path {string}
     * @returns {boolean}
     */
    hasAccessToRoute(path) {
        const route = path.replace(/\./g, '/');
        if (route === '/sw/settings/index') {
            return this.hasActiveSettingModules();
        }

        if (!Shopware?.Application?.view?.root?.$router) {
            return true;
        }

        const router = Shopware.Application.view.root.$router;
        const match = router.match(route);

        if (!match.meta) {
            return true;
        }

        return this.can(match.meta.privilege);
    }


    hasActiveSettingModules() {
        const groups = Object.values(this.state.get('settingsItems').settingsGroups);

        let hasActive = false;

        groups.forEach((modules) => {
            modules.forEach((module) => {
                if (!module.privilege) {
                    hasActive = true;
                } else if (this.can(module.privilege)) {
                    hasActive = true;
                }
            });
        });

        return hasActive;
    }

    /**
     *
     * @returns {string[]}
     */
    get privileges() {
        return this.state.getters.userPrivileges;
    }
}
