import { deepCopyObject, getAssociatedDeletions, getObjectChangeSet } from 'src/core/service/utils/object.utils';
import { Entity, Application } from 'src/core/shopware';
import utils, { types } from '../../core/service/util.service';

class EntityStateModule {
    constructor(stateName = null, apiService = null, state = { original: {}, draft: {} }, namespaced = true) {
        if (stateName === null) {
            return;
        }

        this._stateName = stateName;
        this._stateObject = state;
        this._namespaced = namespaced;
        this._apiService = apiService;
    }

    getVueX() {
        return {
            state: () => this.stateObject,
            actions: this.actions,
            mutations: EntityStateModule.mutations,
            namespaced: this.namespaced
        };
    }

    getListAction({ commit, state }, { limit, offset, sortBy, sortDirection, term, criteria }) {
        const additionalParams = {};

        if (sortBy && sortBy.length) {
            additionalParams.sort = (sortDirection.toLowerCase() === 'asc' ? '' : '-') + sortBy;
        }

        if (term) {
            additionalParams.term = term;
        }

        if (criteria) {
            additionalParams.filter = criteria;
        }

        return this.apiService.getList(offset, limit, additionalParams).then((response) => {
            const newItems = response.data;
            const total = response.meta.total;
            const items = [];

            newItems.forEach((item) => {
                commit('initItem', item);
                items.push(state.draft[item.id]);
            });

            return { items, total };
        });
    }

    getByIdAction({ commit, state }, id, localCopy = false) {
        const item = state.draft[id];

        if (typeof item !== 'undefined' && item.isDetail) {
            return (localCopy === true) ? deepCopyObject(item) : item;
        }

        return this.apiService.getById(id).then((response) => {
            const loadedItem = response.data;
            loadedItem.isDetail = true;

            commit('initItem', loadedItem);

            return (localCopy === true) ? deepCopyObject(state.draft[id]) : state.draft[id];
        });
    }

    createEmptyItemAction({ commit, state }, id = null) {
        if (id === null) {
            id = utils.createId();
        }

        if (typeof state.draft[id] !== 'undefined') {
            return state.draft[id];
        }

        const item = Entity.getRawEntityObject(this.stateName, true);

        item.id = id;
        item.isDetail = true;
        item.isNew = true;

        commit('initItem', item);

        return state.draft[item.id];
    }

    saveItemAction({ commit, state }, item) {
        if (!item.id) {
            return Promise.reject();
        }

        const changeset = getObjectChangeSet(state.original[item.id], item, this.stateName);
        const deletions = getAssociatedDeletions(state.original[item.id], item, this.stateName);

        const deletionCue = this.createDeletionPromiseCue(deletions, item);

        return Promise.all(deletionCue).then((deleteResponse) => {
            if (types.isEmpty(changeset)) {
                return deleteResponse;
            }

            if (item.isNew) {
                return this.apiService.create(changeset)
                    .then((response) => {
                        const newItem = response.data;

                        commit('initItem', newItem);
                        return newItem;
                    })
                    .catch((exception) => {
                        EntityStateModule.handleException(exception, commit);
                        return Promise.reject(exception);
                    });
            }

            return this.apiService.updateById(item.id, changeset)
                .then((response) => {
                    commit('initItem', response.data);
                    return response.data;
                })
                .catch((exception) => {
                    EntityStateModule.handleException(exception, commit);
                    return Promise.reject(exception);
                });
        }).catch((deleteException) => {
            EntityStateModule.handleException(deleteException, commit);
            return Promise.reject(deleteException);
        });
    }

    static initItemMutation(state, item) {
        if (!item.id) {
            return;
        }

        item.isLoaded = true;

        const originalItem = deepCopyObject(item);
        const draftItem = deepCopyObject(item);

        state.original[item.id] = Object.assign(state.original[item.id] || {}, originalItem);
        state.draft[item.id] = Object.assign(state.draft[item.id] || {}, draftItem);
    }

    static setItemMutation(state, item) {
        if (!item.id) {
            return;
        }

        Object.assign(state.draft[item.id], item);
    }

    createDeletionPromiseCue(deletions, item) {
        const deletionCue = [];

        Object.keys(deletions).forEach((property) => {
            if (types.isArray(deletions[property])) {
                deletions[property].forEach((association) => {
                    deletionCue.push(new Promise((resolve, reject) => {
                        this.apiService.deleteAssociation(item.id, property, association.id)
                            .then((response) => {
                                resolve(response);
                            })
                            .catch((response) => {
                                reject(response);
                            });
                    }));
                });
            }
        });

        return deletionCue;
    }

    static handleException(exception, commit) {
        if (exception.response.data && exception.response.data.errors) {
            exception.response.data.errors.forEach((error) => {
                commit('error/addError', {
                    module: this.stateName,
                    error
                });
            });
        }

        return exception;
    }

    get namespaced() {
        return this._namespaced;
    }

    get actions() {
        return {
            getList: this.getListAction.bind(this),
            getById: this.getByIdAction.bind(this),
            createEmpty: this.createEmptyItemAction.bind(this),
            saveItem: this.saveItemAction.bind(this)
        };
    }

    static get mutations() {
        return {
            initItem: EntityStateModule.initItemMutation,
            setItem: EntityStateModule.setItemMutation
        };
    }

    get stateName() {
        return this._stateName;
    }

    get stateObject() {
        return this._stateObject;
    }

    get apiService() {
        const serviceContainer = Application.getContainer('service');
        return serviceContainer[this._apiService];
    }
}

export default EntityStateModule;
