/**
 * @sw-package framework
 */

function camelCase(value: string): string {
    return value.replace(/-./g, (match) => match.charAt(1).toUpperCase());
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const string = {
    camelCase,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    string,
};
