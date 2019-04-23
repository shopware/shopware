import utils from 'src/core/service/util.service';
import { warn } from 'src/core/service/utils/debug.utils';
import Entity from './entity.data';
import EntityCollection from './entity-collection.data';
import Criteria from './criteria.data';

export default class EntityFactory {
    constructor(schema) {
        this.schema = schema;
    }

    /**
     * Creates a new entity for the provided entity name.
     * Returns null for unknown entities.
     *
     * @param {String} entityName
     * @param {String} id
     * @param {Object} context
     * @returns {Entity|null}
     */
    create(entityName, id, context) {
        id = id || utils.createId();

        const definition = this.schema[entityName];

        if (!definition) {
            warn('Entity factory', `No schema found for entity ${entityName}`);
            return null;
        }
        const toMany = ['one_to_many', 'many_to_many'];

        const data = {};

        Object.keys(definition.properties).forEach((property) => {
            const type = definition.properties[property];
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

    /**
     * @private
     * @param {String} entity
     * @param {String} id
     * @param {String} property
     * @param {String} related
     * @param {Object} context
     * @returns {EntityCollection}
     */
    createCollection(entity, id, property, related, context) {
        const subRoute = property.replace(/_/g, '-');
        const route = entity.replace(/_/g, '-');
        const source = `/${route}/${id}/${subRoute}`;

        const criteria = new Criteria();
        criteria.setLimit(10);
        criteria.setPage(1);

        return new EntityCollection(source, related, context, criteria);
    }
}
