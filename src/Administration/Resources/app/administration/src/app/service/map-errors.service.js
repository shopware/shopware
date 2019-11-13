const { string } = Shopware.Utils;

export function mapApiErrors(subject, properties = []) {
    const map = {};
    properties.forEach((property) => {
        const getter = string.camelCase(`${subject}.${property}.error`);
        map[getter] = function getterApiError() {
            if (this[subject] && typeof this[subject].getEntityName === 'function') {
                return Shopware.State.getters['error/getApiError'](this[subject], property);
            }
            return null;
        };
    });

    return map;
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
