import EntityCollection from './entity-collection.data';

export default class SearchResult extends EntityCollection {
    constructor(source, entity, entities, total, criteria, context, aggregations) {
        super(source, entity, context, criteria);

        this.total = total;
        this.aggregations = aggregations;
        this.items = entities;
    }
}
