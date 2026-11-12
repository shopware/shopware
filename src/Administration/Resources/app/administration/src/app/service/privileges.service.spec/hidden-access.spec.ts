/**
 * @sw-package framework
 */
import PrivilegesService from 'src/app/service/privileges.service';

type Privileges = Array<string | (() => string[])>;
type Mapping = {
    category: 'permissions' | 'additional_permissions';
    parent: string | null;
    key: string;
    roles: Record<string, { privileges: Privileges; dependencies: string[] }>;
    containsPersonalData?: unknown;
};
type Roles = Record<string, { privileges: Privileges; dependencies?: string[] }>;

function permission(key: string, roles: Roles, extra: Partial<Mapping> = {}): Mapping {
    return {
        category: 'permissions',
        parent: 'customers',
        key,
        roles: Object.fromEntries(
            Object.entries(roles).map(([role, { privileges, dependencies = [] }]) => [
                role,
                { privileges, dependencies },
            ]),
        ),
        ...extra,
    };
}

function createService(mappings: Mapping[]): PrivilegesService {
    const service = new PrivilegesService();
    mappings.forEach((mapping) => service.addPrivilegeMappingEntry(mapping));

    return service;
}

const customer = permission(
    'customer',
    {
        viewer: { privileges: ['customer:read', 'customer_address:read'] },
        creator: { privileges: ['customer:create', 'customer_address:create'] },
        deleter: { privileges: ['customer:delete'] },
    },
    { containsPersonalData: true },
);

const country = permission('country', {
    viewer: { privileges: ['country:read', 'customer:read', 'unowned_entity:read'] },
    creator: { privileges: ['country:create'] },
});

const tag = permission('tag', {
    viewer: { privileges: ['tag:read', 'customer:read'] },
    editor: { privileges: ['tag:update', 'customer:update'], dependencies: ['tag.viewer'] },
    creator: { privileges: ['tag:create'] },
});

