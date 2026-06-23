/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations, jest/require-top-level-describe */

import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { h, nextTick, ref } from 'vue';
import type { SetupContext } from 'vue';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import SwMeteorEntityDataTable from '../sw-meteor-entity-data-table.vue';
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableCriteriaResolver,
    SwMeteorEntityDataTableCriteriaResolverPayload,
    SwMeteorEntityDataTableLayout,
    SwMeteorEntityDataTableState,
} from '../sw-meteor-entity-data-table.types';
import { MtDataTableStub, globalStubs } from './stubs';

export const componentName = 'sw-meteor-entity-data-table';
export const { Criteria } = Shopware.Data;
export const shopwareApplication = Shopware.Application as unknown as {
    view: {
        router?: TestRouter;
    };
};

export type TestRepository = Repository<keyof EntitySchema.Entities>;

export type TestColumn = SwMeteorEntityDataTableColumn;

export type TestRecord = {
    id: string;
    name: string;
    [key: string]: unknown;
};

export type TestSearchMock = jest.Mock<Promise<EntityCollection<keyof EntitySchema.Entities>>, [CriteriaType, ApiContext]>;
export type TestDeleteMock = jest.Mock<Promise<unknown>, [string, ApiContext]>;
export type TestSyncDeletedMock = jest.Mock<Promise<void>, [string[], ApiContext]>;
export type TestSaveMock = jest.Mock<Promise<void>, [TestRecord, ApiContext]>;

export type TestProps = {
    repository: TestRepository;
    columns: TestColumn[];
    identifier?: string;
    criteria?: CriteriaType | null;
    criteriaResolver?: SwMeteorEntityDataTableCriteriaResolver | null;
    context?: ApiContext | null;
    initialPage?: number;
    initialLimit?: number;
    initialSearchTerm?: string;
    initialSort?: SwMeteorEntityDataTableState['sort'] | null;
    paginationOptions?: number[];
    layout?: SwMeteorEntityDataTableLayout;
    searchable?: boolean;
    reloadable?: boolean;
    selectable?: boolean;
    detailRoute?: string;
    allowEdit?: boolean;
    allowInlineEdit?: boolean;
    allowDelete?: boolean;
    hideTableSettings?: boolean;
    additionalContextButtons?: Array<{
        key: string;
        label: string;
        type?: 'default' | 'active' | 'critical';
    }>;
};

export type SlotRenderer = string | ((slotProps: Record<string, unknown>) => ReturnType<typeof h>);
export type SlotRenderers = Record<string, SlotRenderer>;

export type TestRouter = {
    push: jest.Mock;
};

export type TestUserConfigEntityInput = {
    id?: string;
    key?: string;
    userId?: string;
    value?: unknown;
    _isNew?: boolean;
};

export type TestUserConfigEntity = TestUserConfigEntityInput & {
    _isNew: boolean;
    isNew: () => boolean;
};

export type TestUserConfigSaveOperation = 'create' | 'update';

export type TestUserConfigRepository = {
    search: jest.Mock<Promise<TestUserConfigEntity[]>, [CriteriaType, ApiContext]>;
    save: jest.Mock<Promise<void>, [TestUserConfigEntity, ApiContext]>;
    create: jest.Mock<TestUserConfigEntity, [ApiContext]>;
    getStoredEntity: () => TestUserConfigEntity | null;
    getSaveOperations: () => TestUserConfigSaveOperation[];
};

export const columns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
];

export const inlineEditColumns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
        inlineEdit: 'string',
    },
];

export const persistedSettingsColumns: TestColumn[] = [
    {
        label: 'Name',
        property: 'name',
    },
    {
        label: 'Link',
        property: 'link',
    },
];

export const records: TestRecord[] = [
    {
        id: 'record-1',
        name: 'First record',
    },
    {
        id: 'record-2',
        name: 'Second record',
    },
];

export const allUserConfigPrivileges = [
    'user_config:read',
    'user_config:create',
    'user_config:update',
];

export function createSearchResult(
    resultRecords: TestRecord[] = records,
    total = resultRecords.length,
): EntityCollection<keyof EntitySchema.Entities> {
    return Object.assign([...resultRecords], { total }) as unknown as EntityCollection<keyof EntitySchema.Entities>;
}

