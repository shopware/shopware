/**
 * Slack notifications for external pull requests, per team.
 *
 * A team opts in with an entry in the `WAITING_ON_SLACK_CHANNELS` repository variable, a
 * JSON object from its domain label to a Slack channel id:
 *
 *   {"domain/crm-after-sales": "C0123456789"}
 *
 * The bot behind `SLACK_TOKEN_PRODUCT_NOTIFICATIONS` has to be a member of that channel.
 * Without a token, every message is logged instead of sent.
 *
 * Two messages exist, both for pull requests carrying `external-contribution`:
 *
 *   - a new-PR message once a pull request has both that label and a team's label, sent
 *     from `external-pr-notify.yml` on the `labeled` event;
 *   - a reminder from the daily waiting-on sweep when one has been waiting on us for
 *     REMINDER_AFTER_DAYS, repeated every REMINDER_EVERY_DAYS while it still is.
 *
 * `external-contribution` is set with GITHUB_TOKEN, whose events start no workflow, so it
 * never triggers the new-PR message itself. That still works because it lands the moment a
 * pull request opens, before anyone can triage it: the team label, which a person or an
 * octo-sts app adds later, is the event that fires with both labels present.
 *
 * The reminder keeps no state. Whether one is due is derived from `since` on every run, in
 * calendar days, so a schedule that starts late does not skip or repeat a day. A manual
 * run on a day the schedule already ran repeats that day's reminders.
 */

import type { Row } from './waiting-on.ts';

export const EXTERNAL_LABEL = 'external-contribution';

export const REMINDER_AFTER_DAYS = 14;

export const REMINDER_EVERY_DAYS = 7;

export type Channels = Map<string, string>;

export function parseChannels(json: string | undefined): Channels {
    if (json === undefined || json.trim() === '') {
        return new Map();
    }

    const parsed: unknown = JSON.parse(json);
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed) || !Object.values(parsed).every((value) => typeof value === 'string')) {
        throw new Error('WAITING_ON_SLACK_CHANNELS must be a JSON object from a label to a Slack channel id.');
    }

    return new Map(Object.entries(parsed as Record<string, string>));
}

/** The channels a pull request's new-PR message goes to, given the label that was just added. */
export function newPullRequestChannels(labels: string[], addedLabel: string, channels: Channels): string[] {
    if (!labels.includes(EXTERNAL_LABEL)) {
        return [];
    }

    return [...channels]
        .filter(([teamLabel]) => labels.includes(teamLabel) && (addedLabel === teamLabel || addedLabel === EXTERNAL_LABEL))
        .map(([, channel]) => channel);
}

export function calendarDaysSince(timestamp: string, now: Date): number {
    const day = (date: Date) => Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate());

    return Math.round((day(now) - day(new Date(timestamp))) / 86_400_000);
}

export function isReminderDue(days: number): boolean {
    return days >= REMINDER_AFTER_DAYS && (days - REMINDER_AFTER_DAYS) % REMINDER_EVERY_DAYS === 0;
}

export type Reminder = { channel: string; rows: (Row & { waitingDays: number })[] };

export function planReminders(rows: Row[], channels: Channels, now: Date): Reminder[] {
    const reminders: Reminder[] = [];

    for (const [teamLabel, channel] of channels) {
        const due = rows
            .filter((row) => row.waitingOn === 'shopware' && row.labels.includes(EXTERNAL_LABEL) && row.labels.includes(teamLabel))
            .map((row) => ({ ...row, waitingDays: calendarDaysSince(row.since, now) }))
            .filter((row) => isReminderDue(row.waitingDays))
            .sort((left, right) => right.waitingDays - left.waitingDays);

        if (due.length > 0) {
            reminders.push({ channel, rows: due });
        }
    }

    return reminders;
}

/** Slack reads `&`, `<` and `>` as markup, so a pull request title has to escape them. */
export function escapeSlack(text: string): string {
    return text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

const link = (url: string, text: string) => `<${url}|${escapeSlack(text)}>`;

export function renderNewPullRequest(pullRequest: { number: number; title: string; url: string; author: string }): string {
    return `:wave: New external pull request: ${link(pullRequest.url, `#${pullRequest.number} ${pullRequest.title}`)} by ${escapeSlack(pullRequest.author)}`;
}

export function renderReminder(reminder: Reminder, boardUrl?: string): string {
    const lines = [
        `:hourglass_flowing_sand: ${reminder.rows.length} external pull request(s) have been waiting on us for ${REMINDER_AFTER_DAYS} days or more:`,
        ...reminder.rows.map((row) => `• ${link(row.url, `#${row.number} ${row.title}`)} by ${escapeSlack(row.author)}: ${row.waitingDays} days, \`${row.reason}\``),
    ];

    if (boardUrl !== undefined) {
        lines.push('', `All of them on the ${link(boardUrl, 'waiting-on board')}.`);
    }

    return lines.join('\n');
}

type Logger = { info(message: string): void };

export type Slack = { token?: string; fetch?: typeof fetch };

/** Without a token the message is logged, so a team can be wired up before the bot is. */
export async function postSlackMessage(slack: Slack, core: Logger, channel: string, text: string): Promise<void> {
    if (!slack.token) {
        core.info(`No Slack token, would post to ${channel}:\n${text}`);
        return;
    }

    const response = await (slack.fetch ?? fetch)('https://slack.com/api/chat.postMessage', {
        method: 'POST',
        headers: { authorization: `Bearer ${slack.token}`, 'content-type': 'application/json; charset=utf-8' },
        body: JSON.stringify({ channel, text, unfurl_links: false }),
    });

    // Slack answers 200 for most failures and reports them in the body.
    const body = (await response.json()) as { ok: boolean; error?: string };
    if (!body.ok) {
        throw new Error(`Slack rejected the message to ${channel}: ${body.error ?? response.status}`);
    }

    core.info(`Posted to ${channel}`);
}

type Context = {
    payload: {
        label?: { name: string };
        pull_request?: { number: number; title: string; html_url: string; user: { login: string }; labels: { name: string }[] };
    };
};

export async function notifyNewExternalPullRequest({ core, context }: { core: Logger; context: Context }, slack: Slack, channelsJson: string | undefined): Promise<void> {
    const { label, pull_request: pullRequest } = context.payload;
    if (label === undefined || pullRequest === undefined) {
        throw new Error('Expected a pull_request labeled event.');
    }

    const channels = newPullRequestChannels(pullRequest.labels.map((candidate) => candidate.name), label.name, parseChannels(channelsJson));
    if (channels.length === 0) {
        core.info(`#${pullRequest.number}: \`${label.name}\` does not complete an external pull request for any team with a channel.`);
        return;
    }

    const text = renderNewPullRequest({ number: pullRequest.number, title: pullRequest.title, url: pullRequest.html_url, author: pullRequest.user.login });
    for (const channel of channels) {
        await postSlackMessage(slack, core, channel, text);
    }
}
