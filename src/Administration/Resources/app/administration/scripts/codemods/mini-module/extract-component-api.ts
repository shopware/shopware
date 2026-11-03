/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';

/**
 * Everything an Options API component offers to other code, read from its source and template.
 * `unresolved` lists what the codemod cannot read statically, such as `...mapState()` in `computed`,
 * so the sweep after the codemod can complete the lists by hand.
 */
export type ComponentApi = {
    props: string[];
    emits: string[];
    slots: string[];
    blocks: string[];
    computed: string[];
    methods: string[];
    unresolved: string[];
};

const LISTED_OPTIONS = ['props', 'computed', 'methods'] as const;

const getPropertyName = (property: ts.ObjectLiteralElementLike): string | null =>
    property.name && (ts.isIdentifier(property.name) || ts.isStringLiteral(property.name)) ? property.name.text : null;

const findComponentObject = (source: ts.SourceFile): ts.ObjectLiteralExpression | null => {
    const exported = source.statements.find(ts.isExportAssignment)?.expression;
    if (!exported) {
        return null;
    }

    if (ts.isObjectLiteralExpression(exported)) {
        return exported;
    }

    const config = ts.isCallExpression(exported) ? exported.arguments[0] : undefined;
    return config && ts.isObjectLiteralExpression(config) ? config : null;
};

const readNames = (option: string, value: ts.Expression, unresolved: string[]): string[] => {
    if (ts.isArrayLiteralExpression(value)) {
        return value.elements.flatMap((element) => {
            if (ts.isStringLiteral(element)) {
                return [element.text];
            }

            unresolved.push(`${option}: ${element.getText()}`);
            return [];
        });
    }

    if (ts.isObjectLiteralExpression(value)) {
        return value.properties.flatMap((property) => {
            const name = getPropertyName(property);
            if (name) {
                return [name];
            }

            unresolved.push(`${option}: ${property.getText().split('\n')[0]}`);
            return [];
        });
    }

    unresolved.push(`${option}: ${value.getText().split('\n')[0]}`);
    return [];
};

/**
 * Reads the props, emits, computed properties and methods of a component's `export default {}`,
 * also inside `wrapComponentConfig({})`, and the blocks and named slots of its Twig template.
 *
 * @example
 * extractComponentApi("export default { props: { value: String }, methods: { save() {} } }", '{% block sw_example %}{% endblock %}');
 * // → { props: ['value'], emits: [], slots: [], blocks: ['sw_example'], computed: [], methods: ['save'], unresolved: [] }
 */
export function extractComponentApi(componentSource: string, templateSource: string): ComponentApi {
    const source = ts.createSourceFile('component.js', componentSource, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const component = findComponentObject(source);
    const unresolved: string[] = component ? [] : ['the component: no `export default {}` found'];
    const api: ComponentApi = {
        props: [],
        emits: [],
        slots: [],
        blocks: [...new Set([...templateSource.matchAll(/\{%-?\s*block\s+(\w+)/g)].map(([, block]) => block))],
        computed: [],
        methods: [],
        unresolved,
    };

    const slots = [...templateSource.matchAll(/<slot\b([^>]*)>/g)].map(
        ([, attributes]) => /\bname="([^"]+)"/.exec(attributes)?.[1] ?? 'default',
    );
    api.slots = [...new Set(slots)];

    for (const property of component?.properties ?? []) {
        const name = getPropertyName(property);
        if (!ts.isPropertyAssignment(property) || !name) {
            continue;
        }

        if (name === 'emits') {
            api.emits = readNames(name, property.initializer, unresolved);
        } else if ((LISTED_OPTIONS as readonly string[]).includes(name)) {
            api[name as (typeof LISTED_OPTIONS)[number]] = readNames(name, property.initializer, unresolved);
        }
    }

    return api;
}

/**
 * Adds the members of the component a component extends, because Vue merges them in,
 * so an override of the extending component reaches them as well. The component's own members come first.
 *
 * @example
 * mergeComponentApi({ ...empty, methods: ['save'] }, { ...empty, methods: ['save', 'reset'] }).methods;
 * // → ['save', 'reset']
 */
export function mergeComponentApi(own: ComponentApi, base: ComponentApi): ComponentApi {
    const merge = (list: keyof ComponentApi) => [...new Set([...own[list], ...base[list]])];

    return {
        props: merge('props'),
        emits: merge('emits'),
        slots: merge('slots'),
        blocks: merge('blocks'),
        computed: merge('computed'),
        methods: merge('methods'),
        unresolved: merge('unresolved'),
    };
}

/**
 * Reads the `public` lists of a mini-module's `definePublicComponent()` declaration,
 * for a component that extends one that is a mini-module already.
 *
 * @example
 * readDeclaredApi("export default definePublicComponent({ name: 'sw-a', load, public: { methods: ['save'] } });").methods;
 * // → ['save']
 */
export function readDeclaredApi(indexSource: string): ComponentApi {
    const source = ts.createSourceFile('index.ts', indexSource, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const declaration = findComponentObject(source);
    const lists = declaration?.properties.find(
        (property): property is ts.PropertyAssignment =>
            ts.isPropertyAssignment(property) && getPropertyName(property) === 'public',
    )?.initializer;
    const api: ComponentApi = {
        props: [],
        emits: [],
        slots: [],
        blocks: [],
        computed: [],
        methods: [],
        unresolved: [],
    };

    if (!lists || !ts.isObjectLiteralExpression(lists)) {
        return api;
    }

    for (const property of lists.properties) {
        const name = getPropertyName(property) as Exclude<keyof ComponentApi, 'unresolved'> | null;
        if (name && name in api && ts.isPropertyAssignment(property)) {
            api[name] = readNames(name, property.initializer, api.unresolved);
        }
    }

    return api;
}
