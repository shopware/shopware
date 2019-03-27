import BaseCollection from './base-collection.data';

export default class SearchResult extends BaseCollection {
    constructor(source, entity, entities, total, criteria, context, aggregations, view) {
        super(source, entity, context, criteria, view);

        this.total = total;
        this.aggregations = aggregations;
        this.elements = entities;

        const that = this;

        // allows to iterate the elements of the collection via v-for
        return new Proxy(this.elements, {
            get(target, property) {
                if (that.elements[property]) {
                    return that.elements[property];
                }
                if (property === 'length') {
                    return Object.keys(that.elements).length;
                }

                return that[property];
            }
        });
    }
}
