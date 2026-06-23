/**
 * @sw-package framework
 */

import {
    createUserConfigRepositoryMock,
    createWrapper,
    flushPromises,
    getTable,
    mockUserConfigRepository,
    nextTick,
    persistedSettingsColumns,
    setCurrentUserWithUserConfigPrivileges,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/user-settings', () => {
    registerSwMeteorEntityDataTableHooks();

    it('loads table settings from user_config when an identifier is configured', async () => {
        setCurrentUserWithUserConfigPrivileges();

        const userConfigRepository = createUserConfigRepositoryMock({
            id: 'user-config-id',
            key: 'grid.setting.sw-manufacturer-list',
            userId: 'current-user-id',
            value: {
                columns: [
                    {
                        dataIndex: 'link',
                        width: 320,
                        visible: true,
                    },
                    {
                        dataIndex: 'name',
                        width: 280,
                        visible: false,
                    },
                    {
                        dataIndex: 'removed-column',
                        width: 120,
                        visible: false,
                    },
                ],
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            },
        });

        mockUserConfigRepository(userConfigRepository);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await nextTick();

        expect(userConfigRepository.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'grid.setting.sw-manufacturer-list',
                    },
                    {
                        field: 'userId',
                        type: 'equals',
                        value: 'current-user-id',
                    },
                ],
            }),
            Shopware.Context.api,
        );
        expect(getTable(wrapper).props()).toEqual(
            expect.objectContaining({
                columnChanges: {
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
                },
                showOutlines: false,
                showStripes: false,
                enableOutlineFraming: true,
                enableRowNumbering: true,
            }),
        );
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('persists table settings to user_config and restores them after remounting', async () => {
        setCurrentUserWithUserConfigPrivileges();

        const userConfigRepository = createUserConfigRepositoryMock();
        mockUserConfigRepository(userConfigRepository);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        userConfigRepository.save.mockClear();

        await wrapper.find('.mt-data-table-stub__change-column-settings').trigger('click');
        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await wrapper.find('.mt-data-table-stub__row-numbering').trigger('click');
        await flushPromises();
        await nextTick();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.save).toHaveBeenCalled();
        expect(userConfigRepository.getStoredEntity()).toEqual(
            expect.objectContaining({
                id: 'created-user-config-id',
                key: 'grid.setting.sw-manufacturer-list',
                userId: 'current-user-id',
                value: {
                    columns: [
                        {
                            property: 'link',
                            dataIndex: 'link',
                            position: 0,
                            width: 320,
                            visible: true,
                        },
                        {
                            property: 'name',
                            dataIndex: 'name',
                            position: 100,
                            width: 280,
                            visible: false,
                        },
                    ],
                    showOutlines: false,
                    showStripes: true,
                    enableOutlineFraming: false,
                    enableRowNumbering: true,
                },
            }),
        );

        wrapper.unmount();
        userConfigRepository.save.mockClear();

        const remountedWrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await nextTick();

        expect(getTable(remountedWrapper).props()).toEqual(
            expect.objectContaining({
                columnChanges: {
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
                },
                showOutlines: false,
                showStripes: true,
                enableOutlineFraming: false,
                enableRowNumbering: true,
            }),
        );
        expect(userConfigRepository.save).not.toHaveBeenCalled();
    });

    it('saves a changed existing table setting with update-only permission', async () => {
        const privileges = [
            'user_config:read',
            'user_config:update',
        ];
        setCurrentUserWithUserConfigPrivileges(privileges);

        const userConfigRepository = createUserConfigRepositoryMock({
            id: 'user-config-id',
            key: 'grid.setting.sw-manufacturer-list',
            userId: 'current-user-id',
            value: {
                columns: [],
                showOutlines: true,
            },
        });
        mockUserConfigRepository(userConfigRepository, privileges);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await nextTick();
        userConfigRepository.save.mockClear();

        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await flushPromises();

        expect(userConfigRepository.create).not.toHaveBeenCalled();
        expect(userConfigRepository.save).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.getSaveOperations()).toEqual(['update']);
    });

    it('saves a first table setting with create-only permission', async () => {
        const privileges = ['user_config:create'];
        setCurrentUserWithUserConfigPrivileges(privileges);

        const userConfigRepository = createUserConfigRepositoryMock();
        mockUserConfigRepository(userConfigRepository, privileges);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await flushPromises();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.save).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.getSaveOperations()).toEqual(['create']);
        expect(userConfigRepository.getStoredEntity()).toEqual(
            expect.objectContaining({
                key: 'grid.setting.sw-manufacturer-list',
                userId: 'current-user-id',
            }),
        );
    });

    it('does not create the same table setting twice after a successful first save', async () => {
        const privileges = [
            'user_config:create',
            'user_config:update',
        ];
        setCurrentUserWithUserConfigPrivileges(privileges);

        const userConfigRepository = createUserConfigRepositoryMock();
        mockUserConfigRepository(userConfigRepository, privileges);

        const wrapper = createWrapper({
            props: {
                identifier: 'sw-manufacturer-list',
                columns: persistedSettingsColumns,
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__show-outlines').trigger('click');
        await flushPromises();
        await wrapper.find('.mt-data-table-stub__row-numbering').trigger('click');
        await flushPromises();

        expect(userConfigRepository.create).toHaveBeenCalledTimes(1);
        expect(userConfigRepository.save).toHaveBeenCalledTimes(2);
        expect(userConfigRepository.getSaveOperations()).toEqual([
            'create',
            'update',
        ]);
    });
});
