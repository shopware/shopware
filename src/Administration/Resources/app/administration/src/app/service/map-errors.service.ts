/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type CamelCasePath<T extends string> = T extends `${infer A}.${infer B}${infer C}`
    ? `${Lowercase<A>}${Capitalize<B>}${CamelCasePath<C>}`
    : Lowercase<T>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, max-len
export function mapPropertyErrors<T extends string, K extends string>(
    entityName: T,
    properties: K[] = [],
): Record<`${Lowercase<T>}${Capitalize<CamelCasePath<K>>}Error`, () => unknown> {
    const computedValues: Record<string, () => unknown> = {};

    properties.forEach((property) => {
        const computedValueName = Shopware.Utils.string.camelCase(`${entityName}.${property}.error`);

        computedValues[computedValueName] = function getterPropertyError() {
            const entity = (this as VueComponent)[entityName];

            const isEntity = entity && typeof entity.getEntityName === 'function';
            if (!isEntity) {
                return null;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
            return Shopware.State.getters['error/getApiError'](entity, property);
        };
    });

    return computedValues;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function mapSystemConfigErrors(entityName: string, saleChannelId: string | null, key: string = ''): $TSFixMe {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
    return Shopware.State.getters['error/getSystemConfigApiError'](entityName, saleChannelId, key);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, max-len
export function mapCollectionPropertyErrors<T extends string, K extends string>(
    entityCollectionName: T,
    properties: K[] = [],
): Record<`${Lowercase<T>}${Capitalize<CamelCasePath<K>>}Error`, () => unknown> {
    const computedValues: Record<string, () => unknown> = {};

    properties.forEach((property) => {
        const computedValueName = Shopware.Utils.string.camelCase(`${entityCollectionName}.${property}.error`);

        computedValues[computedValueName] = function getterCollectionError() {
            const entityCollection = this[entityCollectionName];

            if (!Array.isArray(entityCollection)) {
                return null;
            }

            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
            return entityCollection.map((entity) => Shopware.State.getters['error/getApiError'](entity, property));
        };
    });

    return computedValues;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations, max-len
export function mapPageErrors<T extends string>(
    errorConfig: Record<T, Record<string, string[]>>,
): Record<`${CamelCasePath<T>}Error`, () => boolean> {
    const map: Record<string, () => boolean> = {};
    Object.keys(errorConfig).forEach((routeName) => {
        const subjects = errorConfig[routeName as T];
        map[`${Shopware.Utils.string.camelCase(routeName)}Error`] = function getterPropertyError() {
            return Object.keys(subjects).some((entityName) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                return Shopware.State.getters['error/existsErrorInProperty'](entityName, subjects[entityName]);
            });
        };
    });
    return map;
}
