import types from 'src/core/service/utils/types.utils';
import Entity from './entity.data';
import EntityCollection from './entity-collection.data';

export default class EntityHydrator {
    constructor(schema) {
        this.schema = schema;
    }

    hydrate(route, entityName, data, context, criteria) {
        this.cache = {};

        const collection = new EntityCollection(route, entityName, context, criteria);

        data.data.forEach((row) => {
            collection.add(
                this.hydrateEntity(entityName, row, data, context, criteria)
            );
        });

        return collection;
    }

    hydrateEntity(entityName, row, response, context, criteria) {
        const id = row.id;
        const cacheKey = `${entityName}-${id}`;
        const toOne = ['one_to_one', 'many_to_one'];
        const toMany = ['one_to_many', 'many_to_many'];

        if (this.cache[cacheKey]) {
            return this.cache[cacheKey];
        }

        const schema = this.schema[entityName];
        // currently translation can not be hydrated
        if (!schema) {
            return null;
        }

        const data = row.attributes;
        data.id = id;

        Object.entries(row.relationships).forEach(([property, value]) => {
            const field = schema.properties[property];
            if (!field) {
                return true;
            }

            if (toMany.includes(field.relation)) {
                const associationCriteria = criteria.getAssociation(property);

                const collection = new EntityCollection(
                    value.links.related,
                    field.entity,
                    context,
                    associationCriteria
                );

                if (value.data !== null) {
                    value.data.forEach((link) => {
                        const nestedRaw = this._getIncluded(link.type, link.id, response);
                        const nestedEntity = this.hydrateEntity(
                            link.type,
                            nestedRaw,
                            response,
                            context,
                            associationCriteria
                        );

                        if (nestedEntity) {
                            collection.add(nestedEntity);
                        }
                    });
                }

                data[property] = collection;

                return true;
            }

            if (toOne.includes(field.relation) && types.isObject(value.data)) {
                const associationCriteria = criteria.getAssociation(property);

                const nestedRaw = this._getIncluded(value.data.type, value.data.id, response);

                const nestedEntity = this.hydrateEntity(value.data.type, nestedRaw, response, context, associationCriteria);

                // currently translation can not be hydrated
                if (nestedEntity) {
                    data[property] = nestedEntity;
                }
            }

            return true;
        });

        const entity = new Entity(id, entityName, data);

        this.cache[cacheKey] = entity;

        return entity;
    }

    _getIncluded(entity, id, response) {
        return response.included.find((included) => {
            return (included.id === id && included.type === entity);
        });
    }
}
