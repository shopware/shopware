import { hasOwnProperty, deepCopyObject } from 'src/core/service/utils/object.utils';
import types from 'src/core/service/utils/types.utils';
import utils from 'src/core/service/util.service';

/**
 * @module core/data/LocalStore
 */

/**
 * @class
 * @memberOf module:core/data/LocalStore
 */
export default class LocalStore {
    /**
     * @constructor
     * @memberOf module:core/data/LocalStore
     * @param {array} values
     * @param {string} propertyName
     * @param {string} searchProperty
     */
    constructor(values, propertyName = 'id', searchProperty = 'id') {
        this.store = {};
        this.propertyName = propertyName;
        this.searchProperty = searchProperty;

        this.isLoading = false;

        if (!values || values.length < 1) {
            return;
        }

        values.forEach(value => {
            this.store[value[propertyName]] = value;
            if (this.store[value[propertyName]].meta) {
                return;
            }

            this.store[value[propertyName]].meta = { viewData: deepCopyObject(value) };
        });
    }

    /**
     * @param {String} id
     * @returns {*}
     */
    getById(id) {
        if (!this.hasId(id)) {
            return this.create(id);
        }

        return this.store[id];
    }

    /**
     * @param {String} id
     * @returns {*}
     */
    getByIdAsync(id) {
        return new Promise(resolve => { resolve(this.getById(id)); });
    }

    /**
     * @param {Object} params
     * @returns {Promise<any>}
     */
    getList(params) {
        return new Promise((resolve) => {
            let store = Object.values(this.store);
            if (params.term) {
                const searchTerm = params.term.toLowerCase();
                store = store.filter((value) => {
                    // For inline snippets - example: value[searchProperty] = { 'de_DE': 'Größe', 'en_GB': 'Size' }
                    if (types.isObject(value[this.searchProperty])) {
                        let found = false;
                        Object.keys(value[this.searchProperty]).forEach((key) => {
                            if (value[this.searchProperty][key].toLowerCase().includes(searchTerm)) {
                                found = true;
                            }
                        });

                        return found;
                    }

                    return this.objectPropertiesContains(value, searchTerm)
                        || this.objectPropertiesContains(value.meta.viewData, searchTerm);
                });
            }

            if (params.criteria) {
                const query = params.criteria.getQuery();
                store = this.filterResults(store, query);
            }

            if (params.sortBy) {
                const sortDirection = params.sortDirection === 'ASC' ? 1 : -1;

                store = store.sort((valueA, valueB) => {
                    return valueA[this.propertyName].localeCompare(valueB[this.propertyName]) * sortDirection;
                });
            }

            if (params.limit && params.limit < Object.keys(this.store).length) {
                const page = params.page !== undefined ? params.page - 1 : 0;

                store = store.slice(params.limit * page, params.limit);
            }
            resolve({ items: store, total: Object.keys(this.store).length, aggregations: [] });
        });
    }

    objectPropertiesContains(object, searchTerm) {
        return Object.keys(object).filter(key => {
            return typeof object[key] !== 'object'
                && String(object[key]).toLowerCase().includes(searchTerm);
        }).length;
    }

    filterResults(store, query) {
        if (query.type === 'contains') {
            store = store.filter(value => value[query.field].includes(query.value));
        } else if (query.type === 'equals') {
            store = store.filter(value => value[query.field] === query.value);
        } else if (query.type === 'multi') {
            if (query.operator === 'AND') {
                query.queries.forEach(subQuery => {
                    store = this.filterResults(store, subQuery);
                });
            } else {
                let result = [];
                query.queries.forEach(subQuery => {
                    result = [...new Set([...result, ...this.filterResults(store, subQuery)])];
                });
                store = result;
            }
        }

        return store;
    }

    /**
     * @param {String} id
     * @returns {boolean}
     */
    hasId(id) {
        return this.store[id] !== undefined;
    }

    create(id = utils.createId()) {
        return {
            [this.propertyName]: id,
            meta: {
                viewData: {
                    [this.propertyName]: id
                }
            }
        };
    }

    static duplicate() {
        return {};
    }

    /**
     * @param {Object} entity
     * @returns {boolean}
     */
    add(entity) {
        if (!hasOwnProperty(entity, this.propertyName)) {
            return false;
        }

        this.store[entity[this.propertyName]] = entity;
        this.store[entity[this.propertyName]].meta = { viewData: entity };
        return true;
    }

    /**
     * @param {Object} entity
     * @returns {boolean}
     */
    remove(entity) {
        if (!hasOwnProperty(entity, this.propertyName) || this.hasId(entity[this.propertyName])) {
            return false;
        }

        delete this.store[entity[this.propertyName]];
        return true;
    }
}
