/**
 * @sw-package framework
 */

const { types } = Shopware.Utils;
const { cloneDeep } = Shopware.Utils.object;

/**
 * @module app/filter-service
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class FilterService {
    _storedFilters = {};

    _filterEntity = null;

    async getStoredFilters(storeKey) {
        const queryFilterValue = this._getQueryFilterValue(storeKey);

        this._filterEntity = {
            key: storeKey,
            value: queryFilterValue
                ? JSON.parse(decodeURIComponent(queryFilterValue)) || {}
                : (await Shopware.Service('userConfigService').search([storeKey]))?.data?.[storeKey] || {},
        };

        if (!queryFilterValue) {
            await this._pushFiltersToUrl(true);
        }

        return this._filterEntity.value;
    }

    getStoredCriteria(storeKey) {
        return this.getStoredFilters(storeKey).then((response) => {
            const data = [];
            Object.values(response).forEach((filter) => {
                if (filter.criteria) {
                    data.push(...filter.criteria);
                }
            });

            return Promise.resolve(data);
        });
    }

    saveFilters(storeKey, filters) {
        const filterValues = {};
        const savedCriteria = [];

        Object.keys(filters).forEach((name) => {
            if (filters[name].criteria) {
                filterValues[name] = { ...filters[name] };
                savedCriteria.push(...filterValues[name].criteria);
            }
        });

        const filterEntity = this._filterEntity;

        filterEntity.value = filterValues;
        this._storedFilters[storeKey] = savedCriteria;

        this._pushFiltersToUrl();

        return Shopware.Service('userConfigService')
            .upsert({
                [storeKey]: filterValues,
            })
            .then(() => {
                return filterValues;
            })
            .catch(() => {
                return filterEntity.value;
            });
    }

    async mergeWithStoredFilters(storeKey, listCriteria) {
        this._storedFilters[storeKey] = await this.getStoredCriteria(storeKey);

        const mergedCriteria = cloneDeep(listCriteria);

        this._storedFilters[storeKey].forEach((el1) => {
            const match = listCriteria.filters.find((el2) => {
                if (el1.type !== 'not') {
                    return el1.field === el2.field;
                }

                return el2.type !== 'not'
                    ? el1.queries[0].field === el2.field
                    : el1.queries[0].field === el2.queries[0].field;
            });

            if (!match) {
                mergedCriteria.addFilter(el1);
            }
        });

        return mergedCriteria;
    }

    async _pushFiltersToUrl(replaceRoute = false) {
        const urlFilterValue = types.isEmpty(this._filterEntity.value) ? null : this._filterEntity.value;
        const urlEncodedValue = encodeURIComponent(JSON.stringify(urlFilterValue));

        const router = Shopware.Application.view.router;
        const route = router?.currentRoute?.value;

        const query = { ...route.query };
        const routeParams = { ...route.params };
        delete query[this._filterEntity.key];

        const newRoute = {
            name: route.name,
            query: {
                ...query,
                [this._filterEntity.key]: urlEncodedValue,
            },
        };

        if (!Shopware.Utils.types.isEmpty(routeParams)) {
            newRoute.params = routeParams;
        }

        try {
            if (replaceRoute) {
                return await router.replace(newRoute);
            }

            return await router.push(newRoute);
        } catch (error) {
            if (error?.name === 'NavigationDuplicated') {
                return error;
            }

            return Promise.reject(error);
        }
    }

    _getQueryFilterValue(storeKey) {
        const router = Shopware.Application.view.router;
        const route = router?.currentRoute;

        return route?.value?.query[storeKey];
    }
}
