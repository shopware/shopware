/**
 * @package admin
 */

import Vue from 'vue';

const { warn, error } = Shopware.Utils.debug;
const { object } = Shopware.Utils;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class PrivilegesService {
    alreadyImportedAdminPrivileges = [];

    state = Vue.observable({
        privilegesMappings: [],
    });

    requiredPrivileges = [
        'language:read', // for entityInit and languageSwitch
        'locale:read', // for localeToLanguage service
        'message_queue_stats:read', // for message queue
        'log_entry:create', // for sw-error-boundary
    ];

    /**
     * Removes all keys from the given array which are not a admin role.
     *
     * Example:
     * product.viewer => Valid
     * product:read => Invalid
     *
     * @param privileges {Array}
     * @returns {Array}
     */
    filterPrivilegesRoles(privileges) {
        const onlyRoles = privileges.filter(privilegeKey => this.existsPrivilege(privilegeKey));

        return onlyRoles.filter((role, index) => onlyRoles.indexOf(role) === index);
    }

    /**
     * @public
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
     * @private
     * @param privilegeKey {String}
     * @returns {Object}
     */
    _getPrivilege(privilegeKey) {
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

        const privilege = this._getPrivilege(privilegeKey);

        return privilege ? privilege.roles[role] : undefined;
    }

    /**
     *
     * @example
     * // returns [
     *      'promotion:read',
     *      'promotion:update',
     *      'promotion:create',
     *      'rule:read',
     *      'rule:update',
     *      'rule:create'
     *    ]
     * _getPrivilegesWithDependencies('promotion.creator', false);
     *
     * @example
     * // returns [
     *      'promotion:read',
     *      'promotion:update',
     *      'promotion:create',
     *      'rule:read',
     *      'rule:update',
     *      'rule:create',
     *      'promotion.viewer',
     *      'promotion.editor',
     *      'promotion.creator'
     *    ]
     * _getPrivilegesWithDependencies('promotion.creator', true);
     *
     * @private
     * @param {string} adminPrivilegeKey
     * @param {boolean} shouldAddAdminPrivilege
     * @returns {string[]}
     */
    _getPrivilegesWithDependencies(adminPrivilegeKey, shouldAddAdminPrivilege = true) {
        // check for duplicated calls to prevent infinite loop
        if (this.alreadyImportedAdminPrivileges.includes(adminPrivilegeKey)) {
            return [];
        }
        this.alreadyImportedAdminPrivileges.push(adminPrivilegeKey);

        const privilegeRole = this.getPrivilegeRole(adminPrivilegeKey);

        if (!privilegeRole) {
            return [];
        }

        /**
         * Get all privileges (['product:read', fn(), 'product:update', ...])
         * and dependencies (['product.viewer', ...])
         */
        const { privileges, dependencies } = privilegeRole;

        /**
         * Resolve all privileges for dependencies
         */
        const dependenciesPrivileges = dependencies.reduce((acc, dependencyKey) => {
            return [
                ...acc,
                ...this._getPrivilegesWithDependencies(dependencyKey, shouldAddAdminPrivilege),
            ];
        }, []);

        /**
         * Look in privileges for the getPrivileges() method. If found then it
         * will be recursively resolved and the returned privileges are added
         */
        const resolvedPrivileges = privileges.reduce((acc, privilege) => {
            if (typeof privilege === 'function') {
                return [...acc, ...privilege()];
            }

            return [...acc, privilege];
        }, []);

        /**
         * Combine privileges and privileges of dependencies
         */
        const collectedPrivileges = [
            ...resolvedPrivileges,
            ...dependenciesPrivileges,
        ];

        /**
         * Only add adminPrivilege if wanted
         */
        if (shouldAddAdminPrivilege) {
            collectedPrivileges.push(adminPrivilegeKey);
        }

        return collectedPrivileges;
    }

    /**
     *
     * Use this method directly in privilegeMappingEntries. Then it will
     * automatically get all privileges dynamically from the other adminRole.
     *
     * @usage
     * Shopware.Service('privileges').addPrivilegeMappingEntry({
     *     category: 'permissions',
     *     parent: null,
     *
     *     key: 'product',
     *     roles: {
     *         viewer: {
     *             privileges: [
     *                 'product.read',
     *                 Shopware.Service('privileges').getPrivileges('rule.viewer')
     *             ],
     *             dependencies: []
     *         }
     *     }
     * })
     *
     * @example
     * // returns "() => this._getPrivilegesWithDependencies('rule.creator', false)"
     * getPrivileges('rule.editor')
     *
     * @param privilegeKey {string}
     * @returns {function(): string[]}
     */
    getPrivileges(privilegeKey) {
        return () => this._getPrivilegesWithDependencies(privilegeKey, false);
    }

    /**
     * This method gets all privileges for the given admin identifier
     *
     * @example
     * // return [
     *      'product.viewer',
     *      'product.editor',
     *      'product.creator',
     *      'product:read',
     *      'product:update',
     *      'promotion.viewer',
     *      'promotion:read',
     *      'rule:read'
     *      ...
     *    ]
     * getPrivilegesForAdminPrivilegeKeys(['promotion.viewer', 'product.creator'])
     *
     * @param adminPrivileges {string[]}
     * @returns {string[]}
     */
    getPrivilegesForAdminPrivilegeKeys(adminPrivileges) {
        // reset the global state
        this.alreadyImportedAdminPrivileges = [];

        const allPrivileges = adminPrivileges.reduce((acc, adminPrivilegeKey) => {
            const isAdminPrivilege = adminPrivilegeKey.match(/.+\..+/);

            if (!isAdminPrivilege) {
                return acc;
            }

            const privileges = this._getPrivilegesWithDependencies(adminPrivilegeKey);

            return [...acc, adminPrivilegeKey, ...privileges];
        }, []);

        return [
            // convert to Set and back to Array to remove duplicates
            ...new Set([...allPrivileges, ...this.getRequiredPrivileges()]),
        ].sort();
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
            Object.entries(privilegeMapping.roles).forEach(([role, entry]) => {
                if (existingCategoryKeyCombination.roles.hasOwnProperty(role) === true) {
                    Vue.set(
                        existingCategoryKeyCombination.roles,
                        role,
                        object.deepMergeObject(existingCategoryKeyCombination.roles[role], entry),
                    );
                } else {
                    Vue.set(existingCategoryKeyCombination.roles, role, entry);
                }
            });

            return this;
        }

        this.state.privilegesMappings.push(privilegeMapping);

        return this;
    }

    /**
     *
     * @returns {PrivilegesService}
     * @param privilegeMappings {Object[]}
     */
    addPrivilegeMappingEntries(privilegeMappings) {
        if (!Array.isArray(privilegeMappings)) {
            error('addPrivilegeMappingEntries', 'The privilegeMappings must be an array.');
            return this;
        }

        privilegeMappings.forEach((privilegeMapping) => {
            this.addPrivilegeMappingEntry(privilegeMapping);
        });

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
