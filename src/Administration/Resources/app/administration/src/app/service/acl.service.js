import { next3722 } from 'src/flag/feature_next3722';

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
     * @returns {string[]}
     */
    get privileges() {
        return this.state.getters.userPrivileges;
    }
}
