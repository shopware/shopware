import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    _searchable: true,
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    description: {
        _searchable: true,
        _score: searchRankingPoint.LOW_SEARCH_RANKING,
    },
};

/*
 * @sw-package fundamentals@after-sales
 * @private
 */
export default defaultSearchConfiguration;
