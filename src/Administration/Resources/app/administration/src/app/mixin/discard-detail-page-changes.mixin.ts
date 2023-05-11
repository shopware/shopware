import type { NavigationGuardNext, Route } from 'vue-router';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * Mixin which resets entity changes on page leave or if the id of the entity changes.
 * This also affects changes in associations of the entity
 *
 * Usage:
 *   mixins: [
 *       Mixin.getByName('discard-detail-page-changes')('product')
 *   ],
 *
 */
Mixin.register('discard-detail-page-changes', (...entityNames: Array<string|Array<string>>) => {
    const entities: string[] = [];

    function tryAddEntity(name: string) {
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
        throw new Error('discard-detail-page-changes.mixin - You need to provide the entity names');
    }

    return Shopware.Component.wrapComponentConfig({
        beforeRouteLeave(to: Route, from: Route, next: NavigationGuardNext) {
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
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any
                    const entity: EntitySchema.Entity<any> = this[entityName];

                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    if (entity && typeof entity.discardChanges === 'function') {
                        // eslint-disable-next-line max-len
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                        entity.discardChanges();
                        return;
                    }

                    Shopware.Utils.debug.warn(
                        'Discard-detail-page-changes Mixin',
                        `Could not discard changes for entity with name "${entityName}".`,
                    );
                });
            },
        },
    });
});
