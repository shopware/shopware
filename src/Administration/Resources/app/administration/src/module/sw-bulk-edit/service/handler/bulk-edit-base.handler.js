const { object } = Shopware.Utils;
const { Criteria } = Shopware.Data;
const bulkSyncTypes = Object.freeze({
    OVERWRITE: 'overwrite',
    CLEAR: 'clear',
    ADD: 'add',
    REMOVE: 'remove',
});
const { types } = Shopware.Utils;
const { getObjectDiff } = Shopware.Utils.object;

/**
 * @class
 *
 * @package system-settings
 */
class BulkEditBaseHandler {
    constructor() {
        this.syncService = Shopware.Service('syncService');
        this.repositoryFactory = Shopware.Service('repositoryFactory');
        this.entityName = null;
        this.entityIds = [];

        // Grouped sync payload by operator and entities
        this.groupedPayload = {
            upsert: {},
            delete: {},
        };
    }

    /**
     * @param  {Array.<Object>} changes
     * @return {Object} syncPayload
     * @example
     * const changes = [
        { type: 'overwrite', field: 'description', value: 'test' },
        { type: 'clear', field: 'stock' },
        {
            type: 'overwrite',
            field: 'visibilities',
            mappingReferenceField: 'salesChannelId',
            value: ProductVisibilitiesCollection
        },
        { type: 'overwrite', field: 'categories', value: [{id: 'category_1'}, {id: 'category_2'}]}
     ];
     * const syncPayload = buildBulkSyncPayload(changes);
     * syncPayload // {
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

        // Initialize the grouped payload of referenceEntity
        this.groupedPayload.delete[this.entityName] = {};
        this.groupedPayload.upsert[this.entityName] = {};

        await Promise.all(changes.map(async change => {
            if (!Object.values(bulkSyncTypes).includes(change.type)) {
                return;
            }

            // If the change type is not a toMany association change,
            // grouped the change by entity's id so each entity can have a same sync payload
            const field = definition.getField(change.field);

            if (!field) {
                Shopware.Utils.debug.warn(
                    'Entity factory',
                    `Property ${this.entityName}.${change.field} not found`,
                );

                return;
            }

            if (definition.isToManyAssociation(field)) {
                try {
                    await this._handleAssociationChange(field, change);

                    return;
                } catch (e) {
                    Shopware.Utils.debug.warn(e);

                    // Ignore the failed change
                    return;
                }
            }

            const value = this._castDefaultValueIfNecessary(change, field.type);

            this.entityIds.forEach(id => {
                this.groupedPayload.upsert[this.entityName][id] ??= { id };
                this.groupedPayload.upsert[this.entityName][id][change.field] = value;
            });
        }));

        return this._transformSyncPayload(this.groupedPayload);
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
                syncPayload[payloadKey] ??= {
                    action: operator,
                    entity: payloadEntity,
                    payload: [],
                };

                syncPayload[payloadKey].payload.push(...items.flat());
            });
        });

        return syncPayload;
    }

    /**
     * @private
     *
     * Build upsert or delete payload of an association change depending on change's type and existing associations
     *
     * @param {Object} fieldDefinition
     * @param {Object} change
     * @example
     * change =[{ type: 'overwrite', field: 'categories', value: [{id: 'category_1'}, {id: 'category_2'}]];
     */
    async _handleAssociationChange(fieldDefinition, change) {
        const {
            mapping,
            entity,
            local,
            reference,
            localField,
            referenceField,
        } = fieldDefinition;

        const isMappingField = !!mapping;
        let existAssociations;

        change.referenceEntity = mapping ?? entity;

        // Initialize the grouped payload of referenceEntity
        this.groupedPayload.delete[change.referenceEntity] = {};
        this.groupedPayload.upsert[change.referenceEntity] = {};

        // normalize selected association entities, eg: [{id: 'category_id_1'}, {id: 'category_id_2'},...]
        const changeValue = Array.isArray(change.value) ? change.value : [change.value];
        change.value = changeValue.filter(Boolean);

        if (isMappingField) {
            change.localKey = local;
            change.referenceKey = reference;
            existAssociations = await this._fetchManyToManyAssociated(fieldDefinition, change);
        } else {
            change.localKey = localField;
            change.referenceKey = referenceField;

            existAssociations = await this._fetchOneToManyAssociated(fieldDefinition, change);
        }

        const { referenceEntity, localKey, referenceKey, type } = change;

        // if change type is CLEAR or REMOVE Delete existing associations
        if ([bulkSyncTypes.CLEAR, bulkSyncTypes.REMOVE].includes(type)) {
            this.groupedPayload.delete[referenceEntity] = {
                ...this._transformDeletePayload(existAssociations, localKey, referenceKey),
            };

            return;
        }

        // if change type is OVERWRITE, all existing associations should be removed by default
        // then we can filter the ones we want to keep by remove it from delete payload
        if (type === bulkSyncTypes.OVERWRITE) {
            this.groupedPayload.delete[referenceEntity] = {
                ...this._transformDeletePayload(existAssociations, localKey, referenceKey),
            };
        }

        if (isMappingField) {
            this._detectManyToManyChange(change, existAssociations);
        } else {
            this._detectOneToManyChange(change, existAssociations);
        }
    }

