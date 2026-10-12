import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { Row } from './waiting-on.ts';
import {
    calendarDaysSince,
    escapeSlack,
    isReminderDue,
    newPullRequestChannels,
    notifyNewExternalPullRequest,
    parseChannels,
    planReminders,
    postSlackMessage,
    renderReminder,
} from './waiting-on-slack.ts';

const AFTER_SALES = 'domain/crm-after-sales';
const channels = parseChannels(JSON.stringify({ [AFTER_SALES]: 'C_AFTER_SALES' }));

const row = (overrides: Partial<Row> = {}): Row => ({
    id: 'PR_1',
    number: 1,
    title: 'a pull request',
    url: 'https://github.com/shopware/shopware/pull/1',
    author: 'someone',
    authorAssociation: 'CONTRIBUTOR',
    labels: ['external-contribution', AFTER_SALES],
    waitingOn: 'shopware',
    reason: 'never-reviewed',
    label: 'waiting-on/shopware',
    since: '2026-09-01T23:00:00Z',
    days: 0,
    ...overrides,
});

const quiet = { info() {} };

test('parseChannels treats a missing variable as no team and rejects anything but label to id', () => {
    assert.equal(parseChannels(undefined).size, 0);
    assert.equal(parseChannels('').size, 0);
    assert.throws(() => parseChannels('["C1"]'), /JSON object/);
    assert.throws(() => parseChannels('{"domain/checkout": 1}'), /JSON object/);
});

test('the new-PR message fires when the team label completes an external pull request', () => {
    assert.deepEqual(newPullRequestChannels(['external-contribution', AFTER_SALES], AFTER_SALES, channels), ['C_AFTER_SALES']);
});

test('the new-PR message also fires when the external label arrives second', () => {
    assert.deepEqual(newPullRequestChannels([AFTER_SALES, 'external-contribution'], 'external-contribution', channels), ['C_AFTER_SALES']);
});

test('no new-PR message for internal pull requests, other teams or unrelated labels', () => {
    assert.deepEqual(newPullRequestChannels([AFTER_SALES], AFTER_SALES, channels), []);
    assert.deepEqual(newPullRequestChannels(['external-contribution', 'domain/checkout'], 'domain/checkout', channels), []);
    // Adding an unrelated label to an already announced pull request must not announce it again.
    assert.deepEqual(newPullRequestChannels(['external-contribution', AFTER_SALES, 'priority/high'], 'priority/high', channels), []);
});

test('calendarDaysSince counts UTC dates, so a late schedule neither skips nor repeats a day', () => {
    // 23:00 on the 1st to 00:30 on the 15th is 13 days and 1.5 hours, but 14 calendar days.
    assert.equal(calendarDaysSince('2026-09-01T23:00:00Z', new Date('2026-09-15T00:30:00Z')), 14);
    assert.equal(calendarDaysSince('2026-09-01T23:00:00Z', new Date('2026-09-15T23:59:00Z')), 14);
    assert.equal(calendarDaysSince('2026-09-01T23:00:00Z', new Date('2026-09-16T00:01:00Z')), 15);
});

test('a reminder is due on day 14 and then weekly', () => {
    const due = Array.from({ length: 40 }, (_, days) => days).filter(isReminderDue);

    assert.deepEqual(due, [14, 21, 28, 35]);
});

test('planReminders only reminds about external pull requests that wait on us', () => {
    const now = new Date('2026-09-15T06:00:00Z');
    const reminders = planReminders(
        [
            row({ number: 1 }),
            row({ number: 2, waitingOn: 'author' }),
            row({ number: 3, labels: [AFTER_SALES] }),
            row({ number: 4, labels: ['external-contribution', 'domain/checkout'] }),
            row({ number: 5, since: '2026-09-02T10:00:00Z' }),
            row({ number: 6, since: '2026-08-25T10:00:00Z' }),
        ],
        channels,
        now,
    );

    assert.deepEqual(
        reminders.map((reminder) => ({ channel: reminder.channel, rows: reminder.rows.map(({ number, waitingDays }) => [number, waitingDays]) })),
        [{ channel: 'C_AFTER_SALES', rows: [[6, 21], [1, 14]] }],
    );
});

test('nothing to remind about means no message at all', () => {
    assert.deepEqual(planReminders([row({ since: '2026-09-10T00:00:00Z' })], channels, new Date('2026-09-15T06:00:00Z')), []);
});

test('titles are escaped, since Slack reads angle brackets as links', () => {
    assert.equal(escapeSlack('fix <b> & <i>'), 'fix &lt;b&gt; &amp; &lt;i&gt;');

    const text = renderReminder({ channel: 'C', rows: [{ ...row({ title: 'Use <Foo> & bar' }), waitingDays: 14 }] }, 'https://github.com/orgs/shopware/projects/69');
    assert.match(text, /<https:\/\/github.com\/shopware\/shopware\/pull\/1\|#1 Use &lt;Foo&gt; &amp; bar>/);
    assert.match(text, /<https:\/\/github.com\/orgs\/shopware\/projects\/69\|waiting-on board>/);
});

test('postSlackMessage fails on a body with ok false, which Slack sends with status 200', async () => {
    const fakeFetch = (async () => new Response(JSON.stringify({ ok: false, error: 'not_in_channel' }))) as typeof fetch;

    await assert.rejects(postSlackMessage({ token: 'xoxb', fetch: fakeFetch }, quiet, 'C1', 'hi'), /not_in_channel/);
});

test('postSlackMessage without a token logs instead of sending', async () => {
    const logged: string[] = [];
    const fakeFetch = (async () => assert.fail('must not call Slack')) as typeof fetch;

    await postSlackMessage({ fetch: fakeFetch }, { info: (message) => logged.push(message) }, 'C1', 'hi');

    assert.match(logged[0], /would post to C1/);
});

test('notifyNewExternalPullRequest posts the pull request to the team channel', async () => {
    const sent: { channel: string; text: string }[] = [];
    const fakeFetch = (async (_url: string, init: RequestInit) => {
        sent.push(JSON.parse(init.body as string));

        return new Response(JSON.stringify({ ok: true }));
    }) as typeof fetch;

    await notifyNewExternalPullRequest(
        {
            core: quiet,
            context: {
                payload: {
                    label: { name: AFTER_SALES },
                    pull_request: {
                        number: 7,
                        title: 'Fix the order export',
                        html_url: 'https://github.com/shopware/shopware/pull/7',
                        user: { login: 'contributor' },
                        labels: [{ name: 'external-contribution' }, { name: AFTER_SALES }],
                    },
                },
            },
        },
        { token: 'xoxb', fetch: fakeFetch },
        JSON.stringify({ [AFTER_SALES]: 'C_AFTER_SALES' }),
    );

    assert.equal(sent.length, 1);
    assert.equal(sent[0].channel, 'C_AFTER_SALES');
    assert.match(sent[0].text, /<https:\/\/github.com\/shopware\/shopware\/pull\/7\|#7 Fix the order export> by contributor/);
});
