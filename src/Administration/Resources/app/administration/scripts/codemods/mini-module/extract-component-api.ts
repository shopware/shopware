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
