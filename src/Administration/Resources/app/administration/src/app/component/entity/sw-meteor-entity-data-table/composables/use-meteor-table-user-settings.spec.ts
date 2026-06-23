/**
 * @sw-package framework
 */

import { computed, nextTick } from 'vue';
import { flushPromises } from '@vue/test-utils';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import { useMeteorTableUserSettings } from './use-meteor-table-user-settings';
import type { SwMeteorEntityDataTableResolvedColumn } from '../sw-meteor-entity-data-table.internal-types';

type UserConfigEntity = {
    id?: string;
    key?: string;
    userId?: string;
    value?: unknown;
    _isNew: boolean;
    isNew: () => boolean;
};

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-user-settings', () => {
    const originalCurrentUser = Shopware.Store.get('session').currentUser;
    const columns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => [
        {
            property: 'name',
            label: 'Name',
            renderer: 'text',
            position: 0,
        },
        {
            property: 'link',
            label: 'Link',
            renderer: 'text',
            position: 100,
        },
    ]);

    function createUserConfigEntity(
        overrides: Partial<Omit<UserConfigEntity, 'isNew'>> = {},
        isNew = false,
    ): UserConfigEntity {
        const userConfigEntity = {
            id: isNew ? 'created-user-config-id' : 'user-config-id',
            _isNew: isNew,
            ...overrides,
        } as UserConfigEntity;

        userConfigEntity.isNew = () => userConfigEntity._isNew;

        return userConfigEntity;
    }

    function mockUserConfigRepository({
        initialEntity = null,
        allowedPrivileges = [
            'user_config:read',
            'user_config:create',
            'user_config:update',
        ],
    }: {
        initialEntity?: UserConfigEntity | null;
        allowedPrivileges?: string[];
    } = {}) {
        let storedEntity = initialEntity;
        const saveOperations: Array<'create' | 'update'> = [];
        const search = jest.fn<Promise<UserConfigEntity[]>, [CriteriaType, ApiContext]>(() =>
            Promise.resolve(storedEntity ? [storedEntity] : []),
        );
        const save = jest.fn<Promise<void>, [UserConfigEntity, ApiContext]>((entity) => {
            saveOperations.push(entity.isNew() ? 'create' : 'update');
            storedEntity = entity;

            return Promise.resolve();
        });
        const create = jest.fn<UserConfigEntity, [ApiContext]>(() => createUserConfigEntity({}, true));
        const aclService = Shopware.Service('acl') as { can: (privilege: string) => boolean };
        const repositoryFactory = Shopware.Service('repositoryFactory') as {
            create: (entityName: keyof EntitySchema.Entities) => Repository<keyof EntitySchema.Entities>;
        };
        const can = jest.spyOn(aclService, 'can').mockImplementation((privilege) => {
            return allowedPrivileges.includes(privilege);
        });

        jest.spyOn(repositoryFactory, 'create').mockReturnValue({
            search,
            save,
            create,
        } as unknown as Repository<keyof EntitySchema.Entities>);

        Shopware.Store.get('session').setCurrentUser({
            id: 'current-user-id',
            admin: true,
            aclRoles: [],
        } as unknown as EntitySchema.user);

        return {
            can,
            search,
            save,
            create,
            saveOperations,
            getStoredEntity: () => storedEntity,
        };
    }

    afterEach(() => {
        if (originalCurrentUser) {
            Shopware.Store.get('session').setCurrentUser(originalCurrentUser);
        } else {
            Shopware.Store.get('session').removeCurrentUser();
        }

        jest.restoreAllMocks();
    });

    it('loads and normalizes persisted column settings without saving them back immediately', async () => {
        const userConfig = createUserConfigEntity({
            value: {
                columns: [
                    {
                        dataIndex: 'link',
                        width: 320,
                        visible: true,
                    },
                    {
                        dataIndex: 'removed-column',
                        width: 120,
                        visible: false,
                    },
                    {
                        dataIndex: 'name',
                        width: 280,
                        visible: false,
                    },
                ],
                showOutlines: false,
            },
        });
        const userConfigRepository = mockUserConfigRepository({
            initialEntity: userConfig,
        });

        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        await userSettings.loadUserTableSettings();
        await nextTick();

        const usedCriteriaPayload = userConfigRepository.search.mock.calls[0]?.[0].parse() as {
            filter?: Array<{
                field?: string;
                value?: string;
            }>;
        };

        expect(usedCriteriaPayload.filter).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    field: 'key',
                    value: 'grid.setting.sw-manufacturer-list',
                }),
                expect.objectContaining({
                    field: 'userId',
                    value: 'current-user-id',
                }),
            ]),
        );
        expect(userConfigRepository.search.mock.calls[0]?.[1]).toBe(Shopware.Context.api);
        expect(userSettings.tableColumnChanges).toEqual({
            link: {
                position: 0,
                width: 320,
                visible: true,
            },
            name: {
                position: 100,
                width: 280,
                visible: false,
            },
        });
        expect(userSettings.showOutlines.value).toBe(false);
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('updates an existing setting with update permission only', async () => {
        const existingUserConfig = createUserConfigEntity({
            value: {},
        });
        const userConfigRepository = mockUserConfigRepository({
            initialEntity: existingUserConfig,
            allowedPrivileges: [
                'user_config:read',
                'user_config:update',
            ],
        });

        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        await userSettings.loadUserTableSettings();
        userConfigRepository.save.mockClear();
        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.create).not.toHaveBeenCalled();
        expect(userConfigRepository.save).toHaveBeenCalledWith(existingUserConfig, Shopware.Context.api);
        expect(userConfigRepository.saveOperations).toEqual(['update']);
    });

    it('does not update an existing setting without update permission', async () => {
        const existingUserConfig = createUserConfigEntity({
            value: {},
        });
        const userConfigRepository = mockUserConfigRepository({
            initialEntity: existingUserConfig,
            allowedPrivileges: [
                'user_config:read',
                'user_config:create',
            ],
        });

        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        await userSettings.loadUserTableSettings();
        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('creates a first setting with create permission only', async () => {
        const userConfigRepository = mockUserConfigRepository({
            allowedPrivileges: ['user_config:create'],
        });
        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.save).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.saveOperations).toEqual(['create']);
        expect(userConfigRepository.getStoredEntity()).toEqual(
            expect.objectContaining({
                key: 'grid.setting.sw-manufacturer-list',
                userId: 'current-user-id',
            }),
        );
    });

    it('does not create a first setting without create permission', async () => {
        const userConfigRepository = mockUserConfigRepository({
            allowedPrivileges: ['user_config:update'],
        });
        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.create).not.toHaveBeenCalled();
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('requires create permission while the current setting is still new', async () => {
        const unsavedUserConfig = createUserConfigEntity(
            {
                value: {},
            },
            true,
        );
        const userConfigRepository = mockUserConfigRepository({
            initialEntity: unsavedUserConfig,
            allowedPrivileges: [
                'user_config:read',
                'user_config:create',
            ],
        });

        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        await userSettings.loadUserTableSettings();
        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.saveOperations).toEqual(['create']);
    });

    it('uses update permission for later saves after successfully creating a setting', async () => {
        const userConfigRepository = mockUserConfigRepository({
            allowedPrivileges: [
                'user_config:create',
                'user_config:update',
            ],
        });
        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        userSettings.setShowOutlines(false);
        await flushPromises();

        expect(userConfigRepository.saveOperations).toEqual(['create']);

        userConfigRepository.can.mockClear();
        userSettings.setEnableRowNumbering(true);
        await flushPromises();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.saveOperations).toEqual([
            'create',
            'update',
        ]);
        expect(userConfigRepository.can).toHaveBeenCalledWith('user_config:update');
        expect(userConfigRepository.can).not.toHaveBeenCalledWith('user_config:create');
    });
});
