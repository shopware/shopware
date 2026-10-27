/**
 * @sw-package framework
 */

import type { ParameterEntry } from '../../snapshot/types';
import { diffComponent, diffObject, method, node, param, signature } from './builders';

const optional = (name: string, type = 'string'): ParameterEntry => param(name, { optional: true, type });
const rest = (name: string): ParameterEntry => param(name, { rest: true, type: 'unknown[]' });

describe('createDiff: component methods', () => {
    it.each([
        [
            'a removed required parameter',
            [param('id'), param('name')],
            [param('id')],
            'breaking',
            ['Required parameter `name` removed.'],
        ],
        [
            'a removed optional parameter',
            [param('id'), optional('name')],
            [param('id')],
            'potentiallyBreaking',
            ['Optional parameter `name` removed.'],
        ],
        [
            'a removed rest parameter',
            [rest('args')],
            [],
            'potentiallyBreaking',
            ['Optional parameter `args` removed.'],
        ],
        [
            'a new required parameter',
            [param('id')],
            [param('id'), param('name')],
            'breaking',
            ['New required parameter `name`.'],
        ],
        [
            'a new optional parameter at the end',
            [param('id')],
            [param('id'), optional('name')],
            'compatible',
            ['New optional parameter `name`.'],
        ],
        [
            'a new rest parameter at the end',
            [param('id')],
            [param('id'), rest('args')],
            'compatible',
            ['New optional parameter `args`.'],
        ],
        [
            'an optional parameter made required',
            [optional('id')],
            [param('id')],
            'breaking',
            ['Parameter `id` is now required.'],
        ],
        [
            'a required parameter made optional',
            [param('id')],
            [optional('id')],
            'compatible',
            ['Parameter `id` is now optional.'],
        ],
        [
            'a renamed parameter',
            [param('id')],
            [param('productId')],
            'compatible',
            ['Parameter `id` renamed to `productId`.'],
        ],
        [
            'a changed parameter type',
            [param('id')],
            [param('id', { type: 'number' })],
            'potentiallyBreaking',
            ['Parameter `id` type changed from `string` to `number`.'],
        ],
        [
            'a parameter turned into a rest parameter',
            [param('ids', { type: 'string[]' })],
            [param('ids', { type: 'string[]', rest: true })],
            'potentiallyBreaking',
            ['Parameter `ids` is now optional.', 'Parameter `ids` is now a rest parameter.'],
        ],
        [
            'a parameter inserted before an existing optional one',
            [param('id'), optional('options', 'object')],
            [param('id'), param('context', { type: 'Context' }), optional('options', 'object')],
            'breaking',
            [
                'Parameter `options` moved from position 2 to 3.',
                'Parameter `options` renamed to `context`.',
                'Parameter `context` is now required.',
                'Parameter `context` type changed from `object` to `Context`.',
                'New optional parameter `options`.',
            ],
        ],
        [
            'an optional parameter inserted before another optional one',
            [param('a'), optional('b')],
            [param('a'), optional('x'), optional('b')],
            'potentiallyBreaking',
            [
                'Parameter `b` moved from position 2 to 3.',
                'Parameter `b` renamed to `x`.',
                'New optional parameter `b`.',
            ],
        ],
        [
            'swapped parameters',
            [param('a'), param('b')],
            [param('b'), param('a')],
            'potentiallyBreaking',
            [
                'Parameter `a` moved from position 1 to 2.',
                'Parameter `a` renamed to `b`.',
                'Parameter `b` moved from position 2 to 1.',
                'Parameter `b` renamed to `a`.',
            ],
        ],
    ] as Array<[string, ParameterEntry[], ParameterEntry[], string, string[]]>)(
        'classifies %s as %s',
        (_, baseParameters, headParameters, severity, reasons) => {
            const changes = diffComponent(
                { methods: [method('save', baseParameters)] },
                { methods: [method('save', headParameters)] },
            );

            expect(changes).toEqual([
                expect.objectContaining({ kind: 'method', member: 'save', change: 'changed', severity, reasons }),
            ]);
        },
    );

    it('keeps reasons short for long types and quotes only the changed part', () => {
        const fields = Array.from({ length: 8 }, (_, index) => `field${index}: string`).join('; ');
        const baseType = `{ ${fields} }`;
        const headType = `{ ${fields}; hideUpdateModule: boolean | null }`;

        const [change] = diffComponent(
            { methods: [method('register', [param('options', { type: baseType })])] },
            { methods: [method('register', [param('options', { type: headType })])] },
        );

        expect(change).toMatchObject({
            severity: 'potentiallyBreaking',
            before: `register(options: ${baseType}): void`,
            after: `register(options: ${headType}): void`,
            reasons: [
                'Parameter `options` type changed. Changed part: `… field7: string }` → `… field7: string; hideUpdateModule: boolean | null }`.',
            ],
        });
    });

    it('cuts a long changed part to 80 characters', () => {
        const [change] = diffComponent(
            { methods: [method('load', [], { returnType: 'A'.repeat(200) })] },
            { methods: [method('load', [], { returnType: 'B'.repeat(200) })] },
        );

        expect(change.reasons).toEqual([
            `Return type changed. Changed part: \`${'A'.repeat(79)}…\` → \`${'B'.repeat(79)}…\`.`,
        ]);
        expect(change.after).toBe(`load(): ${'B'.repeat(200)}`);
    });

    it('classifies a changed return type as potentially breaking', () => {
        const [change] = diffComponent(
            { methods: [method('load', [param('id')], { returnType: 'Promise<void>', async: true })] },
            { methods: [method('load', [param('id')], { returnType: 'Promise<Entity>', async: true })] },
        );

        expect(change).toMatchObject({
            severity: 'potentiallyBreaking',
            before: 'async load(id: string): Promise<void>',
            after: 'async load(id: string): Promise<Entity>',
            reasons: ['Return type changed from `Promise<void>` to `Promise<Entity>`.'],
        });
    });

    it.each([
        [false, true, 'Now async.'],
        [true, false, 'No longer async.'],
    ])('classifies async %s -> %s as potentially breaking', (baseAsync, headAsync, reason) => {
        const [change] = diffComponent(
            { methods: [method('load', [], { async: baseAsync })] },
            { methods: [method('load', [], { async: headAsync })] },
        );

        expect(change).toMatchObject({ severity: 'potentiallyBreaking', reasons: [reason] });
    });

    it('renders rest and optional parameters in before and after', () => {
        const [change] = diffComponent(
            { methods: [method('log', [param('level'), rest('args')])] },
            { methods: [method('log', [param('level'), optional('context', 'object'), rest('args')])] },
        );

        expect(change).toMatchObject({
            before: 'log(level: string, ...args: unknown[]): void',
            after: 'log(level: string, context?: object, ...args: unknown[]): void',
        });
    });
});