    /**
     * Handler for bulk edit a OneToMany association
     * @param change
     * @param existAssociations
     * @private
     */
    _detectOneToManyChange(change, existAssociations) {
        const {
            referenceEntity,
            referenceKey,
            localKey,
            mappingReferenceField,
            value: changeItems,
            type,
        } = change;
        const editableProperties = this._getEditableProperties(referenceEntity);

        if (mappingReferenceField) {
            editableProperties.push(mappingReferenceField);
        }

        changeItems.forEach(changeItem => {
            const original = changeItem;
            // Clean non-editable fields
            changeItem = object.pick(changeItem, editableProperties);

            this.entityIds.forEach(entityId => {
                const record = { ...changeItem };
                record[referenceKey] = entityId;

                const identifyKey = mappingReferenceField ?? localKey;
                const key = `${original[identifyKey]}.${entityId}`;

                const associations = existAssociations[key] ?? [];

                if (mappingReferenceField && type === bulkSyncTypes.ADD && associations.length > 0) {
                    return;
                }

                let association = null;

                // Only update existing association if there's only one association record
                if (associations.length === 1) {
                    association = { ...associations[0] };
                    existAssociations[key].shift();
                    // Remove existing OneToMany association record from delete payload
                    delete this.groupedPayload.delete[referenceEntity][key];
                }

                const actualChange = this._getOneToManyChange(record, localKey, mappingReferenceField, association);

                if (actualChange === null || Object.keys(actualChange).length === 0) {
                    return;
                }

                this.groupedPayload.upsert[referenceEntity][key] ??= [];
                this.groupedPayload.upsert[referenceEntity][key].push(actualChange);
            });
        });
    }

    /**
     * get actual changes of a OneToMany association, if existedRecord means a new record will be inserted
     * @private
     */
    _getOneToManyChange(updatePayload, localKey, mappingReferenceField, existedRecord = null) {
        const actualChange = {};

        if (mappingReferenceField) {
            actualChange[mappingReferenceField] = updatePayload[mappingReferenceField];
        }

        if (existedRecord) {
            actualChange[localKey] = existedRecord[localKey];

            // These fields are fixed if the oneToMany association exists
            delete updatePayload[localKey];
            delete updatePayload[mappingReferenceField];
        }

        // Detect if there is any change in oneToMany association so we should update it, otherwise we can skip it
        Object.keys(updatePayload).forEach(field => {
            if (
                !existedRecord
                || (updatePayload[field] !== undefined
                    && this._isFieldValueChanged(updatePayload[field], existedRecord[field]))
            ) {
                actualChange[field] = updatePayload[field];
            }
        });

        // Reduce request payload
        if (existedRecord) {
            delete actualChange[mappingReferenceField];
        }

        // If the change payload has any properties other than localKey (id) we should update it
        const hasChanged = Object.keys(actualChange).some(key => key !== localKey);

        // the fields are not updated, skip it
        if (existedRecord && !hasChanged) {
            return null;
        }

        return actualChange;
    }

    /**
     * Handler for bulk edit a ManyToMany association
     *
     * @param change
     * @param existAssociations
     * @private
     */
    _detectManyToManyChange(change, existAssociations) {
        const {
            referenceEntity,
            referenceKey,
            localKey,
            value: items,
        } = change;

        items.forEach(fieldValue => {
            this.entityIds.forEach(entityId => {
                const referenceValue = fieldValue.id;
                const key = `${referenceValue}.${entityId}`;

                if (existAssociations[key]) {
                    delete this.groupedPayload.delete[referenceEntity][key];

                    return;
                }

                this.groupedPayload.upsert[referenceEntity][key] = [{
                    [referenceKey]: referenceValue,
                    [localKey]: entityId,
                }];
            });
        });
    }

    /**
     * @private
     */
    _castDefaultValueIfNecessary(change, fieldType) {
        const { value, type } = change;

        // Cast the value to 0 if the we 'CLEAR' an int or float field
        if (type === bulkSyncTypes.CLEAR) {
            return ['int', 'float'].includes(fieldType) ? 0 : null;
        }

        if (value === '' || typeof value === 'undefined') {
            return null;
        }

        return value;
    }

