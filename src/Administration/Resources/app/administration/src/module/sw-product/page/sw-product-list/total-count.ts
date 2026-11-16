import type CriteriaType from 'src/core/data/criteria.data';

/**
 * @sw-package inventory
 */

const PAGING_PARAMETERS = [
    'page',
    'limit',
    'sort',
    'total-count-mode',
];

type TotalCountHost = {
    term: string | null;
    page: number;
    limit: number;
    total: number;
    totalCountKey: string | null;
    getList: () => unknown;
};

type TotalCountState = {
    key: string | null;
    reuse: boolean;
};

type CountedResult = {
    length: number;
    total: number | null;
    criteria: CriteriaType;
};

/**
 * @private
 */
export function startTotalCount(host: TotalCountHost, criteria: CriteriaType): TotalCountState {
    if (host.term) {
        return { key: null, reuse: false };
    }

    const countRelevant = Object.entries(criteria.parse()).filter(([name]) => !PAGING_PARAMETERS.includes(name));
    const key = JSON.stringify([Shopware.Context.api.languageId, countRelevant]);
    const reuse = key === host.totalCountKey;

    if (reuse) {
        criteria.setTotalCountMode(0);
    }

    return { key, reuse };
}

/**
 * @private
 */
export function finishTotalCount(host: TotalCountHost, state: TotalCountState, result: CountedResult): boolean {
    if (state.reuse && result.length === 0 && host.page > 1) {
        host.totalCountKey = null;
        void host.getList();

        return false;
    }

    if (!state.reuse) {
        host.total = result.total ?? 0;
    } else {
        result.criteria.setTotalCountMode(1);

        if (result.length < host.limit) {
            host.total = (host.page - 1) * host.limit + result.length;
        }

        result.total = host.total;
    }

    host.totalCountKey = state.key;

    return true;
}