export function createRepositoryMock(searchResult = createSearchResult()): TestRepository {
    const search: TestSearchMock = jest.fn<ReturnType<TestSearchMock>, Parameters<TestSearchMock>>();
    const deleteMock: TestDeleteMock = jest.fn<ReturnType<TestDeleteMock>, Parameters<TestDeleteMock>>();
    const syncDeletedMock: TestSyncDeletedMock = jest.fn<ReturnType<TestSyncDeletedMock>, Parameters<TestSyncDeletedMock>>();
    const saveMock: TestSaveMock = jest.fn<ReturnType<TestSaveMock>, Parameters<TestSaveMock>>();
    search.mockResolvedValue(searchResult);
    deleteMock.mockResolvedValue({});
    syncDeletedMock.mockResolvedValue();
    saveMock.mockResolvedValue();

    return {
        search,
        delete: deleteMock,
        syncDeleted: syncDeletedMock,
        save: saveMock,
    } as unknown as TestRepository;
}

export function createRepositoryMockWithSearch(search: TestSearchMock): TestRepository {
    const deleteMock: TestDeleteMock = jest.fn<ReturnType<TestDeleteMock>, Parameters<TestDeleteMock>>();
    const syncDeletedMock: TestSyncDeletedMock = jest.fn<ReturnType<TestSyncDeletedMock>, Parameters<TestSyncDeletedMock>>();
    const saveMock: TestSaveMock = jest.fn<ReturnType<TestSaveMock>, Parameters<TestSaveMock>>();
    deleteMock.mockResolvedValue({});
    syncDeletedMock.mockResolvedValue();
    saveMock.mockResolvedValue();

    return {
        search,
        delete: deleteMock,
        syncDeleted: syncDeletedMock,
        save: saveMock,
    } as unknown as TestRepository;
}

function createUserConfigEntityMock(entity: TestUserConfigEntityInput = {}, isNew = false): TestUserConfigEntity {
    const userConfigEntity = {
        id: isNew ? 'created-user-config-id' : 'user-config-id',
        _isNew: isNew,
        ...entity,
    } as TestUserConfigEntity;

    userConfigEntity.isNew = () => userConfigEntity._isNew;

    return userConfigEntity;
}

export function createUserConfigRepositoryMock(
    initialEntity: TestUserConfigEntityInput | null = null,
): TestUserConfigRepository {
    let storedEntity = initialEntity ? createUserConfigEntityMock(initialEntity, initialEntity._isNew ?? false) : null;
    const saveOperations: TestUserConfigSaveOperation[] = [];
    const search = jest.fn<Promise<TestUserConfigEntity[]>, [CriteriaType, ApiContext]>(() =>
        Promise.resolve(storedEntity ? [storedEntity] : []),
    );
    const save = jest.fn<Promise<void>, [TestUserConfigEntity, ApiContext]>((entity) => {
        saveOperations.push(entity.isNew() ? 'create' : 'update');
        storedEntity = entity;

        return Promise.resolve();
    });
    const create = jest.fn<TestUserConfigEntity, [ApiContext]>(() => createUserConfigEntityMock({}, true));

    return {
        search,
        save,
        create,
        getStoredEntity: () => storedEntity,
        getSaveOperations: () => saveOperations,
    };
}

export function mockUserConfigRepository(
    userConfigRepository: TestUserConfigRepository,
    allowedPrivileges = allUserConfigPrivileges,
): void {
    const aclService = Shopware.Service('acl') as {
        can: (privilege: string) => boolean;
    };
    const repositoryFactory = Shopware.Service('repositoryFactory') as {
        create: (entityName: keyof EntitySchema.Entities) => Repository<keyof EntitySchema.Entities>;
    };

    jest.spyOn(aclService, 'can').mockImplementation((privilege) => allowedPrivileges.includes(privilege));
    jest.spyOn(repositoryFactory, 'create').mockImplementation((entityName) => {
        if (entityName === 'user_config') {
            return userConfigRepository as unknown as Repository<keyof EntitySchema.Entities>;
        }

        return createRepositoryMock() as unknown as Repository<keyof EntitySchema.Entities>;
    });
}

export function setCurrentUserWithUserConfigPrivileges(privileges = allUserConfigPrivileges): void {
    Shopware.Store.get('session').setCurrentUser({
        id: 'current-user-id',
        admin: true,
        aclRoles: [
            {
                privileges,
            },
        ],
    } as EntitySchema.user);
}

