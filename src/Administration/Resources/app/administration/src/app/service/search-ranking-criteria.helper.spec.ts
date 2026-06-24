/**
 * @sw-package framework
 */

import Criteria from 'src/core/data/criteria.data';
import { applySearchRankingCriteria } from './search-ranking-criteria.helper';

type SearchRankingService = Parameters<typeof applySearchRankingCriteria>[0]['searchRankingService'];

function createSearchRankingService(overrides: Partial<SearchRankingService> = {}): SearchRankingService {
    return {
        isValidTerm: jest.fn(() => true),
        getSearchFieldsByEntity: jest.fn(() => ({
            name: 500,
        })),
        buildSearchQueriesForEntity: jest.fn((_, __, criteria: Criteria) => criteria),
        ...overrides,
    };
}

describe('src/app/service/search-ranking-criteria.helper.ts', () => {
    it('returns the original criteria when no search config entity is provided', async () => {
        const criteria = new Criteria();
        const searchRankingService = createSearchRankingService();

        const result = await applySearchRankingCriteria({
            criteria,
            term: 'meteor',
            searchRankingService,
        });

        expect(result).toEqual({
            criteria,
            searchable: true,
        });
        expect(searchRankingService.isValidTerm).not.toHaveBeenCalled();
        expect(searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();
        expect(searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('returns the original criteria and skips field lookup when the term is not valid', async () => {
        const criteria = new Criteria();
        const searchRankingService = createSearchRankingService({
            isValidTerm: jest.fn(() => false),
        });

        const result = await applySearchRankingCriteria({
            criteria,
            term: 'm',
            searchConfigEntity: 'product_manufacturer',
            searchRankingService,
        });

        expect(result).toEqual({
            criteria,
            searchable: true,
        });
        expect(searchRankingService.isValidTerm).toHaveBeenCalledWith('m');
        expect(searchRankingService.getSearchFieldsByEntity).not.toHaveBeenCalled();
        expect(searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('marks the entity as not searchable when search ranking fields are empty', async () => {
        const criteria = new Criteria();
        const searchRankingService = createSearchRankingService({
            getSearchFieldsByEntity: jest.fn(() => ({})),
        });

        const result = await applySearchRankingCriteria({
            criteria,
            term: 'meteor',
            searchConfigEntity: 'product_manufacturer',
            searchRankingService,
        });

        expect(result).toEqual({
            criteria,
            searchable: false,
        });
        expect(searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledWith('product_manufacturer');
        expect(searchRankingService.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('builds search queries with resolved search ranking fields', async () => {
        const criteria = new Criteria();
        const transformedCriteria = new Criteria(1, 25);
        const searchRankingFields = {
            name: 500,
        };
        const searchRankingService = createSearchRankingService({
            getSearchFieldsByEntity: jest.fn(() => searchRankingFields),
            buildSearchQueriesForEntity: jest.fn(() => transformedCriteria),
        });

        const result = await applySearchRankingCriteria({
            criteria,
            term: 'meteor',
            searchConfigEntity: 'product_manufacturer',
            searchRankingService,
        });

        expect(result).toEqual({
            criteria: transformedCriteria,
            searchable: true,
        });
        expect(searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledWith(
            searchRankingFields,
            'meteor',
            criteria,
        );
    });

    it('supports asynchronously loaded search ranking fields', async () => {
        const criteria = new Criteria();
        const searchRankingFields = {
            name: 500,
        };
        const searchRankingService = createSearchRankingService({
            getSearchFieldsByEntity: jest.fn(() => Promise.resolve(searchRankingFields)),
        });

        const result = await applySearchRankingCriteria({
            criteria,
            term: 'meteor',
            searchConfigEntity: 'product_manufacturer',
            searchRankingService,
        });

        expect(result).toEqual({
            criteria,
            searchable: true,
        });
        expect(searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledWith('product_manufacturer');
        expect(searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledWith(
            searchRankingFields,
            'meteor',
            criteria,
        );
    });
});
