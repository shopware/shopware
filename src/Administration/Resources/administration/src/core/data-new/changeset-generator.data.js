export default class ChangesetGenerator {
    constructor(schema) {
        this.schema = schema;
        this.scalar = ['uuid', 'int', 'text', 'password', 'float', 'string', 'blob', 'boolean', 'date'];
        this.jsonTypes = ['json_list', 'json_object'];
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
     * @returns {null}
     */
    recursion(entity, deletionQueue) {
        const definition = this.schema[entity.getEntityName()];
        const changes = {};

        const origin = entity.getOrigin();
        const draft = entity.getDraft();

        Object.keys(definition.properties).forEach((property) => {
            const type = definition.properties[property];
            // skip read only
            if (type.readOnly) {
                return true;
            }

            const draftValue = draft[property];
            const originValue = origin[property];

            if (this.scalar.includes(type.type)) {
                if (draftValue !== originValue) {
                    changes[property] = draftValue;
                    return true;
                }
            }

            if (this.jsonTypes.includes(type.type)) {
                const equals = JSON.stringify(originValue) === JSON.stringify(draftValue);

                if (!equals) {
                    changes[property] = draftValue;
                }

                return true;
            }

            if (type.type === 'association' && type.relation === 'one_to_many') {
                const associationChanges = this.handleOneToMany(draftValue, originValue, deletionQueue);
                if (associationChanges.length > 0) {
                    changes[property] = associationChanges;
                }

                return true;
            }

            if (type.type === 'association' && type.relation === 'many_to_many') {
                const associationChanges = this.handleManyToMany(draftValue, originValue, deletionQueue);

                if (associationChanges.length > 0) {
                    changes[property] = associationChanges;
                }

                return true;
            }

            // we can skip many to one, the foreign key will be set over the foreignKey field
            if (type.type === 'association' && type.relation === 'many_to_one') {
                return true;
            }

            if (type.type === 'association' && type.relation === 'one_to_one') {
                const change = this.recursion(draftValue, deletionQueue);

                if (change !== null) {
                    // if a change is detected, add id as identifier for updates
                    change.id = draftValue.id;
                    changes.push(change);
                }
            }

            return true;
        });

        if (Object.keys(changes).length > 0) {
            return changes;
        }

        return null;
    }

    /**
     * @private
     * @param draft
     * @param origin
     * @param deletionQueue
     * @returns {Array}
     */
    handleManyToMany(draft, origin, deletionQueue) {
        const changes = [];
        const originIds = Object.keys(origin.items);

        Object.keys(draft.items).forEach((key) => {
            const entity = draft[key];

            if (!originIds.includes(entity.id)) {
                changes.push({ id: entity.id });
            }
        });

        originIds.forEach((id) => {
            if (!draft.has(id)) {
                deletionQueue.push({ route: draft.source, key: id });
            }
            return true;
        });

        return changes;
    }

    /**
     *
     * @param {Object} draft
     * @param {Object} origin
     * @param {Array} deletionQueue
     * @returns {Array}
     */
    handleOneToMany(draft, origin, deletionQueue) {
        const changes = [];
        const originIds = Object.keys(origin.items);

        // check for new and updated items
        Object.keys(draft.items).forEach((key) => {
            const entity = draft.items[key];
            // new record?
            if (!originIds.includes(key)) {
                let change = this.recursion(entity, []);

                if (change === null) {
                    change = { id: entity.id };
                } else {
                    change.id = entity.id;
                }

                changes.push(change);

                return true;
            }

            // check if some properties changed
            const change = this.recursion(entity, deletionQueue);
            if (change !== null) {
                // if a change is detected, add id as identifier for updates
                change.id = entity.id;
                changes.push(change);
            }
            return true;
        });

        originIds.forEach((id) => {
            if (!draft.has(id)) {
                // still existing?
                deletionQueue.push({
                    route: draft.source,
                    key: id
                });
            }
        });

        return changes;
    }
}
