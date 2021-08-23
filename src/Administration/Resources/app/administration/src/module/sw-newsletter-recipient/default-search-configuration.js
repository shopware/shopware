import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    email: {
        _searchable: false,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
};

export default defaultSearchConfiguration;
