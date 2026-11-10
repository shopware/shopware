/**
 * @sw-package framework
 *
 * Finds out which Administration component a piece of extension code overrides or extends, so a
 * registry step that is limited to some components only touches their overrides.
 */

const fs = require('fs');
const path = require('path');

const COMPONENT_FACTORY_METHODS = new Set([
    'register',
    'override',
    'extend',
]);

const TS_WRAPPERS = new Set([
    'TSAsExpression',
    'TSSatisfiesExpression',
    'TSNonNullExpression',
    'TSTypeAssertion',
]);

// Option sections whose keys are members of the component, e.g. `computed: { isLoading() {} }`.
const MEMBER_DEFINING_SECTIONS = [
    'props',
    'data',
    'computed',
    'methods',
    'inject',
    'setup',
];

/**
 * The node without TypeScript wrappers such as `as`, `satisfies` and `!`.
 *
 * @example
 * unwrapTypeScript(nodeOf('({ computed: {} } as never)')); // the object expression
 */
function unwrapTypeScript(node) {
    let current = node;

    while (current && TS_WRAPPERS.has(current.type)) {
        current = current.expression;
    }

    return current;
}

/**
 * The dotted path of a member chain like `Shopware.Component`, or null for computed members and calls.
 *
 * @example
 * dottedPath(nodeOf('Shopware.Component.override')); // 'Shopware.Component.override'
 */
function dottedPath(node) {
    if (node?.type === 'Identifier') {
        return node.name;
    }

    if (node?.type === 'ThisExpression') {
        return 'this';
    }

    if (node?.type === 'MemberExpression' && !node.computed && node.property.type === 'Identifier') {
        const objectPath = dottedPath(unwrapTypeScript(node.object));

        return objectPath === null ? null : `${objectPath}.${node.property.name}`;
    }

    return null;
}

/**
 * The value of a string literal or of a template literal without expressions, otherwise null.
 *
 * @example
 * staticString(nodeOf("'sw-order-list'")); // 'sw-order-list'
 */
function staticString(node) {
    if (node?.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node?.type === 'TemplateLiteral' && node.expressions.length === 0) {
        return node.quasis[0].value.cooked;
    }

    return null;
}

/**
 * The static name of a member access or object property, otherwise null.
 *
 * @example
 * propertyName(nodeOf('this.isLoading')); // 'isLoading'
 */
function propertyName(node) {
    if (node?.type === 'MemberExpression') {
        return node.computed ? staticString(node.property) : node.property.name;
    }

    if (node?.type === 'Property' || node?.type === 'MethodDefinition') {
        return node.computed ? staticString(node.key) : (node.key.name ?? staticString(node.key));
    }

    return null;
}

/**
 * For `Component.override('sw-x', …)` and `Component.extend('new', 'sw-x', …)` the name of the
 * changed or extended component, for `Component.register('sw-x', …)` the registered name.
 *
 * @example
 * componentFactoryCall(nodeOf("Shopware.Component.extend('swag-x', 'sw-x', {})")); // { method: 'extend', name: 'sw-x' }
 */
function componentFactoryCall(node) {
    if (node?.type !== 'CallExpression' || node.callee.type !== 'MemberExpression' || node.callee.computed) {
        return null;
    }

    const method = node.callee.property.name;
    const objectPath = dottedPath(node.callee.object);

    if (
        !COMPONENT_FACTORY_METHODS.has(method) ||
        ![
            'Component',
            'Shopware.Component',
        ].includes(objectPath)
    ) {
        return null;
    }

    const name = staticString(method === 'extend' ? node.arguments[1] : node.arguments[0]);

    return name === null ? null : { method, name };
}

/**
 * The innermost `Component.register/override/extend()` call around `node`, or null outside of one.
 *
 * @example
 * enclosingFactoryCall(nodeInside("Component.register('swag-x', { … })")); // { method: 'register', name: 'swag-x' }
 */
function enclosingFactoryCall(node) {
    for (let current = node; current; current = current.parent) {
        const call = componentFactoryCall(current);

        if (call) {
            return call;
        }
    }

    return null;
}

/**
 * The options object of the component around `node`: the object passed to
 * `Component.register/override/extend()`, `wrapComponentConfig()` or `defineComponent()`, or the
 * object a file exports by default, also behind TypeScript `as` or `satisfies`.
 *
 * @example
 * componentOptions(nodeInside("Component.override('sw-x', { computed: { a() {} } })")); // the options object
 */
