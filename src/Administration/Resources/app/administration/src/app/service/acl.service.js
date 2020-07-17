import { next3722 } from 'src/flag/feature_next3722';

const utils = Shopware.Utils;

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
        return !!this.state.get('session').currentUser.admin;
    }

    /**
     *
     * @param privilegeKey {string}
     * @returns {boolean}
     */
    can(privilegeKey) {
        if (!next3722()) {
            return true;
        }

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
        if (!utils.get(Shopware, 'Application.view.root.$router')) {
            return true;
        }

        const route = path.replace(/\./g, '/');
        const router = Shopware.Application.view.root.$router;
        const match = router.match(route);

        if (!match.meta) {
            return true;
        }

        return this.can(match.meta.privilege);
    }

    /**
     *
     * @returns {string[]}
     */
    get privileges() {
        return this.state.getters.userPrivileges;
    }
}
