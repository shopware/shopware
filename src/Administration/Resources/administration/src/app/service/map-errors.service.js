import { string } from 'src/core/service/util.service';

export function mapFormErrors(subject, properties = []) {
    const map = {};
    properties.forEach((property) => {
        const getter = string.camelCase(`${subject}.${property}.error`);
        map[getter] = function getterApiError() {
            if (typeof this[subject].getEntityName === 'function') {
                return this.$store.getters.getApiError(this[subject], property);
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
                return this.$store.getters.existsErrorInProperty(entityName, subjects[entityName]);
            });
        };
    });
    return map;
}
