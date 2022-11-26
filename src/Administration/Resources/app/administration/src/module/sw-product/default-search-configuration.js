/*
 * @package inventory
 */

import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    _searchable: true,
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    productNumber: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    ean: {
        _searchable: true,
        _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
    },
    manufacturerNumber: {
        _searchable: true,
        _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
    },
    customSearchKeywords: {
        _searchable: false,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    tags: {
        name: {
            _searchable: true,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;
