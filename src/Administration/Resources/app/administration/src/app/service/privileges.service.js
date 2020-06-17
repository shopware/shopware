import Vue from 'vue';

const { warn } = Shopware.Utils.debug;

export default class PrivilegesService {
    state = Vue.observable({
        privilegesMappings: []
    });

    requiredPrivileges = [
        'language:read', // for entityInit and languageSwitch
        'locale:read', // for localeToLanguage service
        'message_queue_stats:read' // for message queue
    ];

    /**
     *
     * @param privileges {Array}
     * @returns {Array}
     */
    filterPrivilegesRoles(privileges) {
        const filteredPrivileges = [];

        privileges.forEach(privilegeKey => {
            if (!this.existsPrivilege(privilegeKey)) {
                return;
            }

            // avoid duplicates
            if (filteredPrivileges.includes(privilegeKey)) {
                return;
            }

            filteredPrivileges.push(privilegeKey);
        });

        return filteredPrivileges;
    }

    /**
     *
     * @param privilegeKey {String}
     * @returns {boolean}
     */
    existsPrivilege(privilegeKey) {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.some(privilegeMapping => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    /**
     *
     * @param privilegeKey {String}
     * @returns {Object}
     */
    getPrivilege(privilegeKey) {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.find(privilegeMapping => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    /**
     *
     * @param privilegeKey {String}
     * @returns {Object}
     */
    getPrivilegeRole(privilegeKey) {
        const role = privilegeKey.split('.')[1];

        const privilege = this.getPrivilege(privilegeKey);

        if (!privilege) {
            return undefined;
        }

        return privilege.roles[role];
    }

    /**
     *
     * @param privilegeMapping {Object}
     * @returns {PrivilegesService}
     */
    addPrivilegeMappingEntry(privilegeMapping) {
        if (typeof privilegeMapping !== 'object') {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping has to be an object.');
            return this;
        }

        if (!('category' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "category".');
            return this;
        }

        if (!('parent' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "parent".');
            return this;
        }

        if (!('key' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "key".');
            return this;
        }

        const existingCategoryKeyCombination = this.state.privilegesMappings.find(mapping => {
            return mapping.category === privilegeMapping.category &&
                mapping.key === privilegeMapping.key;
        });

        if (existingCategoryKeyCombination) {
            Object.entries(privilegeMapping.roles).forEach(([key, entry]) => {
                Vue.set(existingCategoryKeyCombination.roles, key, entry);
            });

            return this;
        }

        this.state.privilegesMappings.push(privilegeMapping);

        return this;
    }

    /**
     *
     * @returns {[]|*[]}
     */
    getPrivilegesMappings() {
        return this.state.privilegesMappings;
    }

    /**
     * @returns {[string, string, string]}
     */
    getRequiredPrivileges() {
        return this.requiredPrivileges;
    }
}
