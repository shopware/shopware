/**
 * @sw-package framework
 */

import type { ShopwareObjectNode } from '../../snapshot/types';
import { createDiff } from '../create-diff';
import { diffObject, node, param, root, signature, snapshot, summarize } from './builders';

const deprecated = { version: 'v6.8.0', message: '' };

function container(
    path: string,
    children: ShopwareObjectNode[],
    overrides: Partial<ShopwareObjectNode> = {},
): ShopwareObjectNode {
    return node(path, { kind: 'object', signature: `{ ${children.length} members }`, children, ...overrides });
}

describe('createDiff: Shopware object', () => {
    it('reports a removed container as one breaking change without its descendants', () => {
        const changes = diffObject(
            [
                container('Shopware.Data', [
                    node('Shopware.Data.Criteria', { kind: 'class', children: [node('Shopware.Data.Criteria#limit')] }),
                ]),
            ],
            [],
        );

        expect(changes).toEqual([
            expect.objectContaining({
                id: 'shopwareObject:Shopware:objectMember:Shopware.Data',
                area: 'shopwareObject',
                kind: 'objectMember',
                owner: 'Shopware',
                member: 'Shopware.Data',
                change: 'removed',
                severity: 'breaking',
                before: '{ 1 members }',
                after: null,
            }),
        ]);
    });

    it('reports an added container as one change', () => {
        const changes = diffObject([], [container('Shopware.Data', [node('Shopware.Data.version')])]);

        expect(summarize(changes)).toEqual([
            { kind: 'objectMember', member: 'Shopware.Data', change: 'added', severity: 'added' },
        ]);
    });

    it('uses the parent path as owner, including instance members', () => {
        const changes = diffObject(
            [
                node('Shopware.Data.ChangesetGenerator', {
                    kind: 'instance',
                    children: [node('Shopware.Data.ChangesetGenerator#generate', { kind: 'method' })],
                }),
            ],
            [node('Shopware.Data.ChangesetGenerator', { kind: 'instance', children: [] })],
        );

        expect(changes).toEqual([
            expect.objectContaining({
                owner: 'Shopware.Data.ChangesetGenerator',
                member: 'Shopware.Data.ChangesetGenerator#generate',
                severity: 'breaking',
            }),
        ]);
    });

    it('classifies a changed kind as breaking', () => {
        const [change] = diffObject(
            [node('Shopware.Context.app', { kind: 'property' })],
            [node('Shopware.Context.app', { kind: 'getter' })],
        );

        expect(change).toMatchObject({
            severity: 'breaking',
            before: 'property: string',
            after: 'getter: string',
            reasons: ['Kind changed from property to getter.'],
        });
    });

    it('classifies a changed leaf signature as potentially breaking', () => {
        const [change] = diffObject(
            [node('Shopware.Feature.flags', { kind: 'property', signature: 'Record<string, boolean>' })],
            [node('Shopware.Feature.flags', { kind: 'property', signature: 'Map<string, boolean>' })],
        );

        expect(change).toMatchObject({
            severity: 'potentiallyBreaking',
            before: 'Record<string, boolean>',
            after: 'Map<string, boolean>',
            reasons: ['Type changed.'],
        });
    });

    it('ignores summary signature changes of containers', () => {
        const changes = diffObject(
            [
                container('Shopware.Utils', [node('Shopware.Utils.a')]),
                node('Shopware.Http', { kind: 'class', signature: 'class Http' }),
            ],
            [
                container('Shopware.Utils', [node('Shopware.Utils.a'), node('Shopware.Utils.b')]),
                node('Shopware.Http', { kind: 'class', signature: 'class HttpClient' }),
            ],
        );

        expect(summarize(changes)).toEqual([
            { kind: 'objectMember', member: 'Shopware.Utils.b', change: 'added', severity: 'added' },
        ]);
    });

    it('compares constructor signatures of classes', () => {
        const changes = diffObject(
            [
                node('Shopware.Data.Criteria', {
                    kind: 'class',
                    callSignatures: [signature([param('page', { optional: true })])],
                }),
            ],
            [node('Shopware.Data.Criteria', { kind: 'class', callSignatures: [signature([param('page')])] })],
        );

        expect(summarize(changes)).toEqual([
            { kind: 'objectMember', member: 'Shopware.Data.Criteria', change: 'changed', severity: 'breaking' },
        ]);
    });

    it('classifies removed deprecated and non-public nodes', () => {
        const changes = diffObject(
            [node('Shopware.legacy', { deprecation: deprecated }), node('Shopware.internal', { visibility: 'internal' })],
            [],
        );

        expect(summarize(changes)).toEqual([
            { kind: 'objectMember', member: 'Shopware.legacy', change: 'removed', severity: 'removedDeprecated' },
            { kind: 'objectMember', member: 'Shopware.internal', change: 'removed', severity: 'private' },
        ]);
    });

    it('classifies removing a node below a deprecated ancestor as removedDeprecated', () => {
        const state = (children: ShopwareObjectNode[]): ShopwareObjectNode =>
            container('Shopware.State', [container('Shopware.State.Store', children)], { deprecation: deprecated });
        const changes = diffObject([state([node('Shopware.State.Store.get', { kind: 'method' })])], [state([])]);

        expect(changes).toEqual([
            expect.objectContaining({
                member: 'Shopware.State.Store.get',
                change: 'removed',
                severity: 'removedDeprecated',
                reasons: ['Removed; `Shopware.State` is deprecated since v6.8.0.'],
            }),
        ]);
    });

    it('reports a deprecation or visibility change only where it starts, not on inheriting descendants', () => {
        const children = [node('Shopware.Utils.a'), node('Shopware.Utils.b')];
        const changes = diffObject(
            [container('Shopware.Utils', children), container('Shopware.Hidden', [node('Shopware.Hidden.a')])],
            [
                container(
                    'Shopware.Utils',
                    children.map((child) => ({ ...child, deprecation: deprecated })),
                    { deprecation: deprecated },
                ),
                container('Shopware.Hidden', [node('Shopware.Hidden.a', { visibility: 'private' })], {
                    visibility: 'private',
                }),
            ],
        );

        expect(summarize(changes)).toEqual([
            { kind: 'objectMember', member: 'Shopware.Hidden', change: 'changed', severity: 'potentiallyBreaking' },
            { kind: 'objectMember', member: 'Shopware.Utils', change: 'deprecated', severity: 'newlyDeprecated' },
        ]);
    });

    it('classifies changes below a private node as private', () => {
        const changes = diffObject(
            [
                container('Shopware.Internal', [node('Shopware.Internal.a', { visibility: 'internal' })], {
                    visibility: 'internal',
                }),
            ],
            [
                container('Shopware.Internal', [node('Shopware.Internal.a', { visibility: 'internal', kind: 'property' })], {
                    visibility: 'internal',
                }),
            ],
        );

        expect(changes).toEqual([
            expect.objectContaining({
                member: 'Shopware.Internal.a',
                severity: 'private',
                reasons: [
                    'Not public; it is @internal.',
                    'Kind changed from constant to property.',
                ],
            }),
        ]);
    });

    it('does not compare the members of a node that was truncated on one side only', () => {
        const diff = createDiff(
            snapshot({
                shopwareObject: root([container('Shopware.Data', [node('Shopware.Data.a'), node('Shopware.Data.b')])]),
            }),
            snapshot({ shopwareObject: root([container('Shopware.Data', [], { truncated: true })]) }),
        );

        expect(diff.changes).toEqual([]);
        expect(diff.head.diagnostics).toEqual([
            { file: null, message: 'The walk was truncated at Shopware.Data; its members were not compared.' },
        ]);
    });
});