describe('createDiff: callable object nodes', () => {
    function callable(path: string, ...callSignatures: ReturnType<typeof signature>[]): ReturnType<typeof node> {
        return node(path, { kind: 'function', signature: 'fn', callSignatures });
    }

    it('applies the parameter rules to call signatures', () => {
        const [change] = diffObject(
            [callable('Shopware.Utils.debounce', signature([param('fn'), optional('wait', 'number')], 'Function'))],
            [callable('Shopware.Utils.debounce', signature([param('fn'), param('wait', { type: 'number' })], 'Function'))],
        );

        expect(change).toMatchObject({
            area: 'shopwareObject',
            kind: 'objectMember',
            owner: 'Shopware',
            member: 'Shopware.Utils.debounce',
            severity: 'breaking',
            reasons: ['Parameter `wait` is now required.'],
        });
    });

    it('classifies a changed return type of a call signature as potentially breaking', () => {
        const [change] = diffObject(
            [callable('Shopware.Utils.get', signature([], 'string'))],
            [callable('Shopware.Utils.get', signature([], 'string | null'))],
        );

        expect(change).toMatchObject({
            severity: 'potentiallyBreaking',
            reasons: ['Return type changed from `string` to `string | null`.'],
        });
    });

    it('compares overloads pairwise in order', () => {
        const [change] = diffObject(
            [callable('Shopware.Utils.get', signature([param('a')]), signature([param('a'), param('b')]))],
            [callable('Shopware.Utils.get', signature([param('a')]), signature([param('a')]))],
        );

        expect(change).toMatchObject({ severity: 'breaking', reasons: ['Overload 2: Required parameter `b` removed.'] });
    });

    it('classifies a changed overload count as potentially breaking', () => {
        const [change] = diffObject(
            [callable('Shopware.Utils.get', signature([param('a')]))],
            [callable('Shopware.Utils.get', signature([param('a')]), signature([param('a'), param('b')]))],
        );

        expect(change).toMatchObject({
            severity: 'potentiallyBreaking',
            reasons: ['Number of call signatures changed from 1 to 2.'],
        });
    });

    it('ignores a changed display signature when the call signatures are equal', () => {
        expect(
            diffObject(
                [
                    node('Shopware.Utils.get', {
                        kind: 'function',
                        signature: '(a: string) => void',
                        callSignatures: [signature([param('a')])],
                    }),
                ],
                [
                    node('Shopware.Utils.get', {
                        kind: 'function',
                        signature: '(a: string)\n => void',
                        callSignatures: [signature([param('a')])],
                    }),
                ],
            ),
        ).toEqual([]);
    });
});
