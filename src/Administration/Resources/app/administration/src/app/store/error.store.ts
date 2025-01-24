/**
 * @sw-package framework
 */
import type ShopwareError from 'src/core/data/ShopwareError';

const utils = Shopware.Utils;

/**
 * @private
 */
// eslint-disable-next-line no-use-before-define,sw-deprecation-rules/private-feature-declarations
export type ErrorStore = ReturnType<typeof errorStore>;

/**
 * @private
 * @description Creates a path to the error in the error store
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function createPathToError(expression: string, store: ErrorStore['api']): { field: string; deepState: string } {
    const path = expression.split('.');
    const field = path.pop();

    // @ts-expect-error
    const deepState = path.reduce((currentStore, next) => {
        if (!currentStore.hasOwnProperty(next)) {
            // @ts-expect-error
            currentStore[next] = {};
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return currentStore[next];
    }, store);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    return { field: field!, deepState };
}

/**
 * @private
 * @description Sets an api error in the error store
 * */
function setApiErrorNested({ expression, error, store }: { expression: string; error: ShopwareError; store: ErrorStore }) {
    const { field, deepState } = createPathToError(expression, store.api);

    // @ts-expect-error
    deepState[field] = error;
}

/**
 * Removes the error for a given expression from the error store
 */
function removeApiError(expression: string, store: ErrorStore) {
    const path = expression.split('.');
    const field = path.pop();

    // @ts-expect-error
    const deepState = path.reduce((currentStore, next) => {
        if (currentStore?.[next]) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return currentStore[next];
        }

        return null;
    }, store.api);

    if (deepState === null) {
        return;
    }

    // @ts-expect-error
    delete deepState[field];

    if (path.length <= 0) {
        return;
    }

    if (Shopware.Utils.types.isEmpty(store)) {
        removeApiError(path.join('.'), store);
    }
}

interface GivenEntity {
    getEntityName(): string;
    id: string;
}

/**
 * @private
 * @description Store for global api error handling
 */
const errorStore = Shopware.Store.register({
    id: 'error',

    state: () =>
        ({
            system: {},
            api: {},
        }) as {
            system: Record<string, ShopwareError>;
            api: Record<string, ShopwareError>;
        },

    actions: {
        addApiError({ expression, error }: { expression: string; error: ShopwareError }) {
            error.selfLink = expression;

            setApiErrorNested({
                expression,
                error,
                store: this,
            });
        },

        removeApiError(expression: string) {
            removeApiError(expression, this);
        },

        resetApiErrors() {
            this.api = {};
        },

        addSystemError({ error, id = utils.createId() }: { error: ShopwareError; id?: string }) {
            this.system[id] = error;
        },

        removeSystemError(id: string) {
            delete this.system[id];
        },
    },

    getters: {
        getErrorsForEntity(state) {
            return (entityName: string, id: string) => {
                const entityStorage = state.api[entityName];

                if (!entityStorage) {
                    return null;
                }

                // @ts-expect-error
                return (entityStorage[id] as ShopwareError) ?? null;
            };
        },

        getApiErrorFromPath() {
            return (entityName: string, id: string, path: string[]) => {
                return path.reduce(
                    (store, next) => {
                        if (store === null) {
                            return null;
                        }

                        if (!store.hasOwnProperty(next)) {
                            return null;
                        }

                        // @ts-expect-error
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                        return store[next];
                    },
                    this.getErrorsForEntity(entityName, id),
                );
            };
        },

        getApiError() {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            return (entity: GivenEntity, field: string) => {
                const path = field.split('.');

                return this.getApiErrorFromPath(entity.getEntityName(), entity.id, path);
            };
        },

        getSystemConfigApiError() {
            return (entityName: string, saleChannelId: string, key: string) => {
                const errors = this.getErrorsForEntity(entityName, saleChannelId);

                if (errors === null) {
                    return null;
                }

                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return errors[key] ?? null;
            };
        },

        getAllApiErrors(state) {
            return () => {
                return Object.values(state.api);
            };
        },

        existsErrorInProperty(state) {
            return (entity: string, properties: string[]) => {
                const entityErrors = state.api[entity];
                if (!entityErrors) {
                    return false;
                }

                return Object.keys(entityErrors).some((id) => {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    const instance = entityErrors[id];

                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    return Object.keys(instance).some((propWithError) => {
                        return properties.includes(propWithError);
                    });
                });
            };
        },

        getSystemError(state) {
            return (id: string) => {
                return state.system[id] ?? null;
            };
        },

        countSystemError(state) {
            return () => {
                return Object.keys(state.system).length;
            };
        },
    },
});

/**
 * @private
 */
export default errorStore;
