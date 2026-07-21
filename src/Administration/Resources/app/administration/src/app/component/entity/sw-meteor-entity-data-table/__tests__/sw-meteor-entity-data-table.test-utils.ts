/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { mount, type VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';
// @ts-expect-error - Jest resolves this SFC import without the `.vue` suffix; the suffix is reserved by the Vue package mapper.
import SwMeteorEntityDataTable from '../sw-meteor-entity-data-table'; // eslint-disable-line import/extensions

export { SwMeteorEntityDataTable };

export type TableRecord = Record<string, unknown> & { id?: string };

type TableState = {
    page: number;
    limit: number;
    searchTerm: string;
    sortBy: string;
    sortDirection: string;
    naturalSorting: boolean;
};

type TableVm = {
    $nextTick: () => Promise<void>;
    $t: (key: string) => string;
    records: TableRecord[];
    total: number;
    loading: boolean;
    state: TableState;
    selectedIds: string[];
    selection: Record<string, TableRecord>;
    resolvedColumns: Array<Record<string, unknown> & { position?: number }>;
    load: () => Promise<unknown>;
    reload: () => Promise<unknown>;
    setSelectedIds: (ids: string[]) => void;
    confirmDelete?: unknown;
    [key: string]: unknown;
};

type StubVm = {
    $emit: (event: string, ...args: unknown[]) => void | Promise<void>;
};

export type TestWrapper = VueWrapper<TableVm>;

type StubWrapper = VueWrapper<StubVm> & {
    props: (name: string) => unknown;
};

type Deferred<T> = {
    promise: Promise<T>;
    resolve: (value: T) => void;
    reject: (reason?: unknown) => void;
};

export function createDeferred<T>(): Deferred<T> {
    let resolve!: (value: T) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return { promise, resolve, reject };
}

export function createSearchResult(records: TableRecord[], total = records.length) {
    return Object.assign([...records], {
        total,
        criteria: new Criteria(1, 25),
        context: { languageId: 'test-language-id' },
    });
}

export function mountedTable(wrapper: VueWrapper<unknown>): StubWrapper {
    return wrapper.getComponent('.mt-data-table-stub') as unknown as StubWrapper;
}

export function firstSearchCriteria(repository: { search: { mock: { calls: unknown[][] } } }): Criteria {
    return repository.search.mock.calls[0]?.[0] as Criteria;
}

export function lastSearchCriteria(repository: { search: { mock: { calls: unknown[][] } } }): Criteria {
    const calls = repository.search.mock.calls;

    return calls[calls.length - 1]?.[0] as Criteria;
}

export async function createWrapper(
    props: Record<string, unknown> = {},
    options: {
        mocks?: Record<string, unknown>;
        globalProperties?: Record<string, unknown>;
        provide?: Record<string, unknown>;
        slots?: Record<string, string>;
    } = {},
): Promise<TestWrapper> {
    const translate =
        options.mocks?.$t ??
        options.globalProperties?.$t ??
        ((key: string, values?: { count?: number }) => {
            return values?.count ? `${key} ${values.count}` : key;
        });

    const repository = props.repository ?? {
        search: jest.fn(() =>
            Promise.resolve(
                createSearchResult(
                    [
                        { id: 'manufacturer-1', name: 'Shopware' },
                        { id: 'manufacturer-2', name: 'Meteor' },
                    ],
                    42,
                ),
            ),
        ),
    };
    const hasRepositoryProp = Object.prototype.hasOwnProperty.call(props, 'repository');
    const shouldUseDefaultRepository = !hasRepositoryProp && !props.entity;

    const wrapper = mount(SwMeteorEntityDataTable, {
        props: {
            ...(shouldUseDefaultRepository ? { repository } : {}),
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    primary: true,
                },
            ],
            ...props,
        },
        slots: options.slots,
        global: {
            stubs: {
                'mt-data-table': {
                    props: [
                        'dataSource',
                        'columns',
                        'isLoading',
                        'paginationTotalItems',
                        'currentPage',
                        'paginationLimit',
                        'layout',
                        'allowBulkDelete',
                        'disableEdit',
                        'disableDelete',
                        'additionalContextButtons',
                        'columnChanges',
                        'enableRowNumbering',
                        'showStripes',
                        'showOutlines',
                        'enableOutlineFraming',
                        'searchValue',
                    ],
                    emits: [
                        'pagination-current-page-change',
                        'pagination-limit-change',
                        'sort-change',
                        'search-value-change',
                        'reload',
                        'selection-change',
                        'multiple-selection-change',
                        'open-details',
                        'item-delete',
                        'bulk-delete',
                        'context-select',
                        'change-enable-row-numbering',
                        'change-show-stripes',
                        'change-show-outlines',
                        'change-outline-framing',
                    ],
                    template: `
                        <div class="mt-data-table-stub">
                            <slot
                                v-if="dataSource.length === 0 && !isLoading"
                                name="empty-state"
                            />
                            <template v-else>
                                <slot
                                    v-for="column in columns"
                                    :name="'column-' + column.property"
                                    :data="dataSource[0]"
                                    :column-definition="column"
                                />
                            </template>
                        </div>
                    `,
                },
                'sw-modal': {
                    props: [
                        'title',
                        'variant',
                    ],
                    emits: ['modal-close'],
                    template: `
                        <div class="sw-modal-stub" :data-title="title" :data-variant="variant">
                            <slot />
                            <slot name="modal-footer" />
                        </div>
                    `,
                },
                'mt-button': {
                    props: [
                        'size',
                        'variant',
                        'isLoading',
                    ],
                    emits: ['click'],
                    template: `
                        <button
                            class="mt-button-stub"
                            :class="'mt-button-stub--' + variant"
                            :data-size="size"
                            :disabled="isLoading || undefined"
                            type="button"
                            @click="$emit('click')"
                        >
                            <slot />
                        </button>
                    `,
                },
            },
            provide: options.provide,
            config: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment
                globalProperties: (options.globalProperties ?? {}) as any,
            },
            mocks: {
                $t: translate,
                $router: {
                    push: jest.fn(),
                },
                ...options.mocks,
            },
        },
    });

    await flushPromises();

    return wrapper as TestWrapper;
}
