/**
 * @sw-package framework
 */

import { computed, reactive } from 'vue';

const { warn, error } = Shopware.Utils.debug;
const { object } = Shopware.Utils;

type GetPrivilegesWithDependenciesSignature = () => string[];

type PrivilegeRole = {
    dependencies: Array<string>;
    privileges: Array<string | GetPrivilegesWithDependenciesSignature>;
};

type PrivilegeMapping = {
    category: 'permissions' | 'additional_permissions';
    key: null | string;
    parent: string;
    roles: {
        [key: string]: PrivilegeRole;
    };
    /**
     * Marks the entities owned by this mapping as personal data.
     * `true` flags every owned entity, a list of entity names flags only those.
     */
    containsPersonalData?: boolean | string[];
};

type PrivilegesState = {
    privilegesMappings: PrivilegeMapping[];
};

/**
 * Privileges of one admin privilege key on entities owned by other modules.
 * `ownerKey` is null for entities owned by more than one module ("shared data").
 *
 * @private
 */
export type HiddenAccessGroup = {
    ownerKey: string | null;
    privileges: string[];
    /**
     * Entities that share exactly the same operations, so no entity is shown with an operation it doesn't get.
     */
    entries: Array<{
        operations: string[];
        entities: string[];
    }>;
    containsWrite: boolean;
    containsPersonalData: boolean;
};

/**
 * Access to the entities of one owning module through admin privilege keys of other modules.
 *
 * @private
 */
export type IndirectAccess = {
    ownerKey: string;
    containsPersonalData: boolean;
    entries: Array<{
        privilege: string;
        entity: string;
        operation: string;
        sourceKeys: string[];
        containsPersonalData: boolean;
    }>;
};

type AccessCache = {
    entityOwners: Map<string, string[]>;
    personalDataEntities: Set<string>;
    expandedKeys: Map<string, string[]>;
};

const OPERATION_ORDER = [
    'read',
    'update',
    'create',
    'delete',
];

function sortOperations(operations: Iterable<string>): string[] {
    return [...new Set(operations)].sort((a, b) => {
        const rankA = OPERATION_ORDER.indexOf(a);
        const rankB = OPERATION_ORDER.indexOf(b);

        return (rankA === -1 ? OPERATION_ORDER.length : rankA) - (rankB === -1 ? OPERATION_ORDER.length : rankB);
    });
}

/**
 * @private
 */
export default class PrivilegesService {
    private alreadyImportedAdminPrivileges: string[] = [];

    private state = reactive<PrivilegesState>({
        privilegesMappings: [],
    });

    /**
     * Rebuilt whenever a privilege mapping is added or changed, read lazily.
     */
    private accessCache = computed<AccessCache>(() => this.buildAccessCache());

    private hiddenAccessCache = new WeakMap<AccessCache, Map<string, HiddenAccessGroup[]>>();

    private defaultUserPrivileges = [
        'language:read', // for entityInit and languageSwitch
        'locale:read', // for localeToLanguage service
        'message_queue_stats:read', // for message queue
        'log_entry:create', // for sw-error-boundary
        'currency:read', // for global currency formatting and custom fields
        'country:read', // for globally reused country selectors
        'scheduled_task:read', // for the admin worker run interval
    ];

    /**
     * Removes all keys from the given array which are not an admin role.
     *
     * Example:
     * product.viewer => Valid
     * product:read => Invalid
     */
    public filterPrivilegesRoles(privileges: string[]) {
        const onlyRoles = privileges.filter((privilegeKey) => this.existsPrivilege(privilegeKey));

        return onlyRoles.filter((role, index) => onlyRoles.indexOf(role) === index);
    }

