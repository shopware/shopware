import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    collectEntries,
    renderReport,
    reportMajorLabels,
    searchTimestamp,
    wasLabelledSince,
    windowStart,
} from './major-label-report.ts';

const NOW = new Date('2026-11-11T06:00:00.123Z');
const SINCE = new Date('2026-11-10T06:00:00.123Z');
const REPO = { owner: 'shopware', repo: 'shopware' };

test('windowStart goes back the given number of days and rejects nonsense', () => {
    assert.deepEqual(windowStart(NOW, 1), SINCE);
    assert.deepEqual(windowStart(NOW, 0.5), new Date('2026-11-10T18:00:00.123Z'));
    for (const days of [0, -1, Number.NaN]) {
        assert.throws(() => windowStart(NOW, days), /positive number of days/);
    }
});

test('searchTimestamp shows whole seconds', () => {
    assert.equal(searchTimestamp(SINCE), '2026-11-10T06:00:00Z');
});

test('wasLabelledSince only counts the label being added inside the window', () => {
    const events = [
        { event: 'labeled', created_at: '2026-11-09T10:00:00Z', label: { name: 'major/6.8' } },
        { event: 'labeled', created_at: '2026-11-10T10:00:00Z', label: { name: 'major/6.8-cleanup' } },
        { event: 'unlabeled', created_at: '2026-11-10T11:00:00Z', label: { name: 'major/6.8' } },
    ];
    assert.equal(wasLabelledSince(events, 'major/6.8', SINCE), false);
    assert.equal(wasLabelledSince(events, 'major/6.8-cleanup', SINCE), true);
    assert.equal(
        wasLabelledSince(
            [...events, { event: 'labeled', created_at: '2026-11-10T12:00:00Z', label: { name: 'major/6.8' } }],
            'major/6.8',
            SINCE,
        ),
        true,
    );
});

test('renderReport lists pull requests without an upgrade guide change first', () => {
    const report = renderReport({
        major: '6.8',
        since: SINCE,
        entries: [
            { number: 3, title: 'documented', url: 'u3', author: 'a', state: 'merged', touchesUpgradeGuide: true },
            { number: 2, title: 'pipe | in title', url: 'u2', author: 'b', state: 'open', touchesUpgradeGuide: false },
            { number: 1, title: 'undocumented', url: 'u1', author: 'c', state: 'merged', touchesUpgradeGuide: false },
        ],
    });

    assert.match(report, /3 pull request\(s\), 2 without a change to `UPGRADE-6\.8\.md`/);
    const rows = report.split('\n').filter((line) => line.startsWith('| ['));
    assert.deepEqual(
        rows.map((row) => row.match(/#(\d+)/)?.[1]),
        ['1', '2', '3'],
    );
    assert.match(rows[1], /pipe \\\| in title/);
    assert.match(rows[0], /\| \*\*no\*\* \|$/);
    assert.match(rows[2], /\| yes \|$/);
});

test('renderReport keeps a backslash in a title from undoing the pipe escape', () => {
    const report = renderReport({
        major: '6.8',
        since: SINCE,
        entries: [{ number: 1, title: 'a\\| b', url: 'u1', author: 'c', state: 'open', touchesUpgradeGuide: false }],
    });
    const row = report.split('\n').find((line) => line.startsWith('| ['));
    assert.match(String(row), /\| a\\\\\\\| b \|/);
});

test('renderReport says so when nothing happened', () => {
    assert.match(renderReport({ major: '6.8', since: SINCE, entries: [] }), /No pull request was merged/);
});

function fakeGitHub(data: {
    merged: Array<{ number: number; title: string }>;
    open: Array<{ number: number; title: string }>;
    events: Record<number, Array<{ event: string; created_at: string; label?: { name: string } }>>;
    files: Record<number, string[]>;
}) {
    const queries: string[] = [];
    const search = Symbol('search');
    const listEvents = Symbol('listEvents');
    const listFiles = Symbol('listFiles');
    const item = ({ number, title }: { number: number; title: string }) => ({
        number,
        title,
        html_url: `https://github.com/shopware/shopware/pull/${number}`,
        user: { login: 'dev' },
    });

    return {
        queries,
        github: {
            rest: { search: { issuesAndPullRequests: search }, issues: { listEvents }, pulls: { listFiles } },
            async paginate(route: unknown, options: Record<string, unknown>): Promise<any[]> {
                if (route === search) {
                    const q = String(options.q);
                    queries.push(q);

                    return (q.includes('is:merged') ? data.merged : data.open).map(item);
                }
                if (route === listEvents) {
                    return data.events[Number(options.issue_number)] ?? [];
                }
                if (route === listFiles) {
                    return (data.files[Number(options.pull_number)] ?? []).map((filename) => ({ filename }));
                }
                throw new Error('unexpected route');
            },
        },
    };
}

test('collectEntries takes merged pull requests and open ones labelled inside the window', async () => {
    const { github, queries } = fakeGitHub({
        merged: [{ number: 10, title: 'merged' }],
        open: [
            { number: 20, title: 'newly labelled' },
            { number: 30, title: 'labelled long ago' },
        ],
        events: {
            20: [{ event: 'labeled', created_at: '2026-11-10T08:00:00Z', label: { name: 'major/6.8' } }],
            30: [{ event: 'labeled', created_at: '2026-10-01T08:00:00Z', label: { name: 'major/6.8' } }],
        },
        files: { 10: ['UPGRADE-6.8.md', 'src/Core/A.php'], 20: ['src/Core/B.php'] },
    });

    const entries = await collectEntries(github, REPO, '6.8', SINCE);

    assert.deepEqual(
        entries.map(({ number, state, touchesUpgradeGuide }) => ({ number, state, touchesUpgradeGuide })),
        [
            { number: 10, state: 'merged', touchesUpgradeGuide: true },
            { number: 20, state: 'open', touchesUpgradeGuide: false },
        ],
    );
    assert.deepEqual(queries, [
        'repo:shopware/shopware is:pr label:"major/6.8" is:merged base:trunk merged:>=2026-11-10T06:00:00Z',
        'repo:shopware/shopware is:pr label:"major/6.8" is:open updated:>=2026-11-10T06:00:00Z',
    ]);
});

test('reportMajorLabels writes one section per in-flight major', async () => {
    const { github } = fakeGitHub({ merged: [], open: [], events: {}, files: {} });
    const written: string[] = [];
    const summary = {
        addRaw(text: string) {
            written.push(text);

            return summary;
        },
        async write() {
            written.push('<write>');
        },
    };
    const registry = `shopware:
  feature:
    flags:
      - name: v6.8.0.0
        default: false
      - name: v6.9.0.0
        default: false
`;

    const sections = await reportMajorLabels(
        { github, core: { info: () => {}, summary }, context: { repo: REPO } },
        () => registry,
        { now: NOW, days: 1 },
    );

    assert.equal(sections, 2);
    assert.equal(written.length, 3);
    assert.match(written[0], /`major\/6\.8`/);
    assert.match(written[1], /`major\/6\.9`/);
    assert.equal(written[2], '<write>');
});
