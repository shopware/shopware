/**
 * @sw-package framework
 */

import { createDiff } from '../create-diff';
import {
    block,
    component,
    diffComponent,
    method,
    named,
    node,
    param,
    prop,
    root,
    signature,
    snapshot,
    summarize,
    typed,
} from './builders';

function richSnapshot(): ReturnType<typeof snapshot> {
    return snapshot({
        components: [
            component('sw-a', {
                props: [prop('value', { required: true })],
                emits: [named('update:value')],
                slots: [named('default')],
                data: [typed('isLoading', 'boolean')],
                methods: [method('save', [param('id')], { returnType: 'Promise<void>', async: true })],
                blocks: [
                    block('sw_a'),
                    block('sw_a_content', { key: 'sw_a/sw_a_content', parent: 'sw_a', depth: 1 }),
                ],
            }),
            component('sw-b', { registration: 'extend', extends: 'sw-a' }),
        ],
        shopwareObject: root([
            node('Shopware.Component', {
                kind: 'object',
                children: [
                    node('Shopware.Component.register', {
                        kind: 'method',
                        callSignatures: [signature([param('name')])],
                    }),
                ],
            }),
        ]),
    });
}

describe('createDiff', () => {
    it('reports no changes for identical snapshots', () => {
        const diff = createDiff(richSnapshot(), richSnapshot());

        expect(diff.changes).toEqual([]);
        expect(diff.summary).toEqual({
            total: 0,
            bySeverity: {
                breaking: 0,
                potentiallyBreaking: 0,
                compatible: 0,
                added: 0,
                newlyDeprecated: 0,
                removedDeprecated: 0,
                private: 0,
            },
            hasBreaking: false,
            privateBySeverity: {
                breaking: 0,
                potentiallyBreaking: 0,
                compatible: 0,
                added: 0,
                newlyDeprecated: 0,
                removedDeprecated: 0,
            },
        });
    });

    it('ignores whitespace-only differences in type text', () => {
        const changes = diffComponent(
            {
                methods: [
                    method('load', [param('ids', { type: 'string |  number' })], { returnType: '{ a: string; b: number }' }),
                ],
            },
            {
                methods: [
                    method('load', [param('ids', { type: 'string | number' })], {
                        returnType: '{\n    a: string;\n    b: number\n}',
                    }),
                ],
            },
        );

        expect(changes).toEqual([]);
    });

    it('copies the snapshot metadata and diagnostics into the refs', () => {
        const base = snapshot({
            ref: 'trunk',
            commit: 'abc123',
            createdAt: '2026-10-01T00:00:00.000Z',
            extractorVersion: '1',
            diagnostics: [{ file: 'src/app/foo.ts', message: 'Could not parse.' }],
        });
        const head = snapshot({ extractorVersion: '2' });

        const diff = createDiff(base, head);

        expect(diff.base).toEqual({
            ref: 'trunk',
            commit: 'abc123',
            createdAt: '2026-10-01T00:00:00.000Z',
            extractorVersion: '1',
            diagnostics: [{ file: 'src/app/foo.ts', message: 'Could not parse.' }],
        });
        expect(diff.head).toEqual({
            ref: 'WORKTREE',
            commit: null,
            createdAt: '2026-10-21T00:00:00.000Z',
            extractorVersion: '2',
            diagnostics: [],
        });
    });

    it('sorts by severity, then owner, then member, and counts the summary', () => {
        const base = snapshot({
            components: [
                component('sw-b', { props: [prop('z'), prop('a')] }),
                component('sw-a', { props: [prop('m')], emits: [named('change')] }),
            ],
        });
        const head = snapshot({
            components: [
                component('sw-b', { props: [prop('a', { type: 'Number' })], emits: [named('new')] }),
                component('sw-a', { props: [prop('m', { deprecation: { version: 'v6.8.0', message: '' } })] }),
            ],
        });

        const diff = createDiff(base, head);

        expect(diff.changes.map((change) => change.id)).toEqual([
            'component:sw-a:emit:change',
            'component:sw-b:prop:z',
            'component:sw-b:prop:a',
            'component:sw-b:emit:new',
            'component:sw-a:prop:m',
        ]);
        expect(diff.summary).toEqual({
            total: 5,
            bySeverity: {
                breaking: 2,
                potentiallyBreaking: 1,
                compatible: 0,
                added: 1,
                newlyDeprecated: 1,
                removedDeprecated: 0,
                private: 0,
            },
            hasBreaking: true,
            privateBySeverity: {
                breaking: 0,
                potentiallyBreaking: 0,
                compatible: 0,
                added: 0,
                newlyDeprecated: 0,
                removedDeprecated: 0,
            },
        });
    });

    it('produces the same output regardless of the input order', () => {
        const components = [
            component('sw-a', { props: [prop('a'), prop('b')] }),
            component('sw-b', { emits: [named('x'), named('y')] }),
        ];
        const head = snapshot({ components: [component('sw-b'), component('sw-a')] });

        const forward = createDiff(snapshot({ components }), head);
        const reversed = createDiff(
            snapshot({
                components: [...components].reverse().map((entry) => ({
                    ...entry,
                    props: [...entry.props].reverse(),
                    emits: [...entry.emits].reverse(),
                })),
            }),
            head,
        );

        expect(forward.changes).toHaveLength(4);
        expect(JSON.stringify(reversed)).toBe(JSON.stringify(forward));
    });

    it('fills id, owner, before, after and location', () => {
        const [change] = createDiff(
            snapshot({
                components: [
                    component('sw-foo', {
                        props: [prop('size', { location: { file: 'base.ts', line: 3 } })],
                    }),
                ],
            }),
            snapshot({
                components: [
                    component('sw-foo', {
                        props: [prop('size', { type: 'Number', default: '5', location: { file: 'head.ts', line: 7 } })],
                    }),
                ],
            }),
        ).changes;

        expect(change).toEqual({
            id: 'component:sw-foo:prop:size',
            area: 'component',
            kind: 'prop',
            change: 'changed',
            severity: 'potentiallyBreaking',
            owner: 'sw-foo',
            member: 'size',
            before: 'String',
            after: 'Number, default: 5',
            reasons: [
                'Type changed from `String` to `Number`.',
                'Default changed from `none` to `5`.',
            ],
            location: { file: 'head.ts', line: 7 },
            impact: null,
            privateVisibility: null,
        });
    });

    it('falls back to the base location when the head has none', () => {
        const [change] = diffComponent(
            { emits: [named('change', { location: { file: 'base.ts', line: 3 } })] },
            { emits: [named('change', { deprecation: { version: null, message: '' } })] },
        );

        expect(change.location).toEqual({ file: 'base.ts', line: 3 });
    });

    it('emits one change per entry with the highest severity and all reasons', () => {
        const changes = diffComponent(
            { props: [prop('value', { type: 'String' })] },
            {
                props: [
                    prop('value', {
                        type: 'Number',
                        required: true,
                        deprecation: { version: 'v6.8.0', message: 'Use modelValue.' },
                    }),
                ],
            },
        );

        expect(changes).toHaveLength(1);
        expect(changes[0]).toMatchObject({
            change: 'changed',
            severity: 'breaking',
            reasons: [
                'Type changed from `String` to `Number`.',
                'Now required.',
                'Newly deprecated since v6.8.0: Use modelValue.',
            ],
        });
    });

    it('merges duplicate entries of the same member into one change', () => {
        const changes = diffComponent({ emits: [named('change'), named('change')] }, {});

        expect(summarize(changes)).toEqual([{ kind: 'emit', member: 'change', change: 'removed', severity: 'breaking' }]);
    });

    describe('partial snapshots', () => {
        it('skips the components when one side has none', () => {
            const diff = createDiff(
                snapshot({ components: [component('sw-a'), component('sw-b')] }),
                snapshot({ components: [] }),
            );

            expect(diff.changes).toEqual([]);
            expect(diff.base.diagnostics).toEqual([]);
            expect(diff.head.diagnostics).toEqual([
                { file: null, message: 'No components were extracted, so components were not compared.' },
            ]);
        });

        it('skips the components when the base has none', () => {
            const diff = createDiff(snapshot({ components: [] }), snapshot({ components: [component('sw-a')] }));

            expect(diff.changes).toEqual([]);
            expect(diff.base.diagnostics).toHaveLength(1);
            expect(diff.head.diagnostics).toEqual([]);
        });

        it('skips the Shopware object when one root has no children and keeps the snapshot diagnostics', () => {
            const diff = createDiff(
                snapshot({ shopwareObject: root([node('Shopware.version'), node('Shopware.Component')]) }),
                snapshot({
                    shopwareObject: root([]),
                    diagnostics: [{ file: 'src/core/shopware.ts', message: 'Extraction failed.' }],
                }),
            );

            expect(diff.changes).toEqual([]);
            expect(diff.head.diagnostics).toEqual([
                { file: 'src/core/shopware.ts', message: 'Extraction failed.' },
                { file: null, message: 'The Shopware object has no members, so the Shopware object was not compared.' },
            ]);
        });

        it('still diffs components when only the Shopware object is missing', () => {
            const diff = createDiff(
                snapshot({ components: [component('sw-a')] }),
                snapshot({ components: [component('sw-b')], shopwareObject: root([]) }),
            );

            expect(summarize(diff.changes)).toEqual([
                { kind: 'component', member: 'sw-a', change: 'removed', severity: 'breaking' },
                { kind: 'component', member: 'sw-b', change: 'added', severity: 'added' },
            ]);
        });

        it('compares two empty snapshots without diagnostics', () => {
            const empty = snapshot({ components: [], shopwareObject: root([]) });
            const diff = createDiff(empty, empty);

            expect(diff.changes).toEqual([]);
            expect(diff.base.diagnostics).toEqual([]);
            expect(diff.head.diagnostics).toEqual([]);
        });
    });
});
