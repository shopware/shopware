// @deprecated tag:v6.4.0.0
export default class InfiniteScrollingHelper {
    constructor(entityName, limit) {
        this.store = Shopware.StateDeprecated.getStore(entityName);
        this.page = 1;
        this.limit = limit;
        this.done = false;
    }

    next(params, keepAssociation = false) {
        const searchOptions = Object.assign({}, params, { page: this.page, limit: this.limit });
        this.page += 1;

        return this.store.getList(searchOptions, keepAssociation).then(({ items }) => {
            if (items.length < this.limit) {
                this.done = true;
            }

            return items;
        });
    }

    reset() {
        this.page = 1;
        this.done = false;
    }
}
