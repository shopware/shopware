/**
 * @sw-package framework
 */

import { computed, nextTick } from 'vue';
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
};

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-user-settings', () => {
    const originalCurrentUser = Shopware.Store.get('session').currentUser;

    afterEach(() => {
        if (originalCurrentUser) {
            Shopware.Store.get('session').setCurrentUser(originalCurrentUser);
        } else {
            Shopware.Store.get('session').removeCurrentUser();
        }

        jest.restoreAllMocks();
    });

    it('loads and normalizes persisted column settings without saving them back immediately', async () => {
        const userConfig = {
            id: 'user-config-id',
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
        };
        const search = jest.fn<Promise<UserConfigEntity[]>, [CriteriaType, ApiContext]>().mockResolvedValue([userConfig]);
        const save = jest.fn<Promise<void>, [UserConfigEntity, ApiContext]>().mockResolvedValue();
        const create = jest.fn<UserConfigEntity, [ApiContext]>(() => ({}));
        const aclService = Shopware.Service('acl') as { can: (privilege: string) => boolean };
        const repositoryFactory = Shopware.Service('repositoryFactory') as {
            create: (entityName: keyof EntitySchema.Entities) => Repository<keyof EntitySchema.Entities>;
        };
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

        Shopware.Store.get('session').setCurrentUser({
            id: 'current-user-id',
            admin: true,
            aclRoles: [],
        } as unknown as EntitySchema.user);
        jest.spyOn(aclService, 'can').mockReturnValue(true);
        jest.spyOn(repositoryFactory, 'create').mockReturnValue({
            search,
            save,
            create,
        } as unknown as Repository<keyof EntitySchema.Entities>);

        const userSettings = useMeteorTableUserSettings({
            identifier: () => 'sw-manufacturer-list',
            resolvedColumns: columns,
        });

        await userSettings.loadUserTableSettings();
        await nextTick();

        const usedCriteriaPayload = search.mock.calls[0]?.[0].parse() as {
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
        expect(search.mock.calls[0]?.[1]).toBe(Shopware.Context.api);
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
        expect(save).not.toHaveBeenCalled();
    });
});
