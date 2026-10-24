/**
 * @sw-package framework
 */

import { change, compareResponse, objectNode, snapshot } from '../app/__fixtures__/compare-fixture';
import { PUBLIC_SEVERITIES, type ApiChange, type ApiDiff, type PublicSeverity } from '../diff/types';
import { createCommentData } from './comment-data';

const BASE_SHA = '84182fe36a97aef4f1f417bb2e46109640b032f8';
const HEAD_SHA = 'fc6b754982a0328200ffd3f8d795664a0a6e8447';

function rawDiffOf(changes: ApiChange[], headRef: string): ApiDiff {
    return compareResponse(
        snapshot('v6.7.15.1', objectNode('Shopware', 'object'), []),
        snapshot(headRef, objectNode('Shopware', 'object'), []),
        changes,
    ).diff;
}

function diffOf(changes: ApiChange[], headRef = 'WORKTREE'): ApiDiff {
    return withPrivateBreakdown(rawDiffOf(changes, headRef));
}

/** Fills the private breakdown from `impact`, as the diff engine does. */
function withPrivateBreakdown(diff: ApiDiff): ApiDiff {
    const privateBySeverity = Object.fromEntries(
        PUBLIC_SEVERITIES.map((severity) => [
            severity,
            diff.changes.filter((entry) => entry.severity === 'private' && entry.impact === severity).length,
        ]),
    ) as Record<PublicSeverity, number>;

    return { ...diff, summary: { ...diff.summary, privateBySeverity } };
}

function privateChange(impact: PublicSeverity, member: string): ApiChange {
    return change('private', 'prop', 'sw-internal', member, { impact, privateVisibility: 'internal' });
}

describe('api-explorer/report/comment-data', () => {
    it('has the agreed shape with the checkout sha for the working tree', () => {
        const data = createCommentData(
            diffOf([
                change('breaking', 'method', 'sw-foo', 'onSave', { reasons: ['Removed.', 'Really.'] }),
                change('potentiallyBreaking', 'objectMember', 'Shopware.Utils', 'Shopware.Utils.get'),
                change('added', 'prop', 'sw-foo', 'size'),
                privateChange('breaking', 'a'),
                privateChange('breaking', 'b'),
                privateChange('newlyDeprecated', 'c'),
            ]),
            HEAD_SHA,
        );

        expect(data).toEqual({
            version: 2,
            base: { ref: 'v6.7.15.1', commit: BASE_SHA },
            head: { ref: 'WORKTREE', commit: HEAD_SHA },
            counts: {
                breaking: 1,
                potentiallyBreaking: 1,
                compatible: 0,
                added: 1,
                newlyDeprecated: 0,
                removedDeprecated: 0,
                private: 3,
            },
            privateCounts: {
                breaking: 2,
                potentiallyBreaking: 0,
                compatible: 0,
                added: 0,
                newlyDeprecated: 1,
                removedDeprecated: 0,
            },
            breaking: [{ kind: 'method', owner: 'sw-foo', member: 'onSave', reason: 'Removed. Really.' }],
            potentiallyBreaking: [
                {
                    kind: 'objectMember',
                    owner: 'Shopware.Utils',
                    member: 'Shopware.Utils.get',
                    reason: 'Shopware.Utils.get changed.',
                },
            ],
            omitted: { breaking: 0, potentiallyBreaking: 0 },
        });
    });

    it('lists at most 50 changes per severity, counts the rest and cuts long strings', () => {
        const breaking = Array.from({ length: 60 }, (_, index) =>
            change('breaking', 'prop', 'o'.repeat(250), `${'m'.repeat(400)}${index}`, {
                reasons: ['r'.repeat(200), 'r'.repeat(200)],
            }),
        );
        const potentially = Array.from({ length: 51 }, (_, index) =>
            change('potentiallyBreaking', 'prop', 'sw-foo', `p${index}`),
        );
        const data = createCommentData(diffOf([...breaking, ...potentially], 'r'.repeat(150)), HEAD_SHA);

        expect(data.breaking).toHaveLength(50);
        expect(data.potentiallyBreaking).toHaveLength(50);
        expect(data.omitted).toEqual({ breaking: 10, potentiallyBreaking: 1 });
        expect(data.counts.breaking).toBe(60);
        expect(data.head.ref).toHaveLength(100);
        expect(data.breaking[0].owner).toHaveLength(200);
        expect(data.breaking[0].member).toHaveLength(300);
        expect(data.breaking[0].reason).toHaveLength(300);
        expect(data.breaking[0].reason.endsWith('…')).toBe(true);
    });

    it('keeps every string on one line without control characters, never empty and cut by code points', () => {
        const data = createCommentData(
            diffOf([
                change('breaking', 'prop', '  ', `${'😀'.repeat(400)}`, {
                    reasons: ['First line\n\tsecond\u0007 line ', ''],
                }),
                change('breaking', 'prop', 'sw-foo', 'x', { reasons: [] }),
            ]),
            HEAD_SHA,
        );

        expect(data.breaking[0].owner).toBe('-');
        expect(data.breaking[0].reason).toBe('First line second line');
        expect(Array.from(data.breaking[0].member)).toHaveLength(300);
        expect(data.breaking[0].member).toBe(`${'😀'.repeat(299)}…`);
        expect(data.breaking[1].reason).toBe('-');
    });

    it('has private counts with exactly the public severities that sum to the private count', () => {
        const data = createCommentData(
            diffOf([privateChange('compatible', 'a'), privateChange('added', 'b'), privateChange('added', 'c')]),
            HEAD_SHA,
        );
        const values = Object.values(data.privateCounts);

        expect(Object.keys(data.privateCounts)).toEqual([
            'breaking',
            'potentiallyBreaking',
            'compatible',
            'added',
            'newlyDeprecated',
            'removedDeprecated',
        ]);
        expect(values.every((value) => Number.isInteger(value) && value >= 0)).toBe(true);
        expect(values.reduce((sum, value) => sum + value, 0)).toBe(data.counts.private);
        expect(data.breaking).toEqual([]);
    });

    it('rejects a private breakdown that does not sum to the private count', () => {
        const diff = diffOf([privateChange('added', 'a')]);
        diff.summary.privateBySeverity.added = 0;

        expect(() => createCommentData(diff, HEAD_SHA)).toThrow('sum to 0, not 1');
    });

    it('rejects a side without a full commit sha', () => {
        expect(() => createCommentData(diffOf([]), null)).toThrow('WORKTREE');
        expect(() => createCommentData(diffOf([]), 'abc1234')).toThrow('not a full sha');
    });
});
