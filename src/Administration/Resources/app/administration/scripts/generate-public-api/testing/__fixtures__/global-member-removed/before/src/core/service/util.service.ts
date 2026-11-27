/**
 * @sw-package framework
 */

function camelCase(value: string): string {
    return value.replace(/-./g, (match) => match.charAt(1).toUpperCase());
}

function kebabCase(value: string): string {
    return value.replace(/[A-Z]/g, (match) => `-${match.toLowerCase()}`);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const string = {
    camelCase,
    kebabCase,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    string,
};
