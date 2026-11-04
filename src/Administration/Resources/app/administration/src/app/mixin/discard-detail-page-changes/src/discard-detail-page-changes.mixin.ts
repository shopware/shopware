/**
 * @sw-package framework
 */
import { isArray, isString, type NavigationGuardNext, useErrorStore, warn, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 * @public
 *
 * Mixin which resets entity changes on page leave or if the id of the entity changes.
 * This also affects changes in associations of the entity
 *
 * Usage:
 *   mixins: [
 *       Mixin.getByName('discard-detail-page-changes')('product')
 *   ],
 *
 */
export default (...entityNames: Array<string | Array<string>>) => {
    const entities: string[] = [];

    function tryAddEntity(name: string) {
        if (isString(name)) {
            entities.push(name);
        }
    }

    entityNames.forEach((name) => {
        if (isArray(name)) {
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

    return wrapComponentConfig({
        beforeRouteLeave(to, from, next: NavigationGuardNext) {
            this.discardChanges();

            next();
        },

        watch: {
            '$route.params.id'() {
                this.discardChanges();
            },
        },

        methods: {
            discardChanges(): void {
                entities.forEach((entityName) => {
                    // @ts-expect-error - we check if the entity exists on the component
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any
                    const entity: Entity<any> = this[entityName];

                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    if (entity && typeof entity.discardChanges === 'function') {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
                        entity.discardChanges();
                        return;
                    }

                    warn(
                        'Discard-detail-page-changes Mixin',
                        `Could not discard changes for entity with name "${entityName}".`,
                    );
                });

                // reset all api errors
                const errorStore = useErrorStore();
                errorStore.resetApiErrors();
            },
        },
    });
};