function componentOptions(node) {
    for (let current = node; current?.parent; current = current.parent) {
        if (current.type !== 'ObjectExpression') {
            continue;
        }

        let parent = current.parent;

        while (parent && TS_WRAPPERS.has(parent.type)) {
            parent = parent.parent;
        }

        if (parent?.type === 'ExportDefaultDeclaration') {
            return current;
        }

        if (parent?.type !== 'CallExpression' || !parent.arguments.some((argument) => unwrapTypeScript(argument) === current)) {
            continue;
        }

        const calleeName = parent.callee.type === 'MemberExpression' ? parent.callee.property.name : parent.callee.name;

        if (
            componentFactoryCall(parent) ||
            [
                'wrapComponentConfig',
                'defineComponent',
            ].includes(calleeName)
        ) {
            return current;
        }
    }

    return null;
}

/**
 * The object literal a `data()`-style function returns, or null.
 */
function returnedObject(node) {
    if (
        ![
            'FunctionExpression',
            'ArrowFunctionExpression',
        ].includes(node?.type)
    ) {
        return null;
    }

    if (node.body.type !== 'BlockStatement') {
        const body = unwrapTypeScript(node.body);

        return body?.type === 'ObjectExpression' ? body : null;
    }

    const returned = node.body.body.find((statement) => statement.type === 'ReturnStatement');
    const argument = unwrapTypeScript(returned?.argument);

    return argument?.type === 'ObjectExpression' ? argument : null;
}

/**
 * The members one option section lists, each with the node that names it: the keys of an object
 * (`computed: { a() {} }`), the strings of an array (`props: ['a']`, `inject: ['a']`) or the keys of
 * the object a function returns (`data() { return { a: 1 }; }`). Watch keys like `'a.b'` name `a`.
 *
 * @example
 * sectionMembers(propertyOf("inject: ['acl', 'repositoryFactory']")); // [{ name: 'acl', node }, { name: 'repositoryFactory', node }]
 */
function sectionMembers(section) {
    const value = unwrapTypeScript(section.value);
    const isWatch = propertyName(section) === 'watch';

    if (value?.type === 'ArrayExpression') {
        return value.elements
            .map((element) => ({ name: staticString(element), node: element }))
            .filter((member) => member.name !== null);
    }

    const object = value?.type === 'ObjectExpression' ? value : returnedObject(value);

    return (object?.properties ?? [])
        .filter((member) => member.type === 'Property')
        .map((member) => {
            const name = propertyName(member);

            return { name: isWatch && name !== null ? name.split('.')[0] : name, node: member };
        })
        .filter((member) => member.name !== null);
}

/**
 * Whether the options object defines `name` in one of its sections, e.g. its own `computed.dateFilter`.
 * `data` counts when it is a function or arrow function that returns an object literal.
 *
 * @example
 * definesMember(optionsOf('{ computed: { dateFilter() {} } }'), ['computed'], 'dateFilter'); // true
 */
function definesMember(options, sections, name) {
    if (!options) {
        return false;
    }

    return options.properties.some((section) => {
        if (section.type !== 'Property' || !sections.includes(propertyName(section))) {
            return false;
        }

        return sectionMembers(section).some((member) => member.name === name);
    });
}

/**
 * Whether `node` is the service: `this.<name>`, `<name>`, `Shopware.Service('<name>')`,
 * `Service('<name>')` or `Shopware.Service().get('<name>')`.
 *
 * @example
 * isService(nodeOf("Shopware.Service('mailService')"), 'mailService'); // true
 */
function isService(node, name) {
    const service = unwrapTypeScript(node);

    if (service?.type === 'Identifier') {
        return service.name === name;
    }

    if (service?.type === 'MemberExpression') {
        return propertyName(service) === name;
    }

    if (service?.type !== 'CallExpression') {
        return false;
    }

    if (
        [
            'Shopware.Service',
            'Service',
        ].includes(dottedPath(service.callee))
    ) {
        return staticString(service.arguments[0]) === name;
    }

    return (
        service.callee.type === 'MemberExpression' &&
        propertyName(service.callee) === 'get' &&
        service.callee.object.type === 'CallExpression' &&
        [
            'Shopware.Service',
            'Service',
        ].includes(dottedPath(service.callee.object.callee)) &&
        service.callee.object.arguments.length === 0 &&
        staticString(service.arguments[0]) === name
    );
}

/**
 * Whether `node` is the Pinia store `Shopware.Store.get('<id>')` or `Store.get('<id>')`.
 *
 * @example
 * isStore(nodeOf("Shopware.Store.get('swProductDetail')"), 'swProductDetail'); // true
 */
