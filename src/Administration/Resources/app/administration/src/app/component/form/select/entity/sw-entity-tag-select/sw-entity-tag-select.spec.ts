/**
 * @sw-package framework
 */

import type Criteria from 'src/core/data/criteria.data';
import swEntityTagSelect from './index';

type CheckTagExistsContext = {
    tagExists: boolean;
    context: object;
    repository: { searchIds: jest.Mock };
};

function createContext(ids: string[]): CheckTagExistsContext {
    return {
        tagExists: true,
        context: {},
        repository: {
            searchIds: jest.fn(() => Promise.resolve({ total: 0, data: ids })),
        },
    };
}

describe('src/app/component/form/select/entity/sw-entity-tag-select', () => {
    const { checkTagExists } = swEntityTagSelect.methods as {
        checkTagExists: (this: CheckTagExistsContext, term: string) => Promise<void>;
    };

    it('should mark the tag as existing when an id is found', async () => {
        const context = createContext(['tag-id']);

        await checkTagExists.call(context, 'summer');

        const [criteria] = context.repository.searchIds.mock.calls[0] as [Criteria];
        expect(criteria.getLimit()).toBe(1);
        expect(criteria.totalCountMode).toBe(0);
        expect(context.tagExists).toBe(true);
    });

    it('should mark the tag as missing when no id is found', async () => {
        const context = createContext([]);

        await checkTagExists.call(context, 'summer');

        expect(context.tagExists).toBe(false);
    });

    it('should not search for an empty term', async () => {
        const context = createContext([]);

        await checkTagExists.call(context, '   ');

        expect(context.repository.searchIds).not.toHaveBeenCalled();
        expect(context.tagExists).toBe(true);
    });

    it('should not fail to filter while a search is still loading', () => {
        const { filterSearchGeneratedTags } = swEntityTagSelect.methods as {
            filterSearchGeneratedTags: (this: { resultCollection: null }) => void;
        };
        const context = { resultCollection: null };

        expect(() => filterSearchGeneratedTags.call(context)).not.toThrow();
        expect(context.resultCollection).toBeNull();
    });

    it('should not add a tag for a term that changed during the search', async () => {
        const { search } = swEntityTagSelect.methods as {
            search: (this: Record<string, unknown>) => void;
        };
        const repository = { create: jest.fn() };
        const context: Record<string, unknown> = {
            searchTerm: 'Sum',
            tagExists: false,
            resultCollection: [],
            filterSearchGeneratedTags: jest.fn(),
            checkTagExists: jest.fn(() => Promise.resolve()),
            $super: jest.fn(() => {
                context.searchTerm = 'Sal';
                return Promise.resolve();
            }),
            repository,
        };

        search.call(context);
        await flushPromises();

        expect(repository.create).not.toHaveBeenCalled();
        expect(context.resultCollection).toEqual([]);
    });
});
