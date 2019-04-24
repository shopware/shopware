import types from 'src/core/service/utils/types.utils';
import Entity from './entity.data';
import EntityCollection from './entity-collection.data';
import SearchResult from './search-result.data';

export default class EntityHydrator {
    constructor(schema) {
        this.schema = schema;
        this.toOne = ['one_to_one', 'many_to_one'];
        this.toMany = ['one_to_many', 'many_to_many'];
    }

    /**
     * Hydrates the repository response to a SearchResult class with all entities and aggregations
     * @param {String} route
     * @param {String} entityName
     * @param {Object} response
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {SearchResult}
     */
    hydrateSearchResult(route, entityName, response, context, criteria) {
        const collection = this.hydrate(route, entityName, response.data, context, criteria);

        return new SearchResult(
            route,
            entityName,
            collection.items,
            response.data.meta.total,
            criteria,
            context,
            response.aggregations
        );
    }

    /**
     * Hydrates a collection of entities. Nested association will be hydrated into collections or entity classes.
     *
     * @param {String} route
     * @param {String} entityName
     * @param {Object} data
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {EntityCollection}
     */
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

    /**
     * @private
     * @param {String} entityName
     * @param {Object} row
     * @param {Object} response
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {*}
     */
    hydrateEntity(entityName, row, response, context, criteria) {
        const id = row.id;
        const cacheKey = `${entityName}-${id}`;

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

        Object.keys(row.relationships).forEach((property) => {
            const value = row.relationships[property];
            const field = schema.properties[property];

            if (!field) {
                return true;
            }

            if (this.toMany.includes(field.relation)) {
                data[property] = this.hydrateToMany(criteria, property, value, field, context, response);

                return true;
            }

            if (this.toOne.includes(field.relation) && types.isObject(value.data)) {
                const nestedEntity = this.hydrateToOne(criteria, property, value, response, context);

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

    /**
     * Hydrates a to one association entity. The entity data is stored in the response included
     *
     * @private
     * @param {Criteria} criteria
     * @param {string} property
     * @param {Object} value
     * @param {Object } response
     * @param {Object} context
     * @returns {*|*}
     */
    hydrateToOne(criteria, property, value, response, context) {
        const associationCriteria = criteria.getAssociation(property);

        const nestedRaw = this.getIncluded(value.data.type, value.data.id, response);

        return this.hydrateEntity(value.data.type, nestedRaw, response, context, associationCriteria);
    }

    /**
     * Hydrates a many association (one to many and many to many) collection and hydrates the related entities
     * @private
     * @param {Criteria} criteria
     * @param {string} property
     * @param {Array|null} value
     * @param {Object} field
     * @param {Object} context
     * @param {Object } response
     * @returns {EntityCollection}
     */
    hydrateToMany(criteria, property, value, field, context, response) {
        const associationCriteria = criteria.getAssociation(property);

        const url = value.links.related.substr(
            value.links.related.indexOf(context.apiResourcePath)
            +
            context.apiResourcePath.length
        );

        const collection = new EntityCollection(url, field.entity, context, associationCriteria);

        if (value.data === null) {
            return collection;
        }

        value.data.forEach((link) => {
            const nestedRaw = this.getIncluded(link.type, link.id, response);
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
        return collection;
    }

    /**
     * Finds an included entity
     * @private
     * @param {Entity} entity
     * @param {string} id
     * @param {Object} response
     * @returns {*}
     */
    getIncluded(entity, id, response) {
        return response.included.find((included) => {
            return (included.id === id && included.type === entity);
        });
    }
}