function isStore(node, id) {
    const store = unwrapTypeScript(node);

    return (
        store?.type === 'CallExpression' &&
        [
            'Shopware.Store.get',
            'Store.get',
        ].includes(dottedPath(store.callee)) &&
        staticString(store.arguments[0]) === id
    );
}

const OVERRIDE_CALL = /\b(?:Shopware\.)?Component\.(override|extend|register)\(\s*(['"`])([\w-]+)\2(?:\s*,\s*(['"`])([\w-]+)\4)?/g;
const siblingIndexCache = new Map();

// The Administration's own source; its files define the components that extensions override.
const ADMINISTRATION_SOURCE = path.resolve(__dirname, '../../../src');
const administrationSourceCache = new Map();

function realpathOrSelf(filename) {
    try {
        return fs.realpathSync(filename);
    } catch {
        return filename;
    }
}

const ADMINISTRATION_SOURCE_PATHS = [...new Set([
    ADMINISTRATION_SOURCE,
    realpathOrSelf(ADMINISTRATION_SOURCE),
])].map((directory) => `${directory}${path.sep}`);

/**
 * Whether a file belongs to the Administration's own source, not to an extension. Its components
 * are the ones extensions override, so no file in it counts as an override, also when
 * `deprecation:migrate` runs on the Administration itself.
 *
 * @example
 * isAdministrationSource(path.resolve(__dirname, '../../../src/module/sw-order/index.ts')); // true
 */
function isAdministrationSource(filename) {
    if (!administrationSourceCache.has(filename)) {
        const resolved = path.resolve(filename);
        const candidates = [
            resolved,
            path.join(realpathOrSelf(path.dirname(resolved)), path.basename(resolved)),
        ];

        administrationSourceCache.set(
            filename,
            candidates.some((candidate) => ADMINISTRATION_SOURCE_PATHS.some((directory) => candidate.startsWith(directory))),
        );
    }

    return administrationSourceCache.get(filename);
}

/**
 * What the `index.js` or `index.ts` next to a template says about its component: the components it
 * overrides or extends, whether it registers a component of its own (`registers`), and its source;
 * null without an index.
 *
 * @example
 * readSiblingIndex('/ext/src/sw-order-list/sw-order-list.html.twig'); // { overrides: ['sw-order-list'], registers: false, … }
 */
function readSiblingIndex(filename) {
    const directory = path.dirname(filename);

    if (siblingIndexCache.has(directory)) {
        return siblingIndexCache.get(directory);
    }

    const indexFile = [
        'index.js',
        'index.ts',
    ]
        .map((name) => path.join(directory, name))
        .find((candidate) => fs.existsSync(candidate));
    let result = null;

    if (indexFile) {
        const code = fs.readFileSync(indexFile, 'utf8');
        const overrides = [];
        let registers = false;

        for (const match of code.matchAll(OVERRIDE_CALL)) {
            if (match[1] === 'register') {
                registers = true;
            } else {
                overrides.push(match[1] === 'extend' ? match[5] : match[3]);
            }
        }

        result = {
            overrides: overrides.filter(Boolean),
            registers,
            code,
        };
    }

    siblingIndexCache.set(directory, result);

    return result;
}

/**
 * The component a native setup override file overrides: `sw-x.override.vue` and
 * `sw-x/index.override.vue` both override `sw-x`. Null for other files.
 *
 * @example
 * overrideFileComponent('/ext/src/sw-x/index.override.vue'); // 'sw-x'
 */
function overrideFileComponent(filename) {
    const base = path.basename(filename);

    if (!base.endsWith('.override.vue')) {
        return null;
    }

    const name = base.slice(0, -'.override.vue'.length);

    return name === 'index' ? path.basename(path.dirname(filename)) : name;
}

/**
 * The components a template or native override file of an extension belongs to, by the
 * Administration's file conventions:
 * - `sw-x.override.vue` and `sw-x/index.override.vue` override `sw-x`.
 * - A `.html.twig` belongs to what its sibling `index.js`/`index.ts` overrides or extends, so a
 *   template that an override imports under another name counts as well. A sibling index that
 *   registers a component of its own (`Component.register()`) makes the template no override at all.
 *   Otherwise the folder and the base name count, the convention for override folders such as
 *   `sw-order-list/sw-order-list.html.twig`: without a sibling index, or with one that only exports
 *   options, like a lazy override that a parent file registers with
 *   `Component.override('sw-order-list', () => import('./sw-order-list'))`.
 * Files of the Administration's own source belong to no component: they define the components.
 * Other files belong to no component by their name.
 *
 * @example
 * fileComponents('/ext/src/extension/sw-order-list/sw-order-list.html.twig'); // ['sw-order-list']
 */
function fileComponents(filename) {
    if (isAdministrationSource(filename)) {
        return [];
    }

    const overridden = overrideFileComponent(filename);

    if (overridden) {
        return [overridden];
    }

    const base = path.basename(filename);

    if (!base.endsWith('.html.twig')) {
        return [];
    }

    const sibling = readSiblingIndex(filename);

    if (sibling?.overrides.length) {
        return sibling.overrides;
    }

    if (sibling?.registers) {
        return [];
    }

    return [
        ...new Set([
            path.basename(path.dirname(filename)),
            base.slice(0, -'.html.twig'.length),
        ]),
    ];
}

// The file name of a component's options in its folder, which a lazy override imports from another file.
const LAZY_OVERRIDE_INDEX = /^index\.[cm]?[jt]s$/;

/**
 * The components the spot at `node` overrides or extends: the enclosing `Component.override()` or
 * `Component.extend()` call, otherwise the components of the file by its name, see `fileComponents`.
 * Inside `Component.register()` the spot belongs to the registered component itself, so it is in
 * no override at all, and neither is any spot of the Administration's own source. Pass `null` for
 * template spots, which have no script call around them.
 *
 * @example
 * componentsAt(context, thisIsLoadingNode); // ['sw-order-detail']
 */
function componentsAt(context, node) {
    const filename = context.physicalFilename ?? context.filename;

    if (isAdministrationSource(filename)) {
        return [];
    }

    const call = node ? enclosingFactoryCall(node) : null;

    if (call) {
        return call.method === 'register' ? [] : [call.name];
    }

    // A lazy override registers `() => import('./sw-x')` in another file: its `index.js` only exports the options.
    if (node && LAZY_OVERRIDE_INDEX.test(path.basename(filename)) && isInDefaultExport(node)) {
        return readSiblingIndex(filename)?.registers ? [] : [path.basename(path.dirname(filename))];
    }

    return fileComponents(filename);
}

/**
 * Whether `node` sits in the `export default` of its file.
 */
function isInDefaultExport(node) {
    for (let current = node; current; current = current.parent) {
        if (current.type === 'ExportDefaultDeclaration') {
            return true;
        }
    }

    return false;
}

/**
 * Collects the names a binding pattern declares, e.g. `a` and `b` of `const { a, b: [b] } = x`.
 */
function collectPatternNames(pattern, names) {
    const node = unwrapTypeScript(pattern);

    switch (node?.type) {
        case 'Identifier':
            names.add(node.name);
            break;
        case 'ObjectPattern':
            node.properties.forEach((property) => {
                collectPatternNames(property.type === 'RestElement' ? property.argument : property.value, names);
            });
            break;
        case 'ArrayPattern':
            node.elements.forEach((element) => collectPatternNames(element, names));
            break;
        case 'RestElement':
            collectPatternNames(node.argument, names);
            break;
        case 'AssignmentPattern':
            collectPatternNames(node.left, names);
            break;
        default:
            break;
    }
}

/**
 * The props `defineProps()` declares: the strings of an array, the keys of an object, or the
 * members of an inline type literal.
 */
function collectDefinedProps(call, names) {
    if (call?.type !== 'CallExpression' || call.callee.name !== 'defineProps') {
        return;
    }

    const argument = unwrapTypeScript(call.arguments[0]);
    const typeArguments = call.typeArguments ?? call.typeParameters;
    const typeLiteral = typeArguments?.params?.[0];

    if (argument?.type === 'ArrayExpression') {
        argument.elements.map(staticString).filter(Boolean).forEach((name) => names.add(name));
    }

    if (argument?.type === 'ObjectExpression') {
        argument.properties.map(propertyName).filter(Boolean).forEach((name) => names.add(name));
    }

    if (typeLiteral?.type === 'TSTypeLiteral') {
        typeLiteral.members
            .map((member) => member.key?.name ?? staticString(member.key))
            .filter(Boolean)
            .forEach((name) => names.add(name));
    }
}

/**
 * The names the top-level statements of a `<script setup>` block make available to the template.
 */
function collectScriptSetupNames(statement, names) {
    const node = statement.type === 'ExportNamedDeclaration' && statement.declaration ? statement.declaration : statement;

    if (node.type === 'ImportDeclaration' && node.importKind !== 'type') {
        node.specifiers.filter((specifier) => specifier.importKind !== 'type').forEach((specifier) => names.add(specifier.local.name));
    }

    if (
        [
            'FunctionDeclaration',
            'ClassDeclaration',
            'TSEnumDeclaration',
        ].includes(node.type) &&
        node.id
    ) {
        names.add(node.id.name);
    }

    if (node.type === 'VariableDeclaration') {
        node.declarations.forEach((declarator) => {
            collectPatternNames(declarator.id, names);

            const init = unwrapTypeScript(declarator.init);

            collectDefinedProps(init?.callee?.name === 'withDefaults' ? init.arguments[0] : init, names);
        });
    }

    if (node.type === 'ExpressionStatement') {
        const expression = unwrapTypeScript(node.expression);

        collectDefinedProps(expression?.callee?.name === 'withDefaults' ? expression.arguments[0] : expression, names);
    }
}

/**
 * The range of the `<script setup>` block of a `.vue` file, or null.
 */
function scriptSetupRange(sourceCode) {
    const fragment = sourceCode.parserServices?.getDocumentFragment?.();
    const script = fragment?.children.find((child) => {
        return (
            child.type === 'VElement' &&
            child.name === 'script' &&
            child.startTag.attributes.some((attribute) => !attribute.directive && attribute.key.name === 'setup')
        );
    });

    return script ? [script.startTag.range[1], script.endTag?.range[0] ?? script.range[1]] : null;
}

/**
 * The options object a file exports by default, also through `defineComponent()` or
 * `wrapComponentConfig()`, or null.
 */
function exportedOptions(program) {
    const declaration = program.body.find((statement) => statement.type === 'ExportDefaultDeclaration')?.declaration;
    const exported = unwrapTypeScript(declaration);

    if (exported?.type === 'ObjectExpression') {
        return exported;
    }

    const argument = exported?.type === 'CallExpression' ? unwrapTypeScript(exported.arguments[exported.arguments.length - 1]) : null;

    return argument?.type === 'ObjectExpression' ? argument : null;
}

const ownMembersCache = new WeakMap();

/**
 * The names a `.vue` file defines itself and its template can read: the bindings and props of its
 * `<script setup>` block and the members of the options it exports. A template identifier with
 * such a name is the file's own and no member of the component it overrides.
 *
 * @example
 * fileOwnMembers(sourceCode); // Set { 'dateFilter', 'previousState' }
 */
function fileOwnMembers(sourceCode) {
    if (ownMembersCache.has(sourceCode)) {
        return ownMembersCache.get(sourceCode);
    }

    const names = new Set();
    const program = sourceCode.ast;
    const setupRange = scriptSetupRange(sourceCode);

    if (setupRange) {
        program.body
            .filter((statement) => setupRange[0] <= statement.range[0] && statement.range[1] <= setupRange[1])
            .forEach((statement) => collectScriptSetupNames(statement, names));
    }

    const options = exportedOptions(program);

    options?.properties
        .filter((section) => section.type === 'Property' && MEMBER_DEFINING_SECTIONS.includes(propertyName(section)))
        .forEach((section) => sectionMembers(section).forEach((member) => names.add(member.name)));

    ownMembersCache.set(sourceCode, names);

    return names;
}

function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Whether the `index.js`/`index.ts` next to a template defines `name` itself: as a method, computed
 * or data key (`name() {`, `name: …`) or as a string in a `props` or `inject` list. A template of
 * such an override reads its own member. The check reads the source text, so it rather finds a
 * definition too many than misses one.
 *
 * @example
 * siblingIndexDefines('/ext/src/sw-order-list/sw-order-list.html.twig', 'dateFilter'); // true for `dateFilter() { … }`
 */
function siblingIndexDefines(filename, name) {
    const code = readSiblingIndex(filename)?.code;

    if (!code) {
        return false;
    }

    const escaped = escapeRegExp(name);
    const definition = new RegExp(`(?<![\\w$.])${escaped}\\s*(?:\\([^()]*\\)\\s*(?::[^{;]+)?\\{|:)|(['"\`])${escaped}\\1`);

    return definition.test(code);
}

module.exports = {
    componentFactoryCall,
    componentOptions,
    componentsAt,
    definesMember,
    dottedPath,
    fileComponents,
    fileOwnMembers,
    isAdministrationSource,
    isService,
    isStore,
    overrideFileComponent,
    propertyName,
    sectionMembers,
    siblingIndexDefines,
    staticString,
    unwrapTypeScript,
};
