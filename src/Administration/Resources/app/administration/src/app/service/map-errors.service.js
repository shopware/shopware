const { string } = Shopware.Utils;

/** @deprecated tag:v6.4.0 use mapPropertyError instead */
export function mapApiErrors(subject, properties = []) {
    Shopware.Utils.debug.warn(
        'mapApiErrors',
        'The componentHelper "mapApiErrors" is deprecated and ' +
        'will be removed in 6.4.0 - use "mapPropertyErrors" instead'
    );

    // Only for unit testing
    if (process && process.env && process.env.NODE_ENV === 'test') {
        return this.mapPropertyErrors(subject, properties);
    }

    /* istanbul ignore next */
    return mapPropertyErrors(subject, properties);
}

export function mapPropertyErrors(entityName, properties = []) {
    const computedValues = {};

    properties.forEach((property) => {
        const computedValueName = string.camelCase(`${entityName}.${property}.error`);

        computedValues[computedValueName] = function getterPropertyError() {
            const entity = this[entityName];

            const isEntity = entity && typeof entity.getEntityName === 'function';
            if (!isEntity) {
                return null;
            }

            return Shopware.State.getters['error/getApiError'](entity, property);
        };
    });

    return computedValues;
}

export function mapCollectionPropertyErrors(entityCollectionName, properties = []) {
    const computedValues = {};

    properties.forEach((property) => {
        const computedValueName = string.camelCase(`${entityCollectionName}.${property}.error`);

        computedValues[computedValueName] = function getterCollectionError() {
            const entityCollection = this[entityCollectionName];

            if (!Array.isArray(entityCollection)) {
                return null;
            }

            return entityCollection.map((entity) => Shopware.State.getters['error/getApiError'](entity, property));
        };
    });

    return computedValues;
}

export function mapPageErrors(errorConfig) {
    const map = {};
    Object.keys(errorConfig).forEach((routeName) => {
        const subjects = errorConfig[routeName];
        map[`${string.camelCase(routeName)}Error`] = function getterPropertyError() {
            return Object.keys(subjects).some((entityName) => {
                return Shopware.State.getters['error/existsErrorInProperty'](entityName, subjects[entityName]);
            });
        };
    });
    return map;
}