    /**
     * @private
     *
     * Fetch OneToMany association ids and mapped each id using `${foreignId}.${localId}` as a key
     */
    async _fetchOneToManyAssociated(fieldDefinition, change, page = 1, mappedExistAssociations = {}) {
        const {
            entity,
            localField: localKey,
            referenceField: referenceKey,
        } = fieldDefinition;

        const criteria = new Criteria(page, 500);
        criteria.addFilter(Criteria.equalsAny(referenceKey, this.entityIds));

        /**
         * change.mappingReferenceField to handle special cases like product.visibilities, it will be salesChannelId
         * It's OneToMany association but behave similar to a ManyToMany association
         * We need to prefetch the OneToMany associations to avoid unique constraint.
         * e.g `product_visibility`.`product_id_sales_channel_id`
         */
        if (change.mappingReferenceField && change.type === bulkSyncTypes.REMOVE) {
            const referenceIds = change.value.map(value => value[change.mappingReferenceField]);

            if (referenceIds && referenceIds.filter(Boolean)) {
                criteria.addFilter(Criteria.equalsAny(change.mappingReferenceField, referenceIds));
            }
        }

        const referenceRepository = this.repositoryFactory.create(entity);
        const existAssociations = await referenceRepository.search(criteria);

        existAssociations.forEach(association => {
            let key = association[localKey];

            if (change.mappingReferenceField) {
                const { [referenceKey]: referenceId, [change.mappingReferenceField]: foreignId } = association;
                key = `${foreignId}.${referenceId}`;
            }

            if (mappedExistAssociations.hasOwnProperty(key)) {
                mappedExistAssociations[key].push(association);
            } else {
                mappedExistAssociations[key] = [association];
            }
        });

        if (existAssociations.total > Object.keys(mappedExistAssociations).length) {
            return this._fetchOneToManyAssociated(fieldDefinition, change, page + 1, mappedExistAssociations);
        }

        return mappedExistAssociations;
    }

    /**
     * @private
     *
     * Fetch ManyToMany association ids and mapped each id using `${foreignId}.${localId}` as a key
     */
    async _fetchManyToManyAssociated(fieldDefinition, change, page = 1, mappedExistAssociations = {}) {
        const {
            referenceField,
            mapping: entity,
            local,
            reference,
        } = fieldDefinition;

        const referenceIds = change.type === bulkSyncTypes.REMOVE
            ? change.value.map(value => value[referenceField])
            : null;

        const criteria = new Criteria(page, 500);
        criteria.addFilter(Criteria.equalsAny(local, this.entityIds));

        if (referenceIds && referenceIds.filter(Boolean)) {
            criteria.addFilter(Criteria.equalsAny(reference, referenceIds));
        }

        const mappingRepository = this.repositoryFactory.create(entity);

        const mappingIds = await mappingRepository.searchIds(criteria);
        const existAssociations = mappingIds.data;

        existAssociations.forEach(association => {
            // e.g: { productId: 'product_id_1', categoryId: 'product_cat_2' }
            const {
                [local]: localId,
                [reference]: referenceId,
            } = association;

            const key = `${referenceId}.${localId}`;

            // ManyToMany have 2 primary keys, e.g product_category
            mappedExistAssociations[key] = [association];
        });

        if (mappingIds.total > Object.keys(mappedExistAssociations).length) {
            return this._fetchManyToManyAssociated(fieldDefinition, change, page + 1, mappedExistAssociations);
        }

        return mappedExistAssociations;
    }

    _getEditableProperties(entity) {
        const definition = Shopware.EntityDefinition.get(entity);
        const fields = definition.filterProperties(property => {
            return (definition.isScalarField(property) || definition.isJsonField(property))
                || (!property.flags || property.flags.write_protected);
        });

        return Object.keys(fields).filter(field => !['updatedAt', 'createdAt'].includes(field));
    }

    _transformDeletePayload(deletePayload, localKey, referenceKey) {
        const transformedPayload = {};

        Object.keys(deletePayload).forEach(key => {
            const deleteItems = deletePayload[key] ?? [];

            deleteItems.forEach(deleteItem => {
                const {
                    id,
                    [localKey]: localId,
                    [referenceKey]: referenceId,
                } = deleteItem;

                transformedPayload[key] ??= [];

                if (id) {
                    transformedPayload[key].push({ id });
                } else {
                    transformedPayload[key].push({
                        [localKey]: localId,
                        [referenceKey]: referenceId,
                    });
                }
            });
        });

        return transformedPayload;
    }

    _isFieldValueChanged(newValue, origin) {
        if (types.isObject(newValue) && types.isObject(origin)) {
            return Object.keys(getObjectDiff(newValue, origin)).length > 0;
        }

        return !types.isEqual(newValue, origin);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditBaseHandler;