export function getSearchMock(repository: TestRepository): TestSearchMock {
    return (repository as unknown as { search: TestSearchMock }).search;
}

export function getDeleteMock(repository: TestRepository): TestDeleteMock {
    return (repository as unknown as { delete: TestDeleteMock }).delete;
}

export function getSyncDeletedMock(repository: TestRepository): TestSyncDeletedMock {
    return (repository as unknown as { syncDeleted: TestSyncDeletedMock }).syncDeleted;
}

export function getSaveMock(repository: TestRepository): TestSaveMock {
    return (repository as unknown as { save: TestSaveMock }).save;
}

export function createWrapper(
    options: {
        props?: Partial<TestProps>;
        slots?: SlotRenderers;
        router?: TestRouter;
    } = {},
) {
    return mount(SwMeteorEntityDataTable, {
        props: {
            repository: createRepositoryMock(),
            columns,
            ...options.props,
        },
        slots: options.slots,
        global: {
            mocks: options.router
                ? {
                      $router: options.router,
                  }
                : {},
            stubs: globalStubs,
        },
    });
}

export function createListingConsumerWrapper(repository = createRepositoryMock()) {
    return mount(
        {
            name: 'sw-meteor-entity-data-table-listing-consumer',
            components: {
                SwMeteorEntityDataTable,
            },
            mixins: [
                Shopware.Mixin.getByName('listing'),
            ],
            template: `
                <sw-meteor-entity-data-table
                    :repository="repository"
                    :columns="columns"
                    selectable
                    @selection-change="updateSelection"
                />
            `,
            data() {
                return {
                    repository,
                    columns,
                    disableRouteParams: true,
                };
            },
            methods: {
                getList: jest.fn(),
            },
        },
        {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.index',
                        query: {},
                        params: {},
                    },
                    $router: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                },
                provide: {
                    searchRankingService: {
                        isValidTerm: (term?: string) => {
                            return !!term && term.trim().length >= 1;
                        },
                        getSearchFieldsByEntity: jest.fn(),
                        buildSearchQueriesForEntity: jest.fn(),
                    },
                    feature: {},
                },
                stubs: globalStubs,
            },
        },
    );
}

export function getTable(wrapper: VueWrapper) {
    return wrapper.findComponent(MtDataTableStub);
}

export function getLastSearchCriteria(repository: TestRepository): CriteriaType {
    const searchMock = getSearchMock(repository);
    const lastCall = searchMock.mock.calls[searchMock.mock.calls.length - 1];

    expect(lastCall).toBeDefined();

    return lastCall[0];
}

export function getSetupState(wrapper: VueWrapper): Record<string, unknown> {
    return (wrapper.vm.$ as unknown as { setupState: Record<string, unknown> }).setupState;
}

export function createDeferred<TValue>() {
    let resolve: (value: TValue) => void = () => {};
    let reject: (reason?: unknown) => void = () => {};
    const promise = new Promise<TValue>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return {
        promise,
        resolve,
        reject,
    };
}

export {
    SwMeteorEntityDataTable,
    flushPromises,
    h,
    mount,
    MtDataTableStub,
    nextTick,
    overrideComponentSetup,
    ref,
    globalStubs,
    _overridesMap,
};

export type {
    ApiContext,
    CriteriaType,
    EntityCollection,
    Repository,
    SetupContext,
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableCriteriaResolver,
    SwMeteorEntityDataTableCriteriaResolverPayload,
    SwMeteorEntityDataTableLayout,
    SwMeteorEntityDataTableState,
    VueWrapper,
};

export function registerSwMeteorEntityDataTableHooks(): void {
    const originalRouter = shopwareApplication.view.router;
    const originalCurrentUser = Shopware.Store.get('session').currentUser;

    beforeEach(() => {
        delete _overridesMap[componentName];
        Shopware.Component.getOverrideRegistry().delete(componentName);
    });

    afterEach(() => {
        shopwareApplication.view.router = originalRouter;
        Shopware.Store.get('shopwareApps').selectedIds = [];
        Shopware.Store.get('swBulkEdit').selectedIds = [];

        if (originalCurrentUser) {
            Shopware.Store.get('session').setCurrentUser(originalCurrentUser);
        } else {
            Shopware.Store.get('session').removeCurrentUser();
        }

        jest.restoreAllMocks();
    });
}
