/**
 * @package admin
 */

import types from 'src/core/service/utils/types.utils';
import type { AxiosResponse } from 'axios';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import EntityClass from './entity.data';
import Criteria from './criteria.data';
import EntityCollection from './entity-collection.data';
import type { Property } from './entity-definition.data';

type meta = {
    totalCountMode: number,
    total: number,
}

type links = {
    [relation: string]: string,
}

type row = {
    id: string,
    type: string,
    attributes: {
        [key: string|symbol]: unknown
    },
    links: links,
    relationships: {
        [key: string|symbol]: unknown
    },
    meta?: meta,
}

type data = {
    data: [row],
    included: [row],
    links: {
        [relation: string]: string,
    },
    meta?: meta,
    aggregations: [],
}

type field = {
    entity: keyof EntitySchema.Entities,
}

type schema = {
    properties: {
        [key: string|symbol]: field,
    },
    isToManyAssociation: (field: field) => boolean,
    isToOneAssociation: (field: field) => boolean,
}

type toOneData = {
    data: {
        type: string,
        id: string
    }
}

type entityNames = keyof EntitySchema.Entities;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class EntityHydrator {
    cache: { [key: string]: Entity<entityNames> } = {};

    /**
     * Hydrates the repository response to a SearchResult class with all entities and aggregations
     */
    hydrateSearchResult<EntityName extends keyof EntitySchema.Entities>(
        route: string,
        entityName: EntityName,
        response: AxiosResponse<data>,
        context: apiContext,
        criteria: Criteria,
    ): EntityCollection<EntityName> {
        this.cache = {};
        const entities = [] as Entity<EntityName>[];

        response.data.data.forEach((item) => {
            const entity = this.hydrateEntity(entityName, item, response.data, context, criteria);

            if (entity !== null) {
                entities.push(entity);
            }
        });

        return new EntityCollection<EntityName>(
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
     */
    hydrate(
        route: string,
        entityName: entityNames,
        data: data,
        context: apiContext,
        criteria: Criteria,
    ): EntityCollection<entityNames> {
        this.cache = {};

        const collection = new EntityCollection<entityNames>(route, entityName, context, criteria);

        data.data.forEach((row) => {
            const entity = this.hydrateEntity(entityName, row, data, context, criteria);

            if (entity !== null) {
                collection.add(entity);
            }
        });

        return collection;
    }

    /**
     * @private
     */
    hydrateEntity<EntityName extends keyof EntitySchema.Entities>(
        entityName: EntityName,
        row: row,
        response: data,
        context: apiContext,
        criteria: Criteria,
    ): Entity<EntityName>|null {
        if (!row) {
            return null;
        }

        const id = row.id;
        const cacheKey = `${entityName}-${id}`;

        if (this.cache[cacheKey]) {
            return this.cache[cacheKey] as unknown as Entity<EntityName>;
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
                    && Object.keys(attributeValue).length <= 0;

            if (schema.isJsonListField(field) && (isEmptyObject || attributeValue === null)) {
                data[attributeKey] = [];
            }
        });

        Object.keys(row.relationships).forEach((property) => {
            const value = row.relationships[property] as data;

            if (property === 'extensions') {
                // @ts-expect-error - schema can be any
                data[property] = this.hydrateExtensions(id, value, schema, response, context, criteria);
            }

            const field: Property = (schema.properties as {[key: string]: unknown})[property] as Property;

            if (!field) {
                return true;
            }

            if (schema.isToManyAssociation(field)) {
                data[property] = this.hydrateToMany(
                    criteria,
                    property,
                    value,
                    (field.entity as keyof EntitySchema.Entities),
                    context,
                    response,
                );

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

        const e = new EntityClass<EntityName>(id, entityName, data as unknown as EntitySchema.Entities[EntityName]);

        this.cache[cacheKey] = e as unknown as Entity<entityNames>;

        return e;
    }

    /**
     * Hydrates a to one association entity. The entity data is stored in the response included
     */
    hydrateToOne(
        criteria: Criteria,
        property: string,
        value: unknown,
        response: data,
        context: apiContext,
    ): Entity<entityNames>|null {
        const associationCriteria = this.getAssociationCriteria(criteria, property);

        const nestedRaw = this.getIncluded((value as toOneData).data.type, (value as toOneData).data.id, response);

        return this.hydrateEntity(
            (value as toOneData).data.type as entityNames,
            nestedRaw,
            response,
            context,
            associationCriteria,
        );
    }

    getAssociationCriteria(criteria: Criteria, property: string): Criteria {
        if (criteria.hasAssociation(property)) {
            return criteria.getAssociation(property);
        }

        return new Criteria(1, 25);
    }

    /**
     * Hydrates a many association (one to many and many to many) collection and hydrates the related entities
     * @private
     */
    hydrateToMany(
        criteria: Criteria,
        property: string,
        value: data,
        entityName: keyof EntitySchema.Entities,
        context: apiContext,
        response: data,
    ): EntityCollection<entityNames> {
        const associationCriteria = this.getAssociationCriteria(criteria, property);
        const apiResourcePath = context?.apiResourcePath as string ?? '';

        const url = value.links.related.substr(
            value.links.related.indexOf(apiResourcePath)
            +
            apiResourcePath.length,
        );

        const collection = new EntityCollection<entityNames>(url, entityName, context, associationCriteria);

        if (value.data === null) {
            return collection;
        }

        value.data.forEach((link) => {
            const nestedRaw = this.getIncluded(link.type, link.id, response);
            const nestedEntity = this.hydrateEntity(
                link.type as entityNames,
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
     */
    getIncluded(entityName: string, id: string, response: data): row {
        // @ts-expect-error
        return response.included.find((included) => {
            return (included.id === id && included.type === entityName);
        });
    }

    /**
     * @private
     */
    hydrateExtensions(
        id: string,
        relationship: data,
        schema: schema,
        response: data,
        context: apiContext,
        criteria: Criteria,
    ): {[key: string]: unknown} {
        const extension = this.getIncluded('extension', id, response);

        const data = { ...extension.attributes };

        Object.keys(extension.relationships).forEach((property) => {
            const value = extension.relationships[property] as data;

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
