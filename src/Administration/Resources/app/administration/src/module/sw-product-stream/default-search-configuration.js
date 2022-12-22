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
};

/**
 * @private
 * @package business-ops
 */
export default defaultSearchConfiguration;
