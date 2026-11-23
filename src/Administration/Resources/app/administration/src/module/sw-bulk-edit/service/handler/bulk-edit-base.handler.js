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
 * @sw-package framework
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

        await Promise.all(
            changes.map(async (change) => {
                if (!Object.values(bulkSyncTypes).includes(change.type)) {
                    return;
                }

                // If the change type is not a toMany association change,
                // grouped the change by entity's id so each entity can have a same sync payload
                const field = definition.getField(change.field);

                if (!field) {
                    Shopware.Utils.debug.warn('Entity factory', `Property ${this.entityName}.${change.field} not found`);

                    return;
                }

                const isOneToOne = definition.isOneToOneAssociation(field);
                if (definition.isToManyAssociation(field) || isOneToOne) {
                    try {
                        await this._handleAssociationChange(field, change, isOneToOne);

                        return;
                    } catch (e) {
                        Shopware.Utils.debug.warn(e);

                        // Ignore the failed change
                        return;
                    }
                }

                const value = this._castDefaultValueIfNecessary(change, field.type);

                this.entityIds.forEach((id) => {
                    this.groupedPayload.upsert[this.entityName][id] ??= { id };
                    this.groupedPayload.upsert[this.entityName][id][change.field] = value;
                });
            }),
        );

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

        Object.keys(groupedPayload).forEach((operator) => {
            const operatorPayload = groupedPayload[operator];

            if (Object.keys(operatorPayload).length === 0) {
                return;
            }

            Object.keys(operatorPayload).forEach((payloadEntity) => {
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
     * @param {boolean} isOneToOne
     * @example
     * change =[{ type: 'overwrite', field: 'categories', value: [{id: 'category_1'}, {id: 'category_2'}]];
     */
    async _handleAssociationChange(fieldDefinition, change, isOneToOne = false) {
        // Variant add/remove of inherited associations (visibilities, properties) is computed
        // per variant: an overriding variant keeps its own set, an inheriting variant falls back
        // to the parent's set, and the removed entries are dropped / the added ones merged in.
        // This cannot be expressed as a single uniform change, so it is handled separately.
        const inheritedVariantField = this._getInheritedVariantField(fieldDefinition, change);
        if (inheritedVariantField) {
            await this._handleInheritedVariantChange(inheritedVariantField);

            return;
        }

        const { mapping, entity, local, reference, localField, referenceField } = fieldDefinition;

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
        } else if (isOneToOne) {
            this._detectOneToOneChange(change, existAssociations);
        } else {
            this._detectOneToManyChange(change, existAssociations);
        }
    }

    /**
     * @private
     *
     * Describe the inherited variant association a change targets, by field name, or return
     * null when the change is not a per-variant add/remove (see `transformVariantVisibilityChange`
     * and `transformVariantPropertyChange` in `sw-bulk-edit-product`).
     *
     * `entityIdKey` is the row column holding the variant id, `itemKey` the column holding the
     * set item (sales channel id or property option id). `inherited` and `added` are
     * `[key, value]` entries: the sales channel id and its visibility, or the property option id
     * without a value.
     *
     * @param {Object} fieldDefinition
     * @param {Object} change
     * @return {Object|null}
     */
    _getInheritedVariantField(fieldDefinition, change) {
        if (change.field === 'visibilities' && change.inheritedVisibilities) {
            const { mappingReferenceField } = change;
            const toEntry = (item) => [item[mappingReferenceField], item.visibility];

            return {
                referenceEntity: fieldDefinition.entity,
                entityIdKey: fieldDefinition.referenceField,
                itemKey: mappingReferenceField,
                valueKey: 'visibility',
                inherited: change.inheritedVisibilities.map(toEntry),
                added: (change.addedVisibilities ?? []).map(toEntry),
                removedKeys: change.removedSalesChannelIds ?? [],
                fetchOwnRows: async () => Object.values(await this._fetchOwnAssociations(fieldDefinition)).flat(),
                deletePayload: (row) => ({ id: row.id }),
            };
        }

        if (change.field === 'properties' && change.inheritedPropertyIds) {
            const { local, reference } = fieldDefinition;
            const toEntry = (id) => [id, undefined];

            return {
                referenceEntity: fieldDefinition.mapping,
                entityIdKey: local,
                itemKey: reference,
                valueKey: null,
                inherited: change.inheritedPropertyIds.map(toEntry),
                added: (change.addedPropertyIds ?? []).map(toEntry),
                removedKeys: change.removedPropertyIds ?? [],
                // Without a change type filter this fetches every own mapping row of the edited variants.
                fetchOwnRows: async () => Object.values(await this._fetchManyToManyAssociated(fieldDefinition, {})).flat(),
                deletePayload: (row) => ({ [local]: row[local], [reference]: row[reference] }),
            };
        }

        return null;
    }

    /**
     * @private
     *
     * Build the add/remove payload of an inherited variant association, computed per variant.
     *
     * Variants inherit the parent's set all-or-nothing unless they own rows. For each variant we
     * take its effective set (its own rows when it overrides, otherwise the inherited parent set),
     * drop the removed keys, merge in the added entries (which win on their value), and persist
     * the difference as the variant's own rows. An ADD no longer collapses an inheriting variant
     * to just the added entries, and a REMOVE also drops inherited entries. An inheriting variant
     * whose set does not change keeps inheriting. When the resulting set is empty the variant
     * keeps no own rows and inherits the parent again.
     *
     * @param {Object} field - see `_getInheritedVariantField`
     */
    async _handleInheritedVariantChange(field) {
        const { referenceEntity, entityIdKey, itemKey, valueKey, removedKeys } = field;
        const inherited = new Map(field.inherited);
        const added = new Map(field.added);

        this.groupedPayload.delete[referenceEntity] = {};
        this.groupedPayload.upsert[referenceEntity] = {};

        // Nothing selected (e.g. the field stayed inherited) — leave every variant untouched.
        if (removedKeys.length === 0 && added.size === 0) {
            return;
        }

        const ownRowsByEntity = {};
        (await field.fetchOwnRows()).forEach((row) => {
            (ownRowsByEntity[row[entityIdKey]] ??= new Map()).set(row[itemKey], row);
        });

        this.entityIds.forEach((entityId) => {
            const ownRows = ownRowsByEntity[entityId] ?? new Map();
            const own = new Map([...ownRows].map(([key, row]) => [key, valueKey ? row[valueKey] : undefined]));

            const final = new Map([...(own.size > 0 ? own : inherited)].filter(([key]) => !removedKeys.includes(key)));
            added.forEach((value, key) => final.set(key, value));

            const keepsInheriting =
                own.size === 0 &&
                final.size === inherited.size &&
                [...inherited].every(([key, value]) => final.has(key) && final.get(key) === value);

            if (keepsInheriting) {
                return;
            }

            own.forEach((_, key) => {
                if (!final.has(key)) {
                    this.groupedPayload.delete[referenceEntity][`${key}.${entityId}`] = [
                        field.deletePayload(ownRows.get(key)),
                    ];
                }
            });

            final.forEach((value, key) => {
                if (!own.has(key)) {
                    this.groupedPayload.upsert[referenceEntity][`${key}.${entityId}`] = [
                        {
                            [entityIdKey]: entityId,
                            [itemKey]: key,
                            ...(valueKey ? { [valueKey]: value } : {}),
                        },
                    ];
                }
            });
        });
    }

    /**
     * @private
     *
     * Fetch every own OneToMany association row for the edited entities, grouped by the
     * local entity id (e.g. all `product_visibility` rows per variant), regardless of
     * the change value.
     */
    async _fetchOwnAssociations(fieldDefinition, page = 1, mappedAssociations = {}) {
        const { entity, referenceField } = fieldDefinition;

        const criteria = new Criteria(page, 500);
        criteria.addFilter(Criteria.equalsAny(referenceField, this.entityIds));

        const referenceRepository = this.repositoryFactory.create(entity);
        const ownAssociations = await referenceRepository.search(criteria);

        ownAssociations.forEach((association) => {
            const entityId = association[referenceField];

            (mappedAssociations[entityId] ??= []).push(association);
        });

        const mappedLength = Object.keys(mappedAssociations).reduce((acc, key) => acc + mappedAssociations[key].length, 0);

        if (ownAssociations.total > mappedLength) {
            return this._fetchOwnAssociations(fieldDefinition, page + 1, mappedAssociations);
        }

        return mappedAssociations;
    }

    /**
     * Handler for bulk edit a OneToMany association
     * @param change
     * @param existAssociations
     * @private
     */
    _detectOneToManyChange(change, existAssociations) {
        const { referenceEntity, referenceKey, localKey, mappingReferenceField, value: changeItems, type } = change;
        const editableProperties = this._getEditableProperties(referenceEntity);

        if (mappingReferenceField) {
            editableProperties.push(mappingReferenceField);
        }

        // Subclasses may decorate each newly built record, e.g. to assign a per-entity position.
        // Returns null when there is nothing to decorate.
        const decorateRecord = this._getOneToManyRecordDecorator(change, existAssociations);

        changeItems.forEach((changeItem) => {
            const original = changeItem;
            // Clean non-editable fields
            changeItem = object.pick(changeItem, editableProperties);

            this.entityIds.forEach((entityId) => {
                const record = { ...changeItem };
                record[referenceKey] = entityId;

                const identifyKey = mappingReferenceField ?? localKey;
                const key = mappingReferenceField ? `${original[identifyKey]}.${entityId}` : entityId;

                const associations = existAssociations[key] ?? [];
                if (mappingReferenceField && type === bulkSyncTypes.ADD && associations.length > 0) {
                    return;
                }

                let association = null;

                // Only update existing association if there's only one association record
                if (type === bulkSyncTypes.OVERWRITE && associations.length === 1) {
                    association = { ...associations[0] };
                    existAssociations[key].shift();
                    // Remove existing OneToMany association record from delete payload
                    delete this.groupedPayload.delete[referenceEntity][key];
                }

                if (decorateRecord) {
                    decorateRecord(record, entityId);
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
     * @private
     *
     * Hook for subclasses to decorate each newly built OneToMany record (e.g. assign a per-entity position).
     * Returns a `(record, entityId) => void` callback, or null when no decoration is needed.
     */
    _getOneToManyRecordDecorator() {
        return null;
    }

    /**
     * Handler for bulk edit a OneToOne association
     * @param change
     * @param existAssociations
     * @private
     */
    _detectOneToOneChange(change, existAssociations) {
        const { referenceEntity, referenceKey, localKey, value: changeItems } = change;
        const editableProperties = this._getEditableProperties(referenceEntity);
        changeItems.forEach((changeItem) => {
            changeItem = object.pick(changeItem, editableProperties);

            this.entityIds.forEach((entityId) => {
                const record = { ...changeItem };
                record[referenceKey] = entityId;

                const key = entityId;
                const associations = existAssociations[key] ?? [];

                let association = null;
                if (associations.length === 1) {
                    association = { ...associations[0] };
                    existAssociations[key].shift();

                    delete this.groupedPayload.delete[referenceEntity][key];
                }

                const actualChange = this._getOneToManyChange(record, localKey, null, association);

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
        Object.keys(updatePayload).forEach((field) => {
            if (
                !existedRecord ||
                (updatePayload[field] !== undefined && this._isFieldValueChanged(updatePayload[field], existedRecord[field]))
            ) {
                actualChange[field] = updatePayload[field];
            }
        });

        // Reduce request payload
        if (existedRecord) {
            delete actualChange[mappingReferenceField];
        }

        // If the change payload has any properties other than localKey (id) we should update it
        const hasChanged = Object.keys(actualChange).some((key) => key !== localKey);

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
        const { referenceEntity, referenceKey, localKey, value: items } = change;

        items.forEach((fieldValue) => {
            this.entityIds.forEach((entityId) => {
                const referenceValue = fieldValue.id;
                const key = `${referenceValue}.${entityId}`;

                if (existAssociations[key]) {
                    delete this.groupedPayload.delete[referenceEntity][key];

                    return;
                }

                this.groupedPayload.upsert[referenceEntity][key] = [
                    {
                        [referenceKey]: referenceValue,
                        [localKey]: entityId,
                    },
                ];
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
        const { entity, referenceField: referenceKey } = fieldDefinition;

        const criteria = new Criteria(page, 500);
        criteria.addFilter(Criteria.equalsAny(referenceKey, this.entityIds));

        /**
         * change.mappingReferenceField to handle special cases like product.visibilities, it will be salesChannelId
         * It's OneToMany association but behave similar to a ManyToMany association
         * We need to prefetch the OneToMany associations to avoid unique constraint.
         * e.g `product_visibility`.`product_id_sales_channel_id`
         */
        if (change.mappingReferenceField && change.type === bulkSyncTypes.REMOVE) {
            const referenceIds = change.value.map((value) => value[change.mappingReferenceField]);

            if (referenceIds && referenceIds.filter(Boolean)) {
                criteria.addFilter(Criteria.equalsAny(change.mappingReferenceField, referenceIds));
            }
        }

        const referenceRepository = this.repositoryFactory.create(entity);
        const existAssociations = await referenceRepository.search(criteria);

        existAssociations.forEach((association) => {
            let key = association[referenceKey];

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

        // Associations at key can be more than one, so they must be counted properly
        const mappedExistAssociationsLen = Object.keys(mappedExistAssociations).reduce(
            (acc, key) => acc + mappedExistAssociations[key].length,
            0,
        );

        if (existAssociations.total > mappedExistAssociationsLen) {
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
        const { referenceField, mapping: entity, local, reference } = fieldDefinition;

        const referenceIds =
            change.type === bulkSyncTypes.REMOVE ? change.value.map((value) => value[referenceField]) : null;

        const criteria = new Criteria(page, 500);
        criteria.addFilter(Criteria.equalsAny(local, this.entityIds));

        if (referenceIds && referenceIds.filter(Boolean)) {
            criteria.addFilter(Criteria.equalsAny(reference, referenceIds));
        }

        const mappingRepository = this.repositoryFactory.create(entity);

        const mappingIds = await mappingRepository.searchIds(criteria);
        const existAssociations = mappingIds.data;

        existAssociations.forEach((association) => {
            // e.g: { productId: 'product_id_1', categoryId: 'product_cat_2' }
            const { [local]: localId, [reference]: referenceId } = association;

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
        const fields = definition.filterProperties((property) => {
            return (
                definition.isScalarField(property) ||
                definition.isJsonField(property) ||
                !property.flags ||
                property.flags.write_protected
            );
        });

        return Object.keys(fields).filter((field) => !['updatedAt', 'createdAt'].includes(field));
    }

    _transformDeletePayload(deletePayload, localKey, referenceKey) {
        const transformedPayload = {};

        Object.keys(deletePayload).forEach((key) => {
            const deleteItems = deletePayload[key] ?? [];

            deleteItems.forEach((deleteItem) => {
                const { id, [localKey]: localId, [referenceKey]: referenceId } = deleteItem;

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
