import types from 'src/core/service/utils/types.utils';

function castValueToNullIfNecessary(value) {
    if (value === '' || typeof value === 'undefined') {
        return null;
    }
    return value;
}

export default class ChangesetGenerator {
    /**
     * returns the primary key data of an entity
     * @param entity
     */
    getPrimaryKeyData(entity) {
        const definition = Shopware.EntityDefinition.get(entity.getEntityName());
        const pkFields = definition.getPrimaryKeyFields();
        const pkData = {};

        Object.keys(pkFields).forEach((fieldName) => {
            pkData[fieldName] = entity[fieldName];
        });

        return pkData;
    }

    /**
     * Creates the change set for the provided entity.
     * @param entity
     * @returns {{changes: *, deletionQueue: Array}}
     */
    generate(entity) {
        const deletionQueue = [];
        const changes = this.recursion(entity, deletionQueue);

        return { changes, deletionQueue };
    }

    /**
     * @private
     * @param {Entity} entity
     * @param deletionQueue
     * @param updateQueue
     * @returns {null}
     */
    recursion(entity, deletionQueue) {
        const definition = Shopware.EntityDefinition.get(entity.getEntityName());
        const changes = {};

        const origin = entity.getOrigin();
        const draft = entity.getDraft();

        definition.forEachField((field, fieldName) => {
            if (field.readOnly) {
                return;
            }

            if (field.flags.write_protected) {
                return;
            }

            let draftValue = castValueToNullIfNecessary(draft[fieldName]);
            let originValue = castValueToNullIfNecessary(origin[fieldName]);

            if (definition.isScalarField(field)) {
                if (draftValue !== originValue) {
                    changes[fieldName] = draftValue;
                }
                return;
            }

            if (field.flags.extension) {
                draftValue = castValueToNullIfNecessary(draft.extensions[fieldName]);
                originValue = castValueToNullIfNecessary(origin.extensions[fieldName]);
            }

            if (definition.isJsonField(field)) {
                if (!types.isEqual(originValue, draftValue)) {
                    if (Array.isArray(draftValue) && draftValue.length <= 0) {
                        changes[fieldName] = [];
                        return;
                    }

                    changes[fieldName] = draftValue;
                }

                return;
            }

            if (field.type !== 'association') {
                // if we don't know what kind of field we write send complete draft
                changes[fieldName] = draftValue;
                return;
            }

            switch (field.relation) {
                case 'one_to_many': {
                    const associationChanges = this.handleOneToMany(field, draftValue, originValue, deletionQueue);
                    if (associationChanges.length > 0) {
                        changes[fieldName] = associationChanges;
                    }
                    break;
                }
                case 'many_to_many': {
                    const associationChanges = this.handleManyToMany(draftValue, originValue, deletionQueue);
                    if (associationChanges.length > 0) {
                        changes[fieldName] = associationChanges;
                    }
                    break;
                }
                case 'one_to_one': {
                    if (!draftValue) {
                        return;
                    }

                    const change = this.recursion(draftValue, deletionQueue);
                    if (change !== null) {
                        // if a change is detected, add id as identifier for updates
                        change.id = draftValue.id;
                        changes[fieldName] = change;
                    }
                    break;
                }
                case 'many_to_one':
                default: {
                    break;
                }
            }
        });

        if (Object.keys(changes).length > 0) {
            return changes;
        }

        return null;
    }

    /**
     * @private
     * @param {EntityCollection} draft
     * @param {EntityCollection} origin
     * @param deletionQueue
     * @returns {Array}
     */
    handleManyToMany(draft, origin, deletionQueue) {
        const changes = [];
        const originIds = origin.getIds();

        draft.forEach((entity) => {
            if (!originIds.includes(entity.id)) {
                changes.push({ id: entity.id });
            }
        });

        originIds.forEach((id) => {
            if (!draft.has(id)) {
                deletionQueue.push({ route: draft.source, key: id });
            }
        });

        return changes;
    }

    /**
     * @private
     * @param {Object} field
     * @param {EntityCollection} draft
     * @param {EntityCollection} origin
     * @param {Array} deletionQueue
     * @returns {Array}
     */
    handleOneToMany(field, draft, origin, deletionQueue) {
        const changes = [];
        const originIds = origin.getIds();

        // check for new and updated items
        draft.forEach((entity) => {
            // new record?
            if (!originIds.includes(entity.id)) {
                let change = this.recursion(entity, []);

                if (change === null) {
                    change = { id: entity.id };
                } else {
                    change.id = entity.id;
                }

                changes.push(change);

                return;
            }

            // check if some properties changed
            const change = this.recursion(entity, deletionQueue);
            if (change !== null) {
                // if a change is detected, add id as identifier for updates
                change.id = entity.id;
                changes.push(change);
            }
        });

        if (field.flags?.cascade_delete) {
            originIds.forEach((id) => {
                if (!draft.has(id)) {
                    // still existing?
                    deletionQueue.push({
                        route: draft.source,
                        key: id,
                    });
                }
            });
            return changes;
        }

        if (!field.referenceField) {
            return changes;
        }

        originIds.forEach((id) => {
            if (!draft.has(id)) {
                const data = { id };
                data[field.referenceField] = null;
                changes.push(data);
            }
        });

        return changes;
    }
}
