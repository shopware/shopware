/**
 * @package admin
 */

import { reactive } from 'vue';

const { warn, error } = Shopware.Utils.debug;
const { object } = Shopware.Utils;

type GetPrivilegesWithDependenciesSignature = () => string[];

type PrivilegeRole = {
    dependencies: Array<string>,
    privileges: Array<string|GetPrivilegesWithDependenciesSignature>,
}

type PrivilegeMapping = {
    category: 'permissions'|'additional_permissions',
    key: null|string,
    parent: string,
    roles: {
        [key: string]: PrivilegeRole,
    }
}

type PrivilegesState = {
    privilegesMappings: PrivilegeMapping[],
}

/**
 * @private
 */
export default class PrivilegesService {
    /**
     * @deprecated tag: v6.6.0 - Will be private
     */
    public alreadyImportedAdminPrivileges: string[] = [];

    /**
     * @deprecated tag: v6.6.0 - Will be private. Use getPrivilegesMappings instead of direct access
     */
    public state = reactive<PrivilegesState>({
        privilegesMappings: [],
    });

    /**
     * @deprecated tag: v6.6.0 - Will be private. Use getRequiredMappings instead of direct access
     */
    public requiredPrivileges = [
        'language:read', // for entityInit and languageSwitch
        'locale:read', // for localeToLanguage service
        'message_queue_stats:read', // for message queue
        'log_entry:create', // for sw-error-boundary
    ];

    /**
     * Removes all keys from the given array which are not an admin role.
     *
     * Example:
     * product.viewer => Valid
     * product:read => Invalid
     */
    public filterPrivilegesRoles(privileges: string[]) {
        const onlyRoles = privileges.filter(privilegeKey => this.existsPrivilege(privilegeKey));

        return onlyRoles.filter((role, index) => onlyRoles.indexOf(role) === index);
    }

    public existsPrivilege(privilegeKey: string) {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.some(privilegeMapping => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    private _getPrivilege(privilegeKey: string): PrivilegeMapping|undefined {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.find(privilegeMapping => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    public getPrivilegeRole(privilegeKey: string): PrivilegeRole|undefined {
        const role = privilegeKey.split('.')[1];

        const privilege = this._getPrivilege(privilegeKey);

        return privilege?.roles[role] ?? undefined;
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
     */
    private _getPrivilegesWithDependencies(adminPrivilegeKey: string, shouldAddAdminPrivilege = true): string[] {
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
        const dependenciesPrivileges = dependencies.reduce((acc: string[], dependencyKey) => {
            return [
                ...acc,
                ...this._getPrivilegesWithDependencies(dependencyKey, shouldAddAdminPrivilege),
            ];
        }, []);

        /**
         * Look in privileges for the getPrivileges() method. If found then it
         * will be recursively resolved and the returned privileges are added
         */
        const resolvedPrivileges = privileges.reduce((acc: string[], privilege) => {
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
     */
    getPrivileges(privilegeKey: string) {
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
     */
    getPrivilegesForAdminPrivilegeKeys(adminPrivileges: string[]) {
        // reset the global state
        this.alreadyImportedAdminPrivileges = [];

        const allPrivileges = adminPrivileges.reduce((acc: string[], adminPrivilegeKey) => {
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

    public addPrivilegeMappingEntry(privilegeMapping: unknown) {
        if (!this.isPrivilegeMapping(privilegeMapping)) {
            return this;
        }

        const existingCategoryKeyCombination = this.state.privilegesMappings.find(mapping => {
            return mapping.category === privilegeMapping.category &&
                mapping.key === privilegeMapping.key;
        });

        if (!existingCategoryKeyCombination) {
            this.state.privilegesMappings.push(privilegeMapping);

            return this;
        }

        Object.entries(privilegeMapping.roles).forEach(([role, entry]) => {
            if (existingCategoryKeyCombination.roles.hasOwnProperty(role) === true) {
                existingCategoryKeyCombination.roles[role] =
                    object.deepMergeObject(existingCategoryKeyCombination.roles[role], entry);
            } else {
                existingCategoryKeyCombination.roles[role] = entry;
            }
        });

        return this;
    }

    private isPrivilegeMapping(privilegeMapping: unknown): privilegeMapping is PrivilegeMapping {
        if (typeof privilegeMapping !== 'object') {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping has to be an object.');
            return false;
        }

        if (privilegeMapping === null) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping must not be null.');
            return false;
        }

        if (!('category' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "category".');
            return false;
        }

        if (!('parent' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "parent".');
            return false;
        }

        if (!('key' in privilegeMapping)) {
            warn('addPrivilegeMappingEntry', 'The privilegeMapping need the property "key".');
            return false;
        }

        return true;
    }

    /**
     *
     * @returns {PrivilegesService}
     * @param privilegeMappings {Object[]}
     */
    public addPrivilegeMappingEntries(privilegeMappings: unknown) {
        if (!Array.isArray(privilegeMappings)) {
            error('addPrivilegeMappingEntries', 'The privilegeMappings must be an array.');
            return this;
        }

        privilegeMappings.forEach((privilegeMapping) => {
            this.addPrivilegeMappingEntry(privilegeMapping);
        });

        return this;
    }

    public getPrivilegesMappings() {
        return this.state.privilegesMappings;
    }

    public getRequiredPrivileges() {
        return this.requiredPrivileges;
    }
}
