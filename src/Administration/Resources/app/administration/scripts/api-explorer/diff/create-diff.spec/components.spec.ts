/**
 * @sw-package framework
 */

import type { ComponentEntry } from '../../snapshot/types';
import {
    block,
    component,
    computed,
    diffComponent,
    diffComponents,
    method,
    named,
    prop,
    summarize,
    typed,
} from './builders';

describe('createDiff: components', () => {
    it('reports a removed component as one breaking change without its members', () => {
        const changes = diffComponents(
            [component('sw-foo', { props: [prop('value')], methods: [method('save')], blocks: [block('sw_foo')] })],
            [],
        );

        expect(changes).toEqual([
            {
                id: 'component:sw-foo:component:sw-foo',
                area: 'component',
                kind: 'component',
                change: 'removed',
                severity: 'breaking',
                owner: 'sw-foo',
                member: 'sw-foo',
                before: 'register',
                after: null,
                reasons: ['Removed without prior deprecation.'],
                location: { file: 'src/app/component/sw-foo/index.ts', line: 1 },
                impact: null,
                privateVisibility: null,
            },
        ]);
    });

    it('reports an added component as one change, even with required props', () => {
        const changes = diffComponents([], [component('sw-foo', { props: [prop('value', { required: true })] })]);

        expect(summarize(changes)).toEqual([{ kind: 'component', member: 'sw-foo', change: 'added', severity: 'added' }]);
    });

    it.each([
        [
            { registration: 'register', extends: null },
            { registration: 'extend', extends: 'sw-base' },
            'register',
            'extend extends sw-base',
            [
                'Registration changed from register to extend.',
                'Parent component changed from `none` to `sw-base`.',
            ],
        ],
        [
            { registration: 'extend', extends: 'sw-base' },
            { registration: 'extend', extends: 'sw-other' },
            'extend extends sw-base',
            'extend extends sw-other',
            ['Parent component changed from `sw-base` to `sw-other`.'],
        ],
    ] as Array<[Partial<ComponentEntry>, Partial<ComponentEntry>, string, string, string[]]>)(
        'classifies a changed registration or parent as potentially breaking',
        (base, head, before, after, reasons) => {
            expect(diffComponent(base, head)).toEqual([
                expect.objectContaining({
                    kind: 'component',
                    owner: 'sw-foo',
                    member: 'sw-foo',
                    change: 'changed',
                    severity: 'potentiallyBreaking',
                    before,
                    after,
                    reasons,
                }),
            ]);
        },
    );

    it('ignores fields that are not part of the extension API', () => {
        const changes = diffComponent(
            {},
            {
                overriddenBy: ['src/app/override.ts'],
                packageName: 'inventory',
                language: 'vue',
                sourceFile: 'src/app/moved/index.vue',
                templateFile: 'src/app/moved/index.html.twig',
                location: { file: 'src/app/moved/index.vue', line: 10 },
            },
        );

        expect(changes).toEqual([]);
    });
});

describe('createDiff: props', () => {
    it('classifies a removed prop as breaking and an added optional prop as added', () => {
        const changes = diffComponent({ props: [prop('old')] }, { props: [prop('new')] });

        expect(changes).toEqual([
            expect.objectContaining({
                member: 'old',
                change: 'removed',
                severity: 'breaking',
                before: 'String',
                after: null,
                reasons: ['Removed without prior deprecation.'],
            }),
            expect.objectContaining({
                member: 'new',
                change: 'added',
                severity: 'added',
                before: null,
                after: 'String',
                reasons: ['Added.'],
            }),
        ]);
    });

    it('classifies a new required prop on an existing component as breaking', () => {
        const [change] = diffComponent({}, { props: [prop('value', { required: true })] });

        expect(change).toMatchObject({
            change: 'added',
            severity: 'breaking',
            after: 'String, required',
            reasons: ['New required prop.'],
        });
    });

    it.each([
        [
            prop('value'),
            prop('value', { required: true }),
            'breaking',
            'Now required.',
        ],
        [
            prop('value', { required: true }),
            prop('value'),
            'compatible',
            'No longer required.',
        ],
        [
            prop('value'),
            prop('value', { type: 'String | Number' }),
            'potentiallyBreaking',
            'Type changed from `String` to `String | Number`.',
        ],
        [
            prop('value', { type: null }),
            prop('value'),
            'potentiallyBreaking',
            'Type changed from `none` to `String`.',
        ],
        [
            prop('value', { default: "'a'" }),
            prop('value', { default: "'b'" }),
            'potentiallyBreaking',
            "Default changed from `'a'` to `'b'`.",
        ],
        [
            prop('value', { default: "'a'" }),
            prop('value'),
            'potentiallyBreaking',
            "Default changed from `'a'` to `none`.",
        ],
    ])('classifies %j -> %j as %s', (base, head, severity, reason) => {
        expect(diffComponent({ props: [base] }, { props: [head] })).toEqual([
            expect.objectContaining({ kind: 'prop', change: 'changed', severity, reasons: [reason] }),
        ]);
    });

    it('does not quote long prop types in the reason', () => {
        const baseType = `{ ${'a: string; '.repeat(10)}}`;
        const headType = `{ ${'a: string; '.repeat(10)}b: number; }`;
        const [change] = diffComponent(
            { props: [prop('config', { type: baseType })] },
            { props: [prop('config', { type: headType })] },
        );

        expect(change.reasons).toEqual([
            'Type changed. Changed part: `…ng; a: string; }` → `…ng; a: string; b: number; }`.',
        ]);
        expect(change.after).toBe(headType);
    });

    it('ignores whitespace differences in the default', () => {
        expect(
            diffComponent(
                { props: [prop('value', { default: '() => ({ a: 1 })' })] },
                { props: [prop('value', { default: '() =>  ({\n a: 1\n})' })] },
            ),
        ).toEqual([]);
    });
});

