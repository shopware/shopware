import string from 'src/core/service/utils/string.utils';

const ApiService = Shopware.Classes.ApiService;

const { Criteria } = Shopware.Data;
const bulkSyncTypes = Object.freeze({
    OVERWRITE: 'overwrite',
    CLEAR: 'clear',
    ADD: 'add',
    REMOVE: 'remove'
});

/**
 * @class
 * @extends ApiService
 */
class BulkEditApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService);
        this.syncService = Shopware.Service('syncService');
        this.repositoryFactory = Shopware.Service('repositoryFactory');
        this.name = 'bulkEditService';

        this.entityName = null;
        this.entityIds = [];

        this.handlers = {
            product: this._bulkEditProductHandler
            // TODO: add handlers for order, customer
        };
    }

    async bulkEdit(entityName, entityIds, changes) {
        const handler = this._findBulkEditHandler(entityName).bind(this);

        this.entityName = entityName;
        this.entityIds = entityIds;

        return handler(changes);
    }

    /**
     * @param changes = []
     */
    async buildBulkSyncPayload(changes) {
        const definition = Shopware.EntityDefinition.get(this.entityName);

        if (!definition) {
            throw Error(`No schema found for entity ${this.entityName}`);
        }

        // Grouped sync payload by operator and entities
        const groupedPayload = {
            upsert: {
                [this.entityName]: {}
            },
            delete: {}
        };

        await Promise.all(changes.map(async (change) => {
            if (Object.values(bulkSyncTypes).indexOf(change.type) === -1) {
                return;
            }

            const mappingEntity = change.mappingEntity;

            if (mappingEntity) {
                let associationChanges = null;

                try {
                    const refDefinition = Shopware.EntityDefinition.get(mappingEntity);

                    associationChanges = await this._handleAssociationChange(refDefinition, change);
                } catch (e) {
                    console.warn(e.message);

                    // Ignore the failed change
                    return;
                }

                groupedPayload.delete[mappingEntity] = {
                    ...groupedPayload.delete[mappingEntity],
                    ...associationChanges.delete
                };
                groupedPayload.upsert[mappingEntity] = {
                    ...groupedPayload.upsert[mappingEntity],
                    ...associationChanges.upsert
                };

                return;
            }

            const field = definition.getField(change.field);

            if (!field) {
                console.warn('Entity factory', `Property ${this.entityName}.${change.field} not found`);

                return;
            }

            change.value = this._castDefaultValueIfNecessary(change.value);

            if (change.type === bulkSyncTypes.CLEAR && ['int', 'float'].includes(field.type)) {
                change.value = 0;
            }

            this.entityIds.forEach(id => {
                groupedPayload.upsert[this.entityName][id] = groupedPayload.upsert[this.entityName][id] || { id };
                groupedPayload.upsert[this.entityName][id][change.field] = change.value;
            });
        }));

        const syncPayload = {};

        Object.keys(groupedPayload).forEach(operator => {
            const operatorPayload = groupedPayload[operator];

            if (Object.keys(operatorPayload).length === 0) {
                return;
            }

            Object.keys(operatorPayload).forEach(payloadEntity => {
                const items = Object.values(operatorPayload[payloadEntity]);
                if (items.length === 0) {
                    return;
                }

                const payloadKey = `${operator}-${payloadEntity}`;
                syncPayload[payloadKey] = syncPayload[payloadKey] || {
                    action: operator,
                    entity: payloadEntity,
                    payload: []
                };
                syncPayload[payloadKey].payload.push(...items);
            });
        });

        return syncPayload;
    }

    async _handleAssociationChange(refDefinition, change) {
        const localMappingKey = `${this.entityName}Id`;

        const selectedFieldValues = Array.isArray(change.value) ? change.value : [change.value];

        const existAssociations = await this._fetchAssociated(refDefinition, change.field, change.type === bulkSyncTypes.REMOVE ? selectedFieldValues : null);

        if ([bulkSyncTypes.CLEAR, bulkSyncTypes.REMOVE].includes(change.type)) {
            return {
                upsert: {},
                delete: existAssociations
            };
        }

        const upsertPayload = {};
        selectedFieldValues.forEach(fieldValue => {
            this.entityIds.forEach(id => {
                if (existAssociations[`${fieldValue}.${id}`]) {
                    delete existAssociations[`${fieldValue}.${id}`];

                    return;
                }

                upsertPayload[`${fieldValue}.${id}`] = {
                    [change.field]: fieldValue,
                    [localMappingKey]: id
                };
            });
        });

        if (change.type === bulkSyncTypes.ADD) {
            return {
                upsert: upsertPayload,
                delete: {}
            };
        }

        return {
            upsert: upsertPayload,
            delete: existAssociations
        };
    }

    _castDefaultValueIfNecessary(value) {
        if (value === '' || typeof value === 'undefined') {
            return null;
        }

        return value;
    }

    _findBulkEditHandler(module) {
        if (!this.handlers[module]) {
            throw Error(`Bulk Edit Handler not found for ${module} module`);
        }

        return this.handlers[module];
    }

    async _bulkEditProductHandler(changes) {
        const payload = await this.buildBulkSyncPayload(changes);

        return this.syncService.sync(payload);
    }

    async _fetchAssociated(refDefinition, referenceKey, referenceIds = null) {
        const localMappingKey = `${this.entityName}Id`;

        const refField = refDefinition.getField(referenceKey);

        if (!refField) {
            throw Error(`Property ${refDefinition.entity}.${referenceKey} not found`);
        }

        const localField = refDefinition.getField(localMappingKey);

        if (!localField) {
            throw Error(`Property ${refDefinition.entity}.${localMappingKey} not found`);
        }

        const isMappingDefinition = !refDefinition.getField('id');

        const criteria = new Criteria(1, 500);
        criteria.addFilter(Criteria.equalsAny(localMappingKey, this.entityIds));

        if (referenceIds) {
            criteria.addFilter(Criteria.equalsAny(referenceKey, referenceIds));
        }

        const mappingRepository = this.repositoryFactory.create(refDefinition.entity);

        let existAssociations;

        if (isMappingDefinition) {
            const mappingIds = await mappingRepository.searchIds(criteria);
            existAssociations = mappingIds.data;
        } else {
            existAssociations = await mappingRepository.search(criteria);
        }

        const mappedExistAssociations = {};

        existAssociations.forEach(association => {
            const localKey = isMappingDefinition ? string.snakeCase(localMappingKey) : localMappingKey;
            const foreignKey = isMappingDefinition ? string.snakeCase(referenceKey) : referenceKey;

            const { id, [localKey]: localId, [foreignKey]: foreignId } = association;
            const key = `${foreignId}.${localId}`;

            mappedExistAssociations[key] = isMappingDefinition ? {
                [localMappingKey]: localId,
                [referenceKey]: foreignId
            } : { id };
        });

        return mappedExistAssociations;
    }
}

export default BulkEditApiService;