describe('src/app/service/privileges.service hidden access', () => {
    beforeEach(() => {
        global.console.warn = jest.fn();
    });

    it('should own an entity only through direct create or delete privileges', () => {
        const service = createService([
            customer,
            country,
            permission('media', {
                viewer: { privileges: ['media:read'] },
                creator: {
                    privileges: [
                        () => ['closure_entity:create'],
                        'media:create',
                    ],
                    dependencies: ['customer.creator'],
                },
            }),
            {
                category: 'additional_permissions',
                parent: null,
                key: 'system',
                roles: { clear_cache: { privileges: ['system_entity:delete'], dependencies: [] } },
            },
        ]);

        const owners = service.getEntityOwners();

        expect(owners.get('customer')).toEqual(['customer']);
        expect(owners.get('customer_address')).toEqual(['customer']);
        expect(owners.get('country')).toEqual(['country']);
        expect(owners.get('media')).toEqual(['media']);
        // read only, closures, dependencies and additional permissions don't make an owner
        expect(owners.has('unowned_entity')).toBe(false);
        expect(owners.has('closure_entity')).toBe(false);
        expect(owners.has('system_entity')).toBe(false);
    });

    it('should group hidden privileges by owner and skip own, unowned and default privileges', () => {
        const service = createService([
            customer,
            country,
            permission('currency', { creator: { privileges: ['currency:create'] } }),
            permission('promotion', {
                viewer: { privileges: ['promotion:read', 'customer:read', 'currency:read'] },
                creator: { privileges: ['promotion:create'] },
            }),
        ]);

        expect(service.getHiddenAccess('country.viewer')).toEqual([
            {
                ownerKey: 'customer',
                privileges: ['customer:read'],
                entries: [{ operations: ['read'], entities: ['customer'] }],
                containsWrite: false,
                containsPersonalData: true,
            },
        ]);
        // currency:read is a default user privilege
        expect(service.getHiddenAccess('promotion.viewer')).toEqual([
            {
                ownerKey: 'customer',
                privileges: ['customer:read'],
                entries: [{ operations: ['read'], entities: ['customer'] }],
                containsWrite: false,
                containsPersonalData: true,
            },
        ]);
        expect(service.getHiddenAccess('customer.viewer')).toEqual([]);
        expect(service.getHiddenAccess('unknown.viewer')).toEqual([]);
    });

    it('should include privileges of dependencies', () => {
        const service = createService([
            customer,
            tag,
        ]);

        expect(service.getExpandedPrivileges('tag.editor')).toEqual(
            expect.arrayContaining([
                'tag:read',
                'tag:update',
                'customer:read',
                'customer:update',
            ]),
        );
        expect(service.getHiddenAccess('tag.editor')).toEqual([
            {
                ownerKey: 'customer',
                privileges: ['customer:read', 'customer:update'],
                entries: [{ operations: ['read', 'update'], entities: ['customer'] }],
                containsWrite: true,
                containsPersonalData: true,
            },
        ]);
    });

    it('should group entities with several owners as shared data, personal only when every owner flags it', () => {
        const service = createService([
            permission('order', { creator: { privileges: ['shared_entity:create'] } }, { containsPersonalData: true }),
            permission('review', { creator: { privileges: ['shared_entity:create', 'review_entity:create'] } }),
            permission(
                'newsletter',
                { creator: { privileges: ['both_flagged:create'] } },
                { containsPersonalData: ['both_flagged'] },
            ),
            permission('customer', { creator: { privileges: ['both_flagged:create'] } }, { containsPersonalData: true }),
            permission('product', {
                viewer: {
                    privileges: [
                        'shared_entity:read',
                        'both_flagged:read',
                        'review_entity:read',
                    ],
                },
            }),
        ]);

        expect(service.getEntityOwners().get('shared_entity')).toEqual([
            'order',
            'review',
        ]);
        expect(service.isPersonalDataEntity('shared_entity')).toBe(false);
        expect(service.isPersonalDataEntity('both_flagged')).toBe(true);
        expect(service.getHiddenAccess('product.viewer')).toEqual([
            {
                ownerKey: 'review',
                privileges: ['review_entity:read'],
                entries: [{ operations: ['read'], entities: ['review_entity'] }],
                containsWrite: false,
                containsPersonalData: false,
            },
            {
                ownerKey: null,
                privileges: [
                    'both_flagged:read',
                    'shared_entity:read',
                ],
                entries: [
                    {
                        operations: ['read'],
                        entities: [
                            'both_flagged',
                            'shared_entity',
                        ],
                    },
                ],
                containsWrite: false,
                containsPersonalData: true,
            },
        ]);
        // shared data has no single module row
        expect(service.getIndirectAccess(['product.viewer']).map((access) => access.ownerKey)).toEqual(['review']);
    });

    it('should flag only the listed entities when containsPersonalData is a list', () => {
        const service = createService([
            permission(
                'users_and_permissions',
                { creator: { privileges: ['user:create', 'acl_role:create'] } },
                { containsPersonalData: ['user'] },
            ),
        ]);

        expect(service.isPersonalDataEntity('user')).toBe(true);
        expect(service.isPersonalDataEntity('acl_role')).toBe(false);
    });

    it('should ignore an invalid containsPersonalData value with a warning', () => {
        const service = createService([
            permission('customer', { creator: { privileges: ['customer:create'] } }, { containsPersonalData: 'yes' }),
        ]);

        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The property "containsPersonalData" must be a boolean or an array of entity names. It is ignored.',
        );
        expect(service.getPrivilegesMappings()).toHaveLength(1);
        expect(service.isPersonalDataEntity('customer')).toBe(false);
    });

    it('should keep the flag when a mapping is merged into an existing one', () => {
        const service = createService([
            permission('customer', { creator: { privileges: ['customer:create'] } }),
            permission('customer', { deleter: { privileges: ['customer:delete'] } }, { containsPersonalData: true }),
        ]);

        expect(service.isPersonalDataEntity('customer')).toBe(true);
    });

    it('should leave out indirect access the owning module already grants', () => {
        const service = createService([
            customer,
            country,
            tag,
        ]);

        expect(
            service.getIndirectAccess([
                'country.viewer',
                'tag.editor',
            ]),
        ).toEqual([
            {
                ownerKey: 'customer',
                containsPersonalData: true,
                entries: [
                    {
                        privilege: 'customer:read',
                        entity: 'customer',
                        operation: 'read',
                        sourceKeys: [
                            'country.viewer',
                            'tag.editor',
                        ],
                        containsPersonalData: true,
                    },
                    {
                        privilege: 'customer:update',
                        entity: 'customer',
                        operation: 'update',
                        sourceKeys: ['tag.editor'],
                        containsPersonalData: true,
                    },
                ],
            },
        ]);

        const withOwner = service.getIndirectAccess([
            'country.viewer',
            'tag.editor',
            'customer.viewer',
        ]);
        expect(withOwner[0].entries.map((entry) => entry.privilege)).toEqual(['customer:update']);
    });

    it('should return the selected keys granting a privilege', () => {
        const service = createService([
            customer,
            country,
            tag,
        ]);

        expect(
            service.getSourceKeysForPrivilege('customer:read', [
                'country.viewer',
                'tag.viewer',
                'customer.creator',
            ]),
        ).toEqual([
            'country.viewer',
            'tag.viewer',
        ]);
    });

    it('should not show an entity with operations only another entity of the same owner gets', () => {
        const service = createService([
            permission('rule', {
                creator: { privileges: ['rule:create', 'rule_condition:create', 'rule_condition:delete'] },
                deleter: { privileges: ['rule:delete'] },
            }),
            permission('promotion', {
                // Like Shopware.Service('privileges').getPrivileges('rule.creator'), which doesn't make an owner
                editor: {
                    privileges: [
                        () => [
                            'rule:read',
                            'rule:create',
                            'rule_condition:read',
                            'rule_condition:delete',
                        ],
                    ],
                },
                creator: { privileges: ['promotion:create'] },
            }),
        ]);

        expect(service.getHiddenAccess('promotion.editor')).toEqual([
            {
                ownerKey: 'rule',
                privileges: [
                    'rule:create',
                    'rule:read',
                    'rule_condition:delete',
                    'rule_condition:read',
                ],
                entries: [
                    { operations: ['read', 'create'], entities: ['rule'] },
                    { operations: ['read', 'delete'], entities: ['rule_condition'] },
                ],
                containsWrite: true,
                containsPersonalData: false,
            },
        ]);
    });

    it('should recompute when a mapping is added later', () => {
        const service = createService([country]);

        // customer has no owner yet, so reading it is not hidden
        expect(service.getHiddenAccess('country.viewer')).toEqual([]);

        service.addPrivilegeMappingEntry(customer);

        expect(service.getHiddenAccess('country.viewer')).toEqual([
            {
                ownerKey: 'customer',
                privileges: ['customer:read'],
                entries: [{ operations: ['read'], entities: ['customer'] }],
                containsWrite: false,
                containsPersonalData: true,
            },
        ]);
    });
});
