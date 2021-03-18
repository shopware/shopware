const { Criteria } = Shopware.Data;

/**
* @module app/filter-service
*/

export default class FilterService {
    _userConfigRepository;

    _storedFilters = {};

    _filterEntity = null;

    constructor({ userConfigRepository }) {
        this._userConfigRepository = userConfigRepository;
    }

    getStoredFilters(storeKey) {
        if (this._filterEntity && !this._filterEntity._isNew && this._filterEntity.key === storeKey) {
            return Promise.resolve(this._filterEntity.value);
        }

        const criteria = this._getUserConfigCriteria(storeKey);

        return this._userConfigRepository.search(criteria, Shopware.Context.api).then(response => {
            if (response.length) {
                this._filterEntity = response.first();
            } else {
                const currentUser = Shopware.State.get('session').currentUser;

                this._filterEntity = this._userConfigRepository.create(Shopware.Context.api);
                this._filterEntity.key = storeKey;
                this._filterEntity.userId = currentUser && currentUser.id;
                this._filterEntity.value = {};
            }

            return Promise.resolve(this._filterEntity.value);
        });
    }

    getStoredCriteria(storeKey) {
        return this.getStoredFilters(storeKey).then(response => {
            const data = [];
            Object.values(response).forEach(filter => {
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

        Object.keys(filters).forEach(name => {
            filterValues[name] = { ...filters[name] };
            if (filterValues[name].criteria) {
                savedCriteria.push(...filterValues[name].criteria);
            }
        });

        this._filterEntity.value = filterValues;
        this._storedFilters[storeKey] = savedCriteria;

        return this._userConfigRepository.save(this._filterEntity, Shopware.Context.api).then(() => {
            return this.getStoredFilters(storeKey).then(() => {
                return Promise.resolve(this._filterEntity.value);
            });
        });
    }

    async mergeWithStoredFilters(storeKey, listCriteria) {
        if (!this._storedFilters[storeKey]) {
            this._storedFilters[storeKey] = await this.getStoredCriteria(storeKey);
        }

        this._storedFilters[storeKey].forEach(el1 => {
            const match = listCriteria.filters.find(el2 => {
                if (el1.type !== 'not') {
                    return el1.field === el2.field;
                }

                return (el2.type !== 'not')
                    ? el1.queries[0].field === el2.field
                    : el1.queries[0].field === el2.queries[0].field;
            });

            if (!match) {
                listCriteria.addFilter(el1);
            }
        });

        return listCriteria;
    }

    _getUserConfigCriteria(storeKey) {
        const currentUser = Shopware.State.get('session').currentUser;
        const criteria = new Criteria();

        criteria.addFilter(Criteria.equals('key', storeKey));
        criteria.addFilter(Criteria.equals('userId', currentUser && currentUser.id));

        return criteria;
    }
}
