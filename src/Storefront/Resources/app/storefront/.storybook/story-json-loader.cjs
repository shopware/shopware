/**
 * WHY: Storybook discovers JSON story definitions through CSF's JavaScript default and named exports.
 * The preset loader emits dotted keys such as `blocks.content` without quotes, which is invalid JavaScript,
 * so this loader quotes those keys while keeping imported decorators as references.
 */
const { identifier } = require('safe-identifier');

function stringify(value, level = 0, importedIdentifiers = new Set(), isDecoratorValue = false) {
    if (isDecoratorValue && typeof value === 'string' && importedIdentifiers.has(value)) {
        return value;
    }

    if (typeof value === 'string') {
        return JSON.stringify(value);
    }

    if (value === null || typeof value !== 'object') {
        return JSON.stringify(value);
    }

    const indent = '  '.repeat(level);

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }

        return `[` + value.map((item) => stringify(item, level + 1, importedIdentifiers, isDecoratorValue)).join(`,\n${indent}  `) + `\n${indent}]`;
    }

    const entries = Object.entries(value);
    if (entries.length === 0) {
        return '{}';
    }

    return `{\n${entries.map(([key, item]) => `${indent}  ${JSON.stringify(key)}: ${stringify(item, level + 1, importedIdentifiers, key === 'decorators')}`).join(',\n')}\n${indent}}`;
}

module.exports = function storyJsonLoader(source) {
    const section = JSON.parse(source.toString());
    const { title, imports = {}, decorators = [], stories = [], ...options } = section;
    const importedIdentifiers = new Set(Object.values(imports).flat());
    const output = Object.entries(imports)
        .map(([module, names]) => `import { ${names.slice().sort().join(', ')} } from ${JSON.stringify(module)};`)
        .join('\n');
    const defaultExport = `export default ${stringify({ title, ...options, ...(decorators.length > 0 ? { decorators } : {}) }, 0, importedIdentifiers)};`;
    const storyExports = stories.map((story) => {
        const { name, ...storyOptions } = story;

        return `export const ${identifier(name)} = ${stringify({ name, ...storyOptions })};`;
    });

    return [output, defaultExport, ...storyExports].filter(Boolean).join('\n\n');
};
