const { string } = Shopware.Utils;
const { Criteria } = Shopware.Data;
const bulkSyncTypes = Object.freeze({
    OVERWRITE: 'overwrite',
    CLEAR: 'clear',
    ADD: 'add',
    REMOVE: 'remove',
});

/**
 * @class
 */
class BulkEditBaseHandler {
    constructor() {
        this.syncService = Shopware.Service('syncService');
        this.repositoryFactory = Shopware.Service('repositoryFactory');
        this.entityName = null;
        this.entityIds = [];
    }

    /**
     * normalize the grid-template-rows/columns values
     * @param  {Array.<Object>} changes
     * @return {Object} syncPayload
     * @example
     * const changes = [
        { type: 'overwrite', field: 'description', value: 'test' },
        { type: 'clear', field: 'stock' },
        { type: 'overwrite', mappingEntity: 'product_category', field: 'categoryId', value: ['category_1', 'category_2']}
     ];
     * const syncPayload = buildBulkSyncPayload(changes);
     * syncPayload // <= {
        'upsert-product': {
            action: 'upsert',
            entity: 'product',
            payload: [
                {
                    id: 'product_1',
                    description: 'test',
                    stock: 0
                },
                {
                    id: 'product_2',
                    description: 'test',
                    stock: 0
                }
            ]
        },...
    }
     */
    async buildBulkSyncPayload(changes) {
        const definition = Shopware.EntityDefinition.get(this.entityName);

        if (!definition) {
            throw Error(`No schema found for entity ${this.entityName}`);
        }

        // Grouped sync payload by operator and entities
        const groupedPayload = {
            upsert: {
                [this.entityName]: {},
            },
            delete: {},
        };

        await Promise.all(changes.map(async (change) => {
            if (!Object.values(bulkSyncTypes).includes(change.type)) {
                return;
            }

            // mappingEntity indicates the change is a toMany association change, the value of it is the mapping relation, e.g product_category
            const mappingEntity = change.mappingEntity;

            if (mappingEntity) {
                let associationChanges = null;

                try {
                    const refDefinition = Shopware.EntityDefinition.get(mappingEntity);

                    associationChanges = await this._handleAssociationChange(refDefinition, change);

                    // push the association change's payload into existing grouped change payload
                    groupedPayload.delete[mappingEntity] = {
                        ...groupedPayload.delete[mappingEntity],
                        ...associationChanges.delete,
                    };
                    groupedPayload.upsert[mappingEntity] = {
                        ...groupedPayload.upsert[mappingEntity],
                        ...associationChanges.upsert,
                    };

                    return;
                } catch (e) {
                    console.warn(e.message);

                    // Ignore the failed change
                    return;
                }
            }

            // If the change type is not a toMany association change, grouped the change by entity's id so each entity can have a same sync payload
            const field = definition.getField(change.field);

            if (!field) {
                console.warn('Entity factory', `Property ${this.entityName}.${change.field} not found`);

                return;
            }

            change.value = this._castDefaultValueIfNecessary(change.value);

            // Cast the value to 0 if the we 'CLEAR' an int or float field
            if (change.type === bulkSyncTypes.CLEAR && ['int', 'float'].includes(field.type)) {
                change.value = 0;
            }

            this.entityIds.forEach(id => {
                groupedPayload.upsert[this.entityName][id] = groupedPayload.upsert[this.entityName][id] || { id };
                groupedPayload.upsert[this.entityName][id][change.field] = change.value;
            });
        }));

        return this._transformSyncPayload(groupedPayload);
    }

    /**
     * @private
     *
     * Transform grouped bulk edit payload to sync payload
     *
     * @param {Object} groupedPayload
     * @return {Object} syncPayload
     */
    _transformSyncPayload(groupedPayload) {
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
                    payload: [],
                };
                syncPayload[payloadKey].payload.push(...items);
            });
        });

        return syncPayload;
    }

    /**
     * @private
     *
     * A handler to build upsert or delete payload of an association change depending on change's type and existing associations
     *
     * @param {Object} refDefinition
     * @param {Object} change
     * @example
     * change =[{ type: 'overwrite', mappingEntity: 'product_category', field: 'categoryId', value: ['category_1', 'category_2']];
     */
    async _handleAssociationChange(refDefinition, change) {
        const localMappingKey = `${this.entityName}Id`;

        // Selected association ids, eg: ['category_id_1', 'category_id_2',...]
        const selectedFieldValues = Array.isArray(change.value) ? change.value : [change.value];

        const existAssociations = await this._fetchAssociated(
            refDefinition,
            change.field,
            change.type === bulkSyncTypes.REMOVE ? selectedFieldValues : null,
        );

        // Delete existing associations if change type is CLEAR or REMOVE
        if ([bulkSyncTypes.CLEAR, bulkSyncTypes.REMOVE].includes(change.type)) {
            return {
                upsert: {},
                delete: existAssociations,
            };
        }

        const upsertPayload = {};
        selectedFieldValues.forEach(fieldValue => {
            this.entityIds.forEach(localId => {
                if (existAssociations[`${fieldValue}.${localId}`]) {
                    delete existAssociations[`${fieldValue}.${localId}`];

                    return;
                }

                upsertPayload[`${fieldValue}.${localId}`] = {
                    [change.field]: fieldValue,
                    [localMappingKey]: localId,
                };
            });
        });

        // Upsert associations if change type is ADD
        if (change.type === bulkSyncTypes.ADD) {
            return {
                upsert: upsertPayload,
                delete: {},
            };
        }

        // Associations can be upsert or delete if change type is OVERWRITE
        return {
            upsert: upsertPayload,
            delete: existAssociations,
        };
    }

    /**
     * @private
     */
    _castDefaultValueIfNecessary(value) {
        if (value === '' || typeof value === 'undefined') {
            return null;
        }

        return value;
    }

    /**
     * @private
     *
     * Find the module's bulk edit handler
     */
    _findBulkEditHandler(module) {
        if (!this.handlers[module]) {
            throw Error(`Bulk Edit Handler not found for ${module} module`);
        }

        return this.handlers[module];
    }

    /**
     * @private
     */
    async _bulkEditProductHandler(changes) {
        const payload = await this.buildBulkSyncPayload(changes);

        return this.syncService.sync(payload, {}, { 'single-operation': 1 });
    }

    /**
     * @private
     *
     * Fetch toMany association ids and mapped each id using `${foreignId}.${localId}` as a key
     */
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
            // Normalize keys to snakeCase because repository.searchIds return keys in snakeCase format
            const localKey = isMappingDefinition ? string.snakeCase(localMappingKey) : localMappingKey;
            const foreignKey = isMappingDefinition ? string.snakeCase(referenceKey) : referenceKey;

            const { id, [localKey]: localId, [foreignKey]: foreignId } = association;
            const key = `${foreignId}.${localId}`;

            // ManyToMany have 2 primary keys, e.g product_category. Meanwhile OneToMany have one id as primary key, e.g product_media
            mappedExistAssociations[key] = isMappingDefinition ? {
                [localMappingKey]: localId,
                [referenceKey]: foreignId,
            } : { id };
        });

        return mappedExistAssociations;
    }
}

export default BulkEditBaseHandler;
