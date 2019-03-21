export default class ChangesetGenerator {
    constructor(schema) {
        this.schema = schema;
        this.scalar = ['uuid', 'int', 'text', 'password', 'float', 'string', 'blob', 'boolean', 'date'];
    }

    generate(entity, deletionQueue = []) {
        const definition = this.schema[entity.getEntityName()];
        const changes = {};

        const jsonTypes = ['json_list', 'json_object'];

        const origin = entity.getOrigin();
        const draft = entity.getDraft();

        Object.entries(definition.properties).forEach(([property, type]) => {
            const draftValue = draft[property];
            const originValue = origin[property];

            // skip read only
            if (type.readOnly) {
                return true;
            }

            if (this.scalar.includes(type.type)) {
                if (draftValue !== originValue) {
                    changes[property] = draftValue;
                    return true;
                }
            }

            if (jsonTypes.includes(type.type)) {
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
                const change = this.generate(draftValue, deletionQueue);

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

    handleManyToMany(draft, origin, deletionQueue) {
        const changes = [];
        const originIds = Object.keys(origin);

        Object.entries(draft).forEach((entity) => {
            if (!originIds.includes(entity.id)) {
                changes.push({ id: entity.id });
            }

            return true;
        });

        originIds.forEach((id) => {
            if (!draft.has(id)) {
                deletionQueue.push({ route: draft.source, key: id });
            }
            return true;
        });

        return changes;
    }

    handleOneToMany(draft, origin, deletionQueue) {
        const changes = [];
        const originIds = Object.keys(origin);

        // check for new and updated items
        Object.entries(draft).forEach(([key, entity]) => {
            // new record?
            if (!originIds.includes(entity.id)) {
                changes.push(entity.getDraft());
                return true;
            }

            // check if some properties changed
            const change = this.generate(entity, deletionQueue);
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
