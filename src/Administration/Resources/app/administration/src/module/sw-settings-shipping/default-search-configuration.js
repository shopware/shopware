import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    name: {
        _searchable: false,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
};

export default defaultSearchConfiguration;
