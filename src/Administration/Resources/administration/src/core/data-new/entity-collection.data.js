import BaseCollection from './base-collection.data';

export default class EntityCollection extends BaseCollection {
    constructor(source, entity, context, criteria) {
        super(source, entity, context, criteria);

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
