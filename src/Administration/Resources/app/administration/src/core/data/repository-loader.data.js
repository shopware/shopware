export default class RepositoryLoader {

    constructor() {
        this.factory = Shopware.Service('repositoryFactory');
    }

    load(criteria, entity) {
        return new Promise((resolve) => {
            criteria.limit = 200;
            criteria.page = 1;
            criteria.totalCountMode = 0;
            this._iterate(criteria, entity, [], resolve);
        });
    }

    _iterate(criteria, entity, entities, resolve) {
        this.factory.create(entity).search(criteria, Shopware.Context.api).then((result) => {
            result.forEach((item) => {
                entities.push(item);
            });

            if (result.length >= 50) {
                criteria.page += 1;
                this._iterate(criteria, entity, entities, resolve);
                return;
            }

            resolve(entities);
        });
    }
}
