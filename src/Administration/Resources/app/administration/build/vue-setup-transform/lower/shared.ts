/**
 * @sw-package framework
 */

import { RESERVED_BINDING_PREFIX } from '../naming';

const RUNTIME = `${RESERVED_BINDING_PREFIX}Runtime`;

function quote(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/** `__proto__: value` would set the prototype instead of an own property. */
function propertyKey(name: string): string {
    return name === '__proto__' ? '["__proto__"]' : name;
}

function formatObjectProperties(properties: string[], indent: number): string {
    if (properties.length === 0) {
        return '{}';
    }

    const lines = properties.map((property) => `${' '.repeat(indent)}${property},`);

    return `{\n${lines.join('\n')}\n${' '.repeat(indent - 4)}}`;
}

/**
 * @private
 */
export { RUNTIME, formatObjectProperties, propertyKey, quote };