describe('createDiff: computed and data', () => {
    it.each([
        [
            computed('total', { writable: true }),
            computed('total'),
            'breaking',
            'No longer writable.',
            'string (writable)',
            'string (read-only)',
        ],
        [
            computed('total'),
            computed('total', { writable: true }),
            'compatible',
            'Now writable.',
            'string (read-only)',
            'string (writable)',
        ],
        [
            computed('total'),
            computed('total', { type: 'number' }),
            'potentiallyBreaking',
            'Type changed from `string` to `number`.',
            'string (read-only)',
            'number (read-only)',
        ],
    ])('classifies computed %j -> %j as %s', (base, head, severity, reason, before, after) => {
        expect(diffComponent({ computed: [base] }, { computed: [head] })).toEqual([
            expect.objectContaining({ kind: 'computed', change: 'changed', severity, reasons: [reason], before, after }),
        ]);
    });

    it('classifies a changed data type as potentially breaking', () => {
        const [change] = diffComponent(
            { data: [typed('items', 'string[]')] },
            { data: [typed('items', 'EntityCollection<"product">')] },
        );

        expect(change).toMatchObject({
            kind: 'data',
            severity: 'potentiallyBreaking',
            before: 'string[]',
            after: 'EntityCollection<"product">',
        });
    });

    it('ignores a changed type source when the type text is the same', () => {
        expect(
            diffComponent(
                { data: [typed('items', 'string[]', { typeSource: 'inferred' })] },
                { data: [typed('items', 'string[]')] },
            ),
        ).toEqual([]);
    });

    it('classifies removed computed and data as breaking', () => {
        const changes = diffComponent({ computed: [computed('total')], data: [typed('items', 'string[]')] }, {});

        expect(summarize(changes)).toEqual([
            { kind: 'data', member: 'items', change: 'removed', severity: 'breaking' },
            { kind: 'computed', member: 'total', change: 'removed', severity: 'breaking' },
        ]);
    });
});

describe('createDiff: emits, slots, inject and mixins', () => {
    it('reports removals as breaking and additions as added', () => {
        const changes = diffComponent(
            { emits: [named('change')], slots: [named('default')], inject: [named('acl')], mixins: [named('notification')] },
            {
                emits: [named('update')],
                slots: [named('footer')],
                inject: [named('repositoryFactory')],
                mixins: [named('placeholder')],
            },
        );

        expect(summarize(changes)).toEqual([
            { kind: 'inject', member: 'acl', change: 'removed', severity: 'breaking' },
            { kind: 'emit', member: 'change', change: 'removed', severity: 'breaking' },
            { kind: 'slot', member: 'default', change: 'removed', severity: 'breaking' },
            { kind: 'mixin', member: 'notification', change: 'removed', severity: 'breaking' },
            { kind: 'slot', member: 'footer', change: 'added', severity: 'added' },
            { kind: 'mixin', member: 'placeholder', change: 'added', severity: 'added' },
            { kind: 'inject', member: 'repositoryFactory', change: 'added', severity: 'added' },
            { kind: 'emit', member: 'update', change: 'added', severity: 'added' },
        ]);
    });

    it('matches members within their kind only', () => {
        const changes = diffComponent({ emits: [named('value')] }, { slots: [named('value')] });

        expect(summarize(changes)).toEqual([
            { kind: 'emit', member: 'value', change: 'removed', severity: 'breaking' },
            { kind: 'slot', member: 'value', change: 'added', severity: 'added' },
        ]);
    });
});
