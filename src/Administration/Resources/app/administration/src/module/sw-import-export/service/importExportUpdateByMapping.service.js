/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ImportExportUpdateByMappingService {
    constructor(EntityDefinition) {
        this.EntityDefinition = EntityDefinition;
    }

    getEntity(sourceEntity, path) {
        const pathParts = path.split('.');
        const pathToEntity = [];

        let actualDefinition = this.EntityDefinition.get(sourceEntity);
        let entityFound = false;
        let relation = null;
        let name = actualDefinition.entity;

        pathParts.forEach((propertyName) => {
            const property = actualDefinition.properties[propertyName];

            if (!property || propertyName === 'translations') {
                return;
            }

            if (property.flags.runtime === true) {
                actualDefinition = { entity: null };
                return;
            }

            const entity = property.entity;
            entityFound = this.EntityDefinition.has(entity);

            if (entityFound) {
                actualDefinition = this.EntityDefinition.get(entity);
                pathToEntity.push(propertyName);
                relation = property.relation;
                name = propertyName;
            }
        });

        return {
            entity: actualDefinition.entity,
            path: pathToEntity.join('.'),
            relation,
            name,
        };
    }

    getSelected(entity, updateBy) {
        const entityDefinition = this.EntityDefinition.get(entity);
        const primaryKeyField = Object.keys(entityDefinition.getPrimaryKeyFields())[0];

        if (!Array.isArray(updateBy)) {
            return primaryKeyField;
        }

        const updateByMapping = this.getUpdateByMappingByEntity(entity, updateBy);

        if (!updateByMapping) {
            return primaryKeyField;
        }

        return updateByMapping.mappedKey;
    }

    updateMapping(profile, mappedKey, entityName) {
        if (!mappedKey) {
            this.removeUpdateByMappingByEntity(entityName, profile.updateBy);

            return;
        }

        if (!Array.isArray(profile.updateBy)) {
            profile.updateBy = [{ entityName, mappedKey }];

            return;
        }

        const updateByMapping = this.getUpdateByMappingByEntity(entityName, profile.updateBy);

        if (!updateByMapping) {
            profile.updateBy.push({ entityName, mappedKey });

            return;
        }

        updateByMapping.mappedKey = mappedKey;
    }

    getUpdateByMappingByEntity(entity, updateBy) {
        const updateByMappings = updateBy.filter((identifier) => {
            return identifier.entityName === entity;
        });

        if (!updateByMappings.length) {
            return null;
        }

        return updateByMappings[0];
    }

    removeUpdateByMappingByEntity(entity, updateBy) {
        if (!Array.isArray(updateBy)) {
            return;
        }

        const updateByMapping = this.getUpdateByMappingByEntity(entity, updateBy);

        if (!updateByMapping) {
            return;
        }

        const index = updateBy.indexOf(updateByMapping);

        if (index > -1) {
            updateBy.splice(index, 1);
        }
    }

    removeUnusedMappings(profile) {
        if (!profile || !Array.isArray(profile.updateBy)) {
            return;
        }

        if (!Array.isArray(profile.mapping) || !profile.mapping.length) {
            profile.updateBy = [];

            return;
        }

        const usedEntities = {};

        profile.mapping.forEach((mapping) => {
            const { entity, path, relation } = this.getEntity(
                profile.sourceEntity,
                mapping.key,
            );

            if (relation === 'many_to_many') {
                usedEntities[entity] = true;

                return;
            }

            if (!usedEntities.hasOwnProperty(entity)) {
                usedEntities[entity] = [];
            }

            const value = path !== '' ? mapping.key.replace(new RegExp(`^(${path}\.)`), '') : mapping.key;

            usedEntities[entity].push(value);
        });

        const unusedMappings = profile.updateBy.filter((updateBy) => {
            return !usedEntities.hasOwnProperty(updateBy.entityName) || (
                Array.isArray(usedEntities[updateBy.entityName])
                && !usedEntities[updateBy.entityName].includes(updateBy.mappedKey)
            );
        });

        unusedMappings.forEach((unusedMapping) => {
            this.removeUpdateByMappingByEntity(unusedMapping.entityName, profile.updateBy);
        });
    }
}