    public existsPrivilege(privilegeKey: string) {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.some((privilegeMapping) => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    private _getPrivilege(privilegeKey: string): PrivilegeMapping | undefined {
        const [key, role] = privilegeKey.split('.');

        return this.state.privilegesMappings.find((privilegeMapping) => {
            return privilegeMapping.key === key && role in privilegeMapping.roles;
        });
    }

    public getPrivilegeRole(privilegeKey: string): PrivilegeRole | undefined {
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
            return [...acc, ...this._getPrivilegesWithDependencies(dependencyKey, shouldAddAdminPrivilege)];
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
        const collectedPrivileges = [...resolvedPrivileges, ...dependenciesPrivileges];

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
            ...new Set(allPrivileges),
        ].sort();
    }

    public addPrivilegeMappingEntry(privilegeMapping: unknown) {
        if (!this.isPrivilegeMapping(privilegeMapping)) {
            return this;
        }

        const existingCategoryKeyCombination = this.state.privilegesMappings.find((mapping) => {
            return mapping.category === privilegeMapping.category && mapping.key === privilegeMapping.key;
        });

        if (!existingCategoryKeyCombination) {
            this.state.privilegesMappings.push(privilegeMapping);

            return this;
        }

        if (privilegeMapping.containsPersonalData !== undefined) {
            existingCategoryKeyCombination.containsPersonalData = privilegeMapping.containsPersonalData;
        }

        Object.entries(privilegeMapping.roles).forEach(([role, entry]) => {
            if (existingCategoryKeyCombination.roles.hasOwnProperty(role)) {
                existingCategoryKeyCombination.roles[role] = object.deepMergeObject(
                    existingCategoryKeyCombination.roles[role],
                    entry,
                );
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

        if (
            'containsPersonalData' in privilegeMapping &&
            typeof privilegeMapping.containsPersonalData !== 'boolean' &&
            !Array.isArray(privilegeMapping.containsPersonalData)
        ) {
            warn(
                'addPrivilegeMappingEntry',
                'The property "containsPersonalData" must be a boolean or an array of entity names. It is ignored.',
            );
            delete (privilegeMapping as { containsPersonalData?: unknown }).containsPersonalData;
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

    public getDefaultUserPrivileges() {
        return this.defaultUserPrivileges;
    }

    public getRequiredPrivileges() {
        return this.getDefaultUserPrivileges();
    }

    /**
     * Returns the owning module keys per entity.
     * A module owns an entity when one of its roles grants `<entity>:create` or `<entity>:delete`
     * directly as a string, not through `getPrivileges()` or a dependency.
     */
    public getEntityOwners(): Map<string, string[]> {
        return this.accessCache.value.entityOwners;
    }

    /**
     * An entity is personal data when every module owning it flags it with `containsPersonalData`.
     */
    public isPersonalDataEntity(entity: string): boolean {
        return this.accessCache.value.personalDataEntities.has(entity);
    }

    /**
     * Returns all entity privileges (`entity:operation`) the given admin privilege key grants,
     * including its dependencies.
     */
    public getExpandedPrivileges(adminPrivilegeKey: string): string[] {
        return this.accessCache.value.expandedKeys.get(adminPrivilegeKey) ?? [];
    }

    /**
     * Returns the privileges the given admin privilege key grants on entities owned by other modules,
     * grouped by the owning module.
     * Entities without an owner and default user privileges are not hidden;
     * entities with several owners are grouped under `ownerKey: null`.
     */
    public getHiddenAccess(adminPrivilegeKey: string): HiddenAccessGroup[] {
        const cache = this.accessCache.value;
        let hiddenAccessByKey = this.hiddenAccessCache.get(cache);

        if (!hiddenAccessByKey) {
            hiddenAccessByKey = new Map();
            this.hiddenAccessCache.set(cache, hiddenAccessByKey);
        }

        const cached = hiddenAccessByKey.get(adminPrivilegeKey);
        if (cached) {
            return cached;
        }

        const moduleKey = adminPrivilegeKey.split('.')[0];
        const hiddenPrivileges = this.getExpandedPrivileges(adminPrivilegeKey).filter((privilege) => {
            // Every user gets the default privileges, so they reveal nothing
            if (this.defaultUserPrivileges.includes(privilege)) {
                return false;
            }

            const owners = cache.entityOwners.get(privilege.split(':')[0]);

            return owners !== undefined && !owners.includes(moduleKey);
        });

        const hiddenAccess = this.groupHiddenPrivileges(hiddenPrivileges);
        hiddenAccessByKey.set(adminPrivilegeKey, hiddenAccess);

        return hiddenAccess;
    }

    /**
     * Combines the hidden access of several admin privilege keys, for example all selected roles of one module.
     */
    public getHiddenAccessForAdminPrivilegeKeys(adminPrivilegeKeys: string[]): HiddenAccessGroup[] {
        if (adminPrivilegeKeys.length === 1) {
            return this.getHiddenAccess(adminPrivilegeKeys[0]);
        }

        const hiddenPrivileges = adminPrivilegeKeys.flatMap((adminPrivilegeKey) => {
            return this.getHiddenAccess(adminPrivilegeKey).flatMap((group) => group.privileges);
        });

        return this.groupHiddenPrivileges(hiddenPrivileges);
    }

    /**
     * Returns the selected admin privilege keys whose expanded privileges contain the given entity privilege.
     */
    public getSourceKeysForPrivilege(privilege: string, selectedAdminPrivilegeKeys: string[]): string[] {
        return selectedAdminPrivilegeKeys.filter((adminPrivilegeKey) => {
            return this.getExpandedPrivileges(adminPrivilegeKey).includes(privilege);
        });
    }

    /**
     * Returns, per owning module, the privileges on its entities that the selection grants through other modules.
     * Privileges already granted by a selected role of the owning module itself are left out.
     * Entities with several owners are left out, because no single module row can show them.
     */
    public getIndirectAccess(selectedAdminPrivilegeKeys: string[]): IndirectAccess[] {
        const cache = this.accessCache.value;
        const selectedKeys = [...new Set(selectedAdminPrivilegeKeys)].filter((adminPrivilegeKey) => {
            return cache.expandedKeys.has(adminPrivilegeKey);
        });
        const indirectAccess = new Map<string, Map<string, IndirectAccess['entries'][number]>>();

        selectedKeys.forEach((sourceKey) => {
            this.getHiddenAccess(sourceKey).forEach((group) => {
                const ownerKey = group.ownerKey;

                if (ownerKey === null) {
                    return;
                }

                const ownerPrivileges = new Set(
                    selectedKeys
                        .filter((adminPrivilegeKey) => adminPrivilegeKey.split('.')[0] === ownerKey)
                        .flatMap((adminPrivilegeKey) => this.getExpandedPrivileges(adminPrivilegeKey)),
                );

                group.privileges.forEach((privilege) => {
                    if (ownerPrivileges.has(privilege)) {
                        return;
                    }

                    let entries = indirectAccess.get(ownerKey);
                    if (!entries) {
                        entries = new Map();
                        indirectAccess.set(ownerKey, entries);
                    }

                    const [entity, operation] = privilege.split(':');
                    const entry = entries.get(privilege) ?? {
                        privilege,
                        entity,
                        operation,
                        sourceKeys: [],
                        containsPersonalData: cache.personalDataEntities.has(entity),
                    };

                    entry.sourceKeys.push(sourceKey);
                    entries.set(privilege, entry);
                });
            });
        });

        return [...indirectAccess.entries()].map(([ownerKey, entries]) => {
            const entryList = [...entries.values()];

            return {
                ownerKey,
                containsPersonalData: entryList.some((entry) => entry.containsPersonalData),
                entries: entryList,
            };
        });
    }

    private groupHiddenPrivileges(privileges: string[]): HiddenAccessGroup[] {
        const { entityOwners, personalDataEntities } = this.accessCache.value;
        const groups = new Map<string | null, Set<string>>();

        privileges.forEach((privilege) => {
            const owners = entityOwners.get(privilege.split(':')[0]);

            if (!owners) {
                return;
            }

            const ownerKey = owners.length === 1 ? owners[0] : null;
            const group = groups.get(ownerKey) ?? new Set<string>();
            group.add(privilege);
            groups.set(ownerKey, group);
        });

        return [...groups.entries()]
            .map(([ownerKey, groupPrivileges]) => {
                const sortedPrivileges = [...groupPrivileges].sort();

                const operationsByEntity = new Map<string, string[]>();
                sortedPrivileges.forEach((privilege) => {
                    const [entity, operation] = privilege.split(':');
                    operationsByEntity.set(entity, [...(operationsByEntity.get(entity) ?? []), operation]);
                });

                const entitiesByOperations = new Map<string, { operations: string[]; entities: string[] }>();
                operationsByEntity.forEach((operations, entity) => {
                    const sortedOperations = sortOperations(operations);
                    const operationsKey = sortedOperations.join(',');
                    const entry = entitiesByOperations.get(operationsKey) ?? { operations: sortedOperations, entities: [] };
                    entry.entities.push(entity);
                    entitiesByOperations.set(operationsKey, entry);
                });

                return {
                    ownerKey,
                    privileges: sortedPrivileges,
                    // Widest access first
                    entries: [...entitiesByOperations.values()].sort((a, b) => b.operations.length - a.operations.length),
                    containsWrite: sortedPrivileges.some((privilege) => privilege.split(':')[1] !== 'read'),
                    containsPersonalData: sortedPrivileges.some((privilege) => {
                        return personalDataEntities.has(privilege.split(':')[0]);
                    }),
                };
            })
            .sort((a, b) => {
                // Shared data goes last
                if (a.ownerKey === null || b.ownerKey === null) {
                    return a.ownerKey === null ? 1 : -1;
                }

                return a.ownerKey.localeCompare(b.ownerKey);
            });
    }

    private buildAccessCache(): AccessCache {
        const permissionMappings = this.state.privilegesMappings.filter((mapping) => {
            return mapping.category === 'permissions' && typeof mapping.key === 'string';
        });

        const entityOwners = new Map<string, string[]>();
        permissionMappings.forEach((mapping) => {
            const moduleKey = mapping.key as string;

            Object.values(mapping.roles).forEach((role) => {
                role.privileges.forEach((privilege) => {
                    if (typeof privilege !== 'string') {
                        return;
                    }

                    const [entity, operation] = privilege.split(':');
                    if (operation !== 'create' && operation !== 'delete') {
                        return;
                    }

                    const owners = entityOwners.get(entity) ?? [];
                    if (!owners.includes(moduleKey)) {
                        owners.push(moduleKey);
                    }
                    entityOwners.set(entity, owners);
                });
            });
        });

        const personalDataEntities = new Set<string>();
        entityOwners.forEach((owners, entity) => {
            const isPersonalData = owners.every((ownerKey) => {
                const flag = permissionMappings.find((mapping) => mapping.key === ownerKey)?.containsPersonalData;

                return flag === true || (Array.isArray(flag) && flag.includes(entity));
            });

            if (isPersonalData) {
                personalDataEntities.add(entity);
            }
        });

        const expandedKeys = new Map<string, string[]>();
        this.state.privilegesMappings.forEach((mapping) => {
            Object.keys(mapping.roles).forEach((role) => {
                const adminPrivilegeKey = `${mapping.key}.${role}`;

                expandedKeys.set(
                    adminPrivilegeKey,
                    this.getPrivilegesForAdminPrivilegeKeys([adminPrivilegeKey]).filter((privilege) => {
                        return privilege.includes(':');
                    }),
                );
            });
        });

        return { entityOwners, personalDataEntities, expandedKeys };
    }
}
