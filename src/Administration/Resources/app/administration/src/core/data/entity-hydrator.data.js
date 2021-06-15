import types from 'src/core/service/utils/types.utils';
import Entity from './entity.data';
import Criteria from './criteria.data';
import EntityCollection from './entity-collection.data';

export default class EntityHydrator {
    /**
     * Hydrates the repository response to a SearchResult class with all entities and aggregations
     * @param {String} route
     * @param {String} entityName
     * @param {Object} response
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {EntityCollection}
     */
    hydrateSearchResult(route, entityName, response, context, criteria) {
        this.cache = {};
        const entities = [];

        response.data.data.forEach((item) => {
            entities.push(this.hydrateEntity(entityName, item, response.data, context, criteria));
        });

        return new EntityCollection(
            route,
            entityName,
            context,
            criteria,
            entities,
            response.data.meta?.total,
            response.data.aggregations,
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

        const collection = new Shopware.EntityCollection(route, entityName, context, criteria);

        data.data.forEach((row) => {
            collection.add(
                this.hydrateEntity(entityName, row, data, context, criteria),
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
        if (!row) {
            return null;
        }

        const id = row.id;
        const cacheKey = `${entityName}-${id}`;

        if (this.cache[cacheKey]) {
            return this.cache[cacheKey];
        }

        const schema = Shopware.EntityDefinition.get(entityName);
        // currently translation can not be hydrated
        if (!schema) {
            return null;
        }

        const data = row.attributes;
        data.id = id;

        // hydrate empty json fields
        Object.entries(data).forEach(([attributeKey, attributeValue]) => {
            const field = schema.getField(attributeKey);

            if (!field) {
                return;
            }

            if (!schema.isJsonField(field)) {
                return;
            }

            if (Array.isArray(attributeValue) && attributeValue.length <= 0 && schema.isJsonObjectField(field)) {
                data[attributeKey] = {};
                return;
            }

            const isEmptyObject = !Array.isArray(attributeValue)
                && typeof attributeValue === 'object'
                && attributeValue !== null
                && Object.keys(attributeValue).length <= 0
                && schema.isJsonListField(field);

            if (isEmptyObject) {
                data[attributeKey] = [];
            }
        });

        Object.keys(row.relationships).forEach((property) => {
            const value = row.relationships[property];

            if (property === 'extensions') {
                data[property] = this.hydrateExtensions(id, value, schema, response, context, criteria);
            }

            const field = schema.properties[property];

            if (!field) {
                return true;
            }

            if (schema.isToManyAssociation(field)) {
                data[property] = this.hydrateToMany(criteria, property, value, field.entity, context, response);

                return true;
            }

            if (schema.isToOneAssociation(field) && types.isObject(value.data)) {
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
        const associationCriteria = this.getAssociationCriteria(criteria, property);

        const nestedRaw = this.getIncluded(value.data.type, value.data.id, response);

        return this.hydrateEntity(value.data.type, nestedRaw, response, context, associationCriteria);
    }

    /**
     * @param {Criteria} criteria
     * @param {string} property
     * @returns {Criteria}
     */
    getAssociationCriteria(criteria, property) {
        if (criteria.hasAssociation(property)) {
            return criteria.getAssociation(property);
        }
        return new Criteria();
    }

    /**
     * Hydrates a many association (one to many and many to many) collection and hydrates the related entities
     * @private
     * @param {Criteria} criteria
     * @param {string} property
     * @param {Array|null} value
     * @param {string} entity
     * @param {Object} context
     * @param {Object } response
     * @returns {EntityCollection}
     */
    hydrateToMany(criteria, property, value, entity, context, response) {
        const associationCriteria = this.getAssociationCriteria(criteria, property);

        const url = value.links.related.substr(
            value.links.related.indexOf(context.apiResourcePath)
            +
            context.apiResourcePath.length,
        );

        const collection = new EntityCollection(url, entity, context, associationCriteria);

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
                associationCriteria,
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

    /**
     * @private
     * @param {string} id
     * @param {Object} relationship
     * @param {Object} schema
     * @param {Object} response
     * @param {Object} context
     * @param {Criteria} criteria
     * @returns {*}
     */
    hydrateExtensions(id, relationship, schema, response, context, criteria) {
        const extension = this.getIncluded('extension', id, response);

        const data = Object.assign({}, extension.attributes);

        Object.keys(extension.relationships).forEach((property) => {
            const value = extension.relationships[property];

            const field = schema.properties[property];

            if (!field) {
                return true;
            }

            if (schema.isToManyAssociation(field)) {
                data[property] = this.hydrateToMany(criteria, property, value, field.entity, context, response);

                return true;
            }

            if (schema.isToOneAssociation(field) && types.isObject(value.data)) {
                const nestedEntity = this.hydrateToOne(criteria, property, value, response, context);

                if (nestedEntity) {
                    data[property] = nestedEntity;
                }
            }

            return true;
        });

        return data;
    }
}
