/**
 * @sw-package framework
 */

import Criteria from 'src/core/data/criteria.data';
import type {
    MeteorEntityTableCriteriaTransformContext,
    MeteorEntityTableState,
} from '../sw-meteor-entity-data-table.types';
import {
    createSearchResult,
    createWrapper,
    firstSearchCriteria,
    lastSearchCriteria,
    mountedTable,
} from './sw-meteor-entity-data-table.test-utils';

describe('src/app/component/entity/sw-meteor-entity-data-table PR 1 behavior', () => {
    it('creates a repository from the entity prop when no repository override is provided', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([{ id: 'manufacturer-1' }], 1))),
        };
        const repositoryFactory = {
            create: jest.fn(() => repository),
        };

        await createWrapper(
            {
                entity: 'product_manufacturer',
            },
            {
                provide: {
                    repositoryFactory,
                },
            },
        );

        expect(repositoryFactory.create).toHaveBeenCalledWith('product_manufacturer');
        expect(repository.search).toHaveBeenCalledTimes(1);
    });

    it('uses the explicit repository override before creating a repository from entity', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([{ id: 'manufacturer-1' }], 1))),
        };
        const repositoryFactory = {
            create: jest.fn(),
        };

        await createWrapper(
            {
                entity: 'product_manufacturer',
                repository,
            },
            {
                provide: {
                    repositoryFactory,
                },
            },
        );

        expect(repositoryFactory.create).not.toHaveBeenCalled();
        expect(repository.search).toHaveBeenCalledTimes(1);
    });

    it('reloads from changed criteria props and resets to page 1 by default', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            criteria: new Criteria(),
            initialPage: 3,
        });
        const nextCriteria = new Criteria();
        nextCriteria.setTerm('external search');

        await wrapper.setProps({
            criteria: nextCriteria,
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.page).toBe(1);
        expect(lastSearchCriteria(repository).page).toBe(1);
        expect(lastSearchCriteria(repository).term).toBe('external search');
    });

    it('keeps the current page on criteria prop changes when page reset is disabled', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            criteria: new Criteria(),
            initialPage: 3,
            resetPageOnCriteriaChange: false,
        });

        await wrapper.setProps({
            criteria: new Criteria(),
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.page).toBe(3);
        expect(lastSearchCriteria(repository).page).toBe(3);
    });

    it('calls criteriaTransform with the built criteria, state, and context before repository search', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const criteriaTransform = jest.fn((criteria: Criteria) => {
            criteria.setLimit(5);

            return criteria;
        });

        await createWrapper({
            repository,
            criteriaTransform,
            initialPage: 2,
            initialSearchTerm: 'shop',
        });

        expect(criteriaTransform).toHaveBeenCalledTimes(1);
        const transformCall = criteriaTransform.mock.calls[0] as unknown as
            | [Criteria, MeteorEntityTableState, MeteorEntityTableCriteriaTransformContext]
            | undefined;

        expect(transformCall?.[1]).toEqual({
            page: 2,
            limit: 25,
            searchTerm: 'shop',
            sortBy: '',
            sortDirection: 'ASC',
            naturalSorting: false,
        });
        expect(transformCall?.[2].searchTerm).toBe('shop');
        expect(Array.isArray(transformCall?.[2].columns)).toBe(true);
        expect(firstSearchCriteria(repository).limit).toBe(5);
    });

    it('stops loading and returns an empty result when criteriaTransform returns null', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([{ id: 'manufacturer-1' }]))),
        };

        const wrapper = await createWrapper({
            repository,
            criteriaTransform: () => null,
        });

        expect(repository.search).not.toHaveBeenCalled();
        expect(wrapper.vm.records).toEqual([]);
        expect(wrapper.vm.total).toBe(0);
        expect(wrapper.vm.loading).toBe(false);
    });

    it('uses searchTerm as a controlled page-level search bridge', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };

        const wrapper = await createWrapper({
            repository,
            initialSearchTerm: 'initial search',
            searchTerm: 'page search',
        });

        expect(firstSearchCriteria(repository).term).toBe('page search');
        expect(mountedTable(wrapper).props('searchValue')).toBe('page search');
    });

    it('reloads and resets to page 1 when the controlled searchTerm changes', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            initialPage: 3,
            searchTerm: 'old search',
        });

        await wrapper.setProps({
            searchTerm: 'new search',
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.page).toBe(1);
        expect(lastSearchCriteria(repository).term).toBe('new search');
    });

    it('clears the table-owned search state when the controlled searchTerm is cleared', async () => {
        const repository = {
            search: jest.fn(() => Promise.resolve(createSearchResult([]))),
        };
        const wrapper = await createWrapper({
            repository,
            searchTerm: 'old search',
        });

        await wrapper.setProps({
            searchTerm: null,
        });
        await flushPromises();

        expect(repository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.state.searchTerm).toBe('');
        expect(lastSearchCriteria(repository).term).toBeNull();
    });

    it('forwards empty-state slot context for search-aware empty states', async () => {
        const wrapper = await createWrapper(
            {
                criteriaTransform: () => null,
                initialPage: 2,
                searchTerm: 'missing',
            },
            {
                slots: {
                    'empty-state': `
                        <template #default="{ records, total, loading, state, searchTerm }">
                            <div class="custom-empty-state">
                                {{ searchTerm }}|{{ total }}|{{ records.length }}|{{ loading }}|{{ state.page }}
                            </div>
                        </template>
                    `,
                },
            },
        );

        expect(wrapper.get('.custom-empty-state').text()).toBe('missing|0|0|false|2');
    });
});
