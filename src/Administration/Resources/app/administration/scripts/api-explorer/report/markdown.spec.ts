/**
 * @sw-package framework
 */

import { change, compareResponse, objectNode, snapshot } from '../app/__fixtures__/compare-fixture';
import { PUBLIC_SEVERITIES, type ApiChange, type ApiDiff, type PublicSeverity } from '../diff/types';
import { COMMENT_MARKER, renderPrComment } from './markdown';

const BASE = snapshot('v6.7.15.1', objectNode('Shopware', 'object'), []);
const HEAD = snapshot('WORKTREE', objectNode('Shopware', 'object'), []);

function diffOf(changes: ApiChange[]): ApiDiff {
    return withPrivateBreakdown(compareResponse(BASE, HEAD, changes).diff);
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

const CHANGES = [
    change('breaking', 'method', 'sw-product-detail', 'onSave', {
        change: 'removed',
        reasons: ['Removed without prior deprecation.'],
    }),
    change('breaking', 'objectMember', 'Shopware.Data.ChangesetGenerator', 'Shopware.Data.ChangesetGenerator#generate', {
        reasons: ['Type changed.'],
    }),
    change('potentiallyBreaking', 'block', 'sw-product-detail', 'sw_product_detail_actions', {
        change: 'moved',
        reasons: ['Moved to a different parent.'],
    }),
    change('added', 'prop', 'sw-button', 'size'),
    change('added', 'prop', 'sw-button', 'variant'),
    change('newlyDeprecated', 'component', 'sw-old', 'sw-old'),
];

describe('api-explorer/report/markdown', () => {
    it('starts with the marker line and names both refs with short shas', () => {
        const comment = renderPrComment(diffOf(CHANGES), { worktreeCommit: 'abcdef1234567890' });

        expect(comment.split('\n')[0]).toBe(COMMENT_MARKER);
        expect(comment).toContain('`v6.7.15.1` @ `84182fe` (base)');
        expect(comment).toContain('`WORKTREE` (checkout of `abcdef1`) (head)');
        expect(comment).toContain('6 changes');
    });

    it('counts every severity and lists the rest as counts', () => {
        const comment = renderPrComment(diffOf(CHANGES));

        expect(comment).toContain('| × Breaking | 2 |');
        expect(comment).toContain('| ! Potentially breaking | 1 |');
        expect(comment).toContain('| + Added | 2 |');
        expect(comment).toContain('| ∘ Private | 0 |');
        expect(comment).toContain('Other changes: 2 added, 1 newly deprecated.');
        expect(comment).not.toContain('`sw-button`');
    });

    it('breaks the private changes down by what they would be if they were public', () => {
        const comment = renderPrComment(
            diffOf([
                ...CHANGES,
                privateChange('breaking', 'a'),
                privateChange('breaking', 'b'),
                privateChange('added', 'c'),
                privateChange('removedDeprecated', 'd'),
            ]),
        );
        const line =
            '**Private API** (not a public contract, never turns this check red): 2 breaking · 0 potentially breaking · 0 compatible · 1 added · 0 newly deprecated · 1 removed (was deprecated)';

        expect(comment).toContain('| ∘ Private | 4 |');
        expect(comment).toContain(`| ∘ Private | 4 |\n\n${line}\n`);
        expect(comment).toContain('### Breaking changes (2)');
        expect(comment).not.toContain('`sw-internal`');
    });

    it('leaves the private line out without private changes', () => {
        expect(renderPrComment(diffOf(CHANGES))).not.toContain('**Private API**');
    });

    it('lists breaking changes in order and potentially breaking ones inside details', () => {
        const comment = renderPrComment(diffOf(CHANGES));
        const breaking = comment.indexOf('### Breaking changes (2)');
        const removedMethod = comment.indexOf(
            '- `sw-product-detail` › `onSave` (method, removed): Removed without prior deprecation.',
        );
        const objectMember = comment.indexOf('- `Shopware.Data.ChangesetGenerator` › `generate` (object member, changed)');
        const details = comment.indexOf('<details>\n<summary>Potentially breaking changes (1)</summary>\n\n');

        expect(breaking).toBeGreaterThan(-1);
        expect(removedMethod).toBeGreaterThan(breaking);
        expect(objectMember).toBeGreaterThan(removedMethod);
        expect(details).toBeGreaterThan(objectMember);
        expect(comment.slice(details)).toContain('`sw-product-detail` › `sw_product_detail_actions` (twig block, moved)');
    });

    it('says when nothing breaks and leaves the lists out', () => {
        const comment = renderPrComment(diffOf([change('added', 'prop', 'sw-button', 'size')]));

        expect(comment).toContain('**No breaking changes.**');
        expect(comment).not.toContain('### Breaking changes');
        expect(comment).not.toContain('<details>');
    });

    it('links the full report only when a URL is given', () => {
        const withoutUrl = renderPrComment(diffOf(CHANGES));
        const withUrl = renderPrComment(diffOf(CHANGES), {
            reportUrl: 'https://github.com/shopware/shopware/actions/runs/1/artifacts/(2)',
        });

        expect(withoutUrl).not.toContain('full report](');
        expect(withUrl).toContain(
            '[Download the full report](https://github.com/shopware/shopware/actions/runs/1/artifacts/%282%29) (workflow artifact `api-explorer-report`)',
        );

        for (const comment of [
            withoutUrl,
            withUrl,
        ]) {
            expect(comment).toContain('`composer admin:api-explorer -- --port 5175`');
            expect(comment).toContain('This check is not required');
        }
    });

    it('escapes Markdown in reasons, keeps their code spans and fences names with backticks', () => {
        const comment = renderPrComment(
            diffOf([
                change('breaking', 'prop', 'sw-`odd`', '`value`', {
                    reasons: ['Type `string | null` is now <b>*required*</b> [see](x) by @internal and #12, a ` stray.'],
                }),
            ]),
        );

        expect(comment).toContain(
            '- `` sw-`odd` `` › `` `value` `` (prop, changed): Type `string | null` is now \\<b\\>\\*required\\*\\</b\\> \\[see\\](x) by `@internal` and \\#12, a \\` stray.',
        );
    });

    it('truncates long lists with "… and N more" to stay below the length limit', () => {
        const many = Array.from({ length: 400 }, (_, index) =>
            change('breaking', 'prop', 'sw-component', `prop${String(index).padStart(3, '0')}`, {
                reasons: ['Removed without prior deprecation.'.repeat(5)],
            }),
        );
        const potentially = Array.from({ length: 50 }, (_, index) =>
            change('potentiallyBreaking', 'prop', 'sw-component', `other${index}`),
        );
        const comment = renderPrComment(diffOf([...many, ...potentially]), { maxLength: 20_000 });

        expect(comment.length).toBeLessThanOrEqual(20_000);
        expect(comment).toContain('### Breaking changes (400)');
        expect(comment).toContain('`prop000`');
        expect(comment).toMatch(/- … and \d+ more\n/);
        expect(comment).toContain('- … and 50 more');
        expect(comment).toContain('This check is not required');
    });

    it('stays below 60,000 characters by default', () => {
        const many = Array.from({ length: 3000 }, (_, index) =>
            change('breaking', 'prop', 'sw-component', `prop${index}`, { reasons: ['x'.repeat(300)] }),
        );

        expect(renderPrComment(diffOf(many)).length).toBeLessThanOrEqual(60_000);
    });
});
