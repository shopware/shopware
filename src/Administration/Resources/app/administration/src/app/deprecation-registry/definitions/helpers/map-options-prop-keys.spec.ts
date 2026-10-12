/* eslint-disable @typescript-eslint/no-unsafe-member-access -- assertions read fields of untyped ESLint report descriptors */

import { mapOptionsPropKeys } from './map-options-prop-keys';
import { createDirectiveAttribute, createFixer, createRuleApi } from './test-utils';

describe('mapOptionsPropKeys', () => {
    it('renames inline option object keys', () => {
        const usage = mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
        });
        const attribute = createDirectiveAttribute('bind', 'options', {
            expression: {
                type: 'ArrayExpression',
                range: [
                    10,
                    47,
                ],
                elements: [
                    {
                        type: 'ObjectExpression',
                        properties: [
                            {
                                type: 'Property',
                                key: {
                                    type: 'Identifier',
                                    name: 'name',
                                },
                                shorthand: false,
                            },
                            {
                                type: 'Property',
                                key: {
                                    type: 'Identifier',
                                    name: 'id',
                                },
                                shorthand: false,
                            },
                        ],
                    },
                ],
            },
        });
        const api = createRuleApi({
            usage,
            attribute,
            attributeValueSource: `[{ name: 'One', id: 'one' }]`,
        });

        usage.eslint?.report(api);

        expect(api.reports[0].fix(createFixer())).toEqual([
            {
                method: 'replaceText',
                target: attribute.value.expression.elements[0].properties[0].key,
                text: 'label',
            },
            {
                method: 'replaceText',
                target: attribute.value.expression.elements[0].properties[1].key,
                text: 'value',
            },
        ]);
    });

    it('does not report option keys when the element maps them through one of the unless props', () => {
        const usage = mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
            unlessProps: [
                'label-property',
                'value-property',
            ],
        });
        const api = createRuleApi({
            usage,
            attribute: createDirectiveAttribute('bind', 'options', { expression: { type: 'ArrayExpression' } }),
            attributeValueSource: `[{ name: 'One', id: 'one' }]`,
            existingProps: ['label-property'],
        });

        usage.eslint?.report(api);

        expect(api.reports).toHaveLength(0);
    });

    it('reports dynamic options as manual without a fixer', () => {
        const usage = mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
            unsafeMessage: 'Migrate dynamic options manually.',
        });
        const attribute = createDirectiveAttribute('bind', 'options', {
            expression: {
                range: [
                    10,
                    17,
                ],
            },
        });
        const api = createRuleApi({
            usage,
            attribute,
            attributeValueSource: 'options.map(({ name, id }) => ({ name, id }))',
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Migrate dynamic options manually.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });

    it('reports option objects with replacement key conflicts as manual without a fixer', () => {
        const usage = mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
            unsafeMessage: 'Migrate conflicting options manually.',
        });
        const attribute = createDirectiveAttribute('bind', 'options', {
            expression: {
                type: 'ArrayExpression',
                elements: [
                    {
                        type: 'ObjectExpression',
                        properties: [
                            {
                                type: 'Property',
                                key: {
                                    type: 'Identifier',
                                    name: 'name',
                                },
                            },
                            {
                                type: 'Property',
                                key: {
                                    type: 'Identifier',
                                    name: 'label',
                                },
                            },
                        ],
                    },
                ],
            },
        });
        const api = createRuleApi({
            usage,
            attribute,
            attributeValueSource: `[{ name: 'One', label: 'Existing' }]`,
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Migrate conflicting options manually.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });

    it('skips fixes when object v-bind can hide the options prop', () => {
        const usage = mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
        });
        const attribute = createDirectiveAttribute('bind', 'options', {
            expression: {
                type: 'ArrayExpression',
                elements: [
                    {
                        type: 'ObjectExpression',
                        properties: [
                            {
                                type: 'Property',
                                key: {
                                    type: 'Identifier',
                                    name: 'name',
                                },
                            },
                        ],
                    },
                ],
            },
        });
        const objectVBind = createDirectiveAttribute('bind');
        const api = createRuleApi({
            usage,
            attribute,
            attributeValueSource: `[{ name: 'One' }]`,
            node: {
                name: 'mt-test',
                startTag: {
                    range: [
                        0,
                        10,
                    ],
                    loc: {
                        start: {
                            column: 0,
                        },
                    },
                    attributes: [
                        objectVBind,
                        attribute,
                    ],
                },
                children: [],
            },
        });

        usage.eslint?.report(api);

        expect(api.reports[0].message).toContain('Object v-bind can hide the "options" prop.');
        expect(api.reports[0].fix(createFixer())).toBeNull();
    });
});
