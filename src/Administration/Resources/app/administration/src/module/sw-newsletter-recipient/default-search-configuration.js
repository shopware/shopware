import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    _searchable: false,
    email: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
};

export default defaultSearchConfiguration;
