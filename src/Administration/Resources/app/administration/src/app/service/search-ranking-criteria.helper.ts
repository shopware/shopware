/**
 * @sw-package framework
 */

import type Criteria from 'src/core/data/criteria.data';

type SearchRankingFields = Record<string, unknown>;

type SearchRankingService = {
    isValidTerm: (term?: string | null) => boolean;
    getSearchFieldsByEntity: (entityName: string) => Promise<SearchRankingFields> | SearchRankingFields;
    buildSearchQueriesForEntity: (
        searchRankingFields: SearchRankingFields,
        searchTerm: string,
        criteria: Criteria,
    ) => Criteria;
};

type ApplySearchRankingCriteriaOptions = {
    criteria: Criteria;
    term?: string | null;
    searchConfigEntity?: string | null;
    searchRankingService: SearchRankingService;
};

type ApplySearchRankingCriteriaResult = {
    criteria: Criteria;
    searchable: boolean;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export async function applySearchRankingCriteria({
    criteria,
    term,
    searchConfigEntity,
    searchRankingService,
}: ApplySearchRankingCriteriaOptions): Promise<ApplySearchRankingCriteriaResult> {
    if (!searchConfigEntity || !searchRankingService.isValidTerm(term)) {
        return {
            criteria,
            searchable: true,
        };
    }

    const searchRankingFields = await searchRankingService.getSearchFieldsByEntity(searchConfigEntity);

    if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
        return {
            criteria,
            searchable: false,
        };
    }

    return {
        criteria: searchRankingService.buildSearchQueriesForEntity(searchRankingFields, term ?? '', criteria),
        searchable: true,
    };
}
