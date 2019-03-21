import utils from 'src/core/service/util.service';
import Entity from './entity.data';
import EntityCollection from './entity-collection.data';
import Criteria from './criteria.data';

export default class EntityFactory {
    constructor(schema) {
        this.schema = schema;
    }

    create(entityName, id, context) {
        id = id || utils.createId();

        const definition = this.schema[entityName];
        const toMany = ['one_to_many', 'many_to_many'];

        const data = {};

        Object.entries(definition.properties).forEach(([property, type]) => {
            if (type.type !== 'association') {
                return true;
            }

            if (toMany.includes(type.relation)) {
                data[property] = this.createCollection(entityName, id, property, type.entity, context);
            }
            return true;
        });

        const entity = new Entity(id, entityName, data);
        entity.markAsNew();

        return entity;
    }

    createCollection(entity, id, property, related, context) {
        const subRoute = property.replace(/_/g, '-');
        const route = entity.replace(/_/g, '-');
        const source = `${route}/${id}/${subRoute}`;

        const criteria = new Criteria();
        criteria.setLimit(10);
        criteria.setPage(1);

        return new EntityCollection(source, related, context, criteria);
    }
}
