/**
 * @sw-package framework
 */

const path = require('path');

const COMPONENT_FACTORIES = new Set([
    'register',
    'wrapComponentConfig',
    'defineComponent',
]);

const COMPONENT_CODEMOD_KINDS = new Set([
    'rename-component',
]);

const PROP_CODEMOD_KINDS = new Set([
    'rename-prop',
    'invert-boolean-prop',
    'boolean-prop-to-prop-value',
    'remove-prop',
    'replace-prop',
    'replace-prop-value',
    'rename-array-prop-keys',
]);

/**
 * Normalizes kebab-case and camelCase prop names to one comparable form.
 * Use it whenever a template attribute name meets a prop definition key.
 *
 * @example
 * toPropName('partly-checked'); // 'partlyChecked'
 */
function toPropName(name) {
    return name.replace(/-([a-z])/g, (_match, letter) => letter.toUpperCase());
}

function getKeyName(property) {
    if (property.type !== 'Property' || property.computed) {
        return null;
    }

    if (property.key.type === 'Identifier') {
        return property.key.name;
    }

    return typeof property.key.value === 'string' ? property.key.value : null;
}

function findProperty(objectExpression, name) {
    return objectExpression.properties.find((property) => getKeyName(property) === name) ?? null;
}

function getStringLiteral(node) {
    return node?.type === 'Literal' && typeof node.value === 'string' ? node.value : null;
}

function getCalleeName(callee) {
    if (callee.type === 'Identifier') {
        return callee.name;
    }

    if (callee.type === 'MemberExpression' && !callee.computed) {
        return callee.property.name;
    }

    return null;
}

function getNameFromFilename(filename) {
    const basename = path.basename(filename).replace(/\.(js|ts|vue)$/, '');

    return basename === 'index' ? path.basename(path.dirname(filename)) : basename;
}

function getOptionsName(options, filename) {
    const nameProperty = findProperty(options, 'name');

    return getStringLiteral(nameProperty?.value) ?? getNameFromFilename(filename);
}

function toComponentDefinition(node, statement, filename) {
    if (node.type === 'ExportDefaultDeclaration' && node.declaration.type === 'ObjectExpression') {
        return {
            name: getOptionsName(node.declaration, filename),
            options: node.declaration,
            statement,
        };
    }

    if (node.type !== 'CallExpression' || !COMPONENT_FACTORIES.has(getCalleeName(node.callee))) {
        return null;
    }

    if (getCalleeName(node.callee) === 'register') {
        const [nameArgument, optionsArgument] = node.arguments;
        const name = getStringLiteral(nameArgument);

        return name && optionsArgument?.type === 'ObjectExpression' ? { name, options: optionsArgument, statement } : null;
    }

    const [optionsArgument] = node.arguments;

    if (optionsArgument?.type !== 'ObjectExpression') {
        return null;
    }

    return {
        name: getOptionsName(optionsArgument, filename),
        options: optionsArgument,
        statement,
    };
}

function visit(node, callback) {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    callback(node);

    Object.entries(node).forEach(([key, child]) => {
        if (key === 'parent') {
            return;
        }

        (Array.isArray(child) ? child : [child]).forEach((item) => {
            if (item && typeof item === 'object') {
                visit(item, callback);
            }
        });
    });
}

/**
 * Lists the components a file defines through `Component.register`, `wrapComponentConfig`,
 * `defineComponent` or an exported options object. Unnamed options take the component
 * directory's name, which matches how the Administration registers its components.
 *
 * @example
 * findComponentDefinitions(ast, 'src/app/component/base/sw-alert/index.ts');
 * // [{ name: 'sw-alert', options: <ObjectExpression>, statement: <ExportDefaultDeclaration> }]
 */
function findComponentDefinitions(program, filename) {
    const definitions = [];

    program.body.forEach((statement) => {
        visit(statement, (node) => {
            const definition = toComponentDefinition(node, statement, filename);

            if (definition) {
                definitions.push(definition);
            }
        });
    });

    return definitions;
}

/**
 * Returns the `deprecated` option of a component options object or a prop definition,
 * the marker trunk's deprecation plugin turns into a runtime warning.
 *
 * @example
 * getDeprecatedOption(options); // <Property deprecated: { version: 'v6.8.0.0' }> or null
 */
function getDeprecatedOption(objectExpression) {
    return findProperty(objectExpression, 'deprecated');
}

/**
 * Lists the object-style prop definitions of a component options object.
 * Array-style props carry no options, so they cannot be marked deprecated and are skipped.
 *
 * @example
 * getPropDefinitions(options); // [{ name: 'items', property: <Property>, definition: <ObjectExpression> }]
 */
function getPropDefinitions(options) {
    const props = findProperty(options, 'props')?.value;

    if (props?.type !== 'ObjectExpression') {
        return [];
    }

    return props.properties
        .filter((property) => getKeyName(property) && property.value.type === 'ObjectExpression')
        .map((property) => ({
            name: getKeyName(property),
            property,
            definition: property.value,
        }));
}

/**
 * Lists the prop names a component migration has codemods for, normalized to camelCase.
 *
 * @example
 * getPropCodemodNames(entityListingMigration); // ['items']
 */
function getPropCodemodNames(migration) {
    return migration.usage
        .filter((usage) => PROP_CODEMOD_KINDS.has(usage.kind))
        .map((usage) => usage.prop ?? usage.from ?? usage.fromProp)
        .filter((propName) => typeof propName === 'string')
        .map(toPropName);
}

/**
 * Tells whether the registry replaces a component as a whole, automatically or with a manual hint.
 *
 * @example
 * hasComponentCodemod(registry.componentApiMigrations, 'sw-alert'); // true
 */
function hasComponentCodemod(componentMigrations, componentName) {
    return componentMigrations.some((migration) => {
        return (
            migration.component === componentName && migration.usage.some((usage) => COMPONENT_CODEMOD_KINDS.has(usage.kind))
        );
    });
}

/**
 * Tells whether the registry holds a codemod for one prop of a component.
 *
 * @example
 * hasPropCodemod(registry.componentApiMigrations, 'sw-entity-listing', 'items'); // true
 */
function hasPropCodemod(componentMigrations, componentName, propName) {
    return componentMigrations.some((migration) => {
        return migration.component === componentName && getPropCodemodNames(migration).includes(toPropName(propName));
    });
}

module.exports = {
    findComponentDefinitions,
    findProperty,
    getDeprecatedOption,
    getPropCodemodNames,
    getPropDefinitions,
    getStringLiteral,
    hasComponentCodemod,
    hasPropCodemod,
    toPropName,
};
