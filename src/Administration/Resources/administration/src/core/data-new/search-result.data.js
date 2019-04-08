import BaseCollection from './base-collection.data';

export default class SearchResult extends BaseCollection {
    constructor(source, entity, entities, total, criteria, context, aggregations) {
        super(source, entity, context, criteria);

        this.total = total;
        this.aggregations = aggregations;
        this.elements = entities;
    }
}
