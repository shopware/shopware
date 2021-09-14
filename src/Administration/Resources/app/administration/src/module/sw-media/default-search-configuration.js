import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    _searchable: false,
    fileName: {
        _searchable: false,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    alt: {
        _searchable: false,
        _score: searchRankingPoint.MIDDLE_SEARCH_RANKING,
    },
    title: {
        _searchable: false,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    tags: {
        name: {
            _searchable: false,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
    },
};

export default defaultSearchConfiguration;
