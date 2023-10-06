/**
 * @package admin
 */

import utils from 'src/core/service/util.service';
import { warn } from 'src/core/service/utils/debug.utils';
import Entity from './entity.data';
import EntityCollection from './entity-collection.data';
import Criteria from './criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class EntityFactory {
    /**
     * Creates a new entity for the provided entity name.
     * Returns null for unknown entities.
     *
     * @param {String} entityName
     * @param {String|null} id
     * @param {Object} context
     * @returns {Entity|null}
     */
    create(entityName, id, context) {
        id = id || utils.createId();

        const definition = Shopware.EntityDefinition.get(entityName);

        if (!definition) {
            warn('Entity factory', `No schema found for entity ${entityName}`);
            return null;
        }

        const data = {
            extensions: {},
        };

        const toManyAssociations = definition.getToManyAssociations();
        Object.keys(toManyAssociations).forEach((property) => {
            const associatedProperty = toManyAssociations[property].entity;

            if (toManyAssociations[property].flags.extension) {
                data.extensions[property] = this.createCollection(
                    entityName,
                    `${id}/extensions`,
                    property,
                    associatedProperty,
                    context,
                );
            } else {
                data[property] = this.createCollection(entityName, id, property, associatedProperty, context);
            }
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

        return new EntityCollection(source, related, context, new Criteria(1, 10));
    }
}
