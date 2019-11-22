class StoreLoader {
    /**
     * Allows to load all records of the provided store. The records are loaded inside a queue to prevent server overloads
     *
     * @param {EntityStore} entityStore
     * @param {Object} params
     * @param {Boolean} keepAssociations
     * @param languageId
     * @return {Promise}
     */
    loadAll(entityStore, params, keepAssociations = false, languageId = '') {
        params = params || {};

        entityStore.store = {};

        if (!params.limit) {
            params.limit = 25;
        }

        return new Promise((resolve) => {
            this.loadQueue(entityStore, params, 1, keepAssociations, languageId, resolve);
        });
    }

    /**
     * @param {EntityStore} entityStore
     * @param {Object} params
     * @param {Integer} page
     * @param {Boolean} keepAssociations
     * @param {String} languageId
     * @param {function} promise
     * @return {Promise}
     */
    loadQueue(entityStore, params, page, keepAssociations = false, languageId = '', promise) {
        params.page = page;

        entityStore.getList(params, keepAssociations, languageId).then((response) => {
            const length = Object.keys(entityStore.store).length;

            if (length < response.total || response.items.length > 0) {
                this.loadQueue(entityStore, params, page + 1, keepAssociations, languageId, promise);
            } else {
                // resolve promise, all data loaded
                promise(Object.values(entityStore.store));
            }
        });
    }
}

export default StoreLoader;
