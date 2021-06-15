const { Mixin } = Shopware;
const { types, debug } = Shopware.Utils;

/**
 * Mixin which resets entity changes on page leave or if the id of the entity changes.
 * This also affects changes in associations of the entity
 *
 * Usage:
 *   mixins: [
 *       Mixin.getByName('discard-detail-page-changes')('product')
 *   ],
 *
 */
Mixin.register('discard-detail-page-changes', (...entityNames) => {
    const entities = [];

    function tryAddEntity(name) {
        if (types.isString(name)) {
            entities.push(name);
        }
    }

    entityNames.forEach((name) => {
        if (types.isArray(name)) {
            name.forEach((item) => {
                tryAddEntity(item);
            });
            return;
        }

        tryAddEntity(name);
    });

    if (entities.length < 1) {
        throw new Error('discard-detail-page-changes.mixin - You need to handle over the entity names');
    }

    return {
        beforeRouteLeave(to, from, next) {
            this.discardChanges();

            next();
        },

        watch: {
            '$route.params.id'() {
                this.discardChanges();
            },
        },

        methods: {
            discardChanges() {
                entities.forEach((entityName) => {
                    const entity = this[entityName];
                    if (entity && typeof entity.discardChanges === 'function') {
                        entity.discardChanges();
                        return;
                    }

                    debug.warn('Discard-detail-page-changes Mixin',
                        `Could not discard changes for entity with name "${entityName}".`);
                });
            },
        },
    };
});
