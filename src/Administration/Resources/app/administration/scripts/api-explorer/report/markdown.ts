/**
 * @sw-package framework
 */

import { SEVERITY_META, KIND_LABELS } from '../app/model/severity';
import {
    PUBLIC_SEVERITIES,
    SEVERITIES,
    type ApiChange,
    type ApiDiff,
    type PublicSeverity,
    type Severity,
} from '../diff/types';
import { WORKTREE_REF } from '../snapshot/types';

export const COMMENT_MARKER = '<!-- admin-api-explorer-report -->';
export const REPORT_ARTIFACT_NAME = 'api-explorer-report';
export const MAX_COMMENT_LENGTH = 60_000;
export const REPRODUCE_COMMAND = 'composer admin:api-explorer -- --port 5175';

const PRIVATE_LABELS: Record<PublicSeverity, string> = {
    breaking: 'breaking',
    potentiallyBreaking: 'potentially breaking',
    compatible: 'compatible',
    added: 'added',
    newlyDeprecated: 'newly deprecated',
    removedDeprecated: 'removed (was deprecated)',
};

const MAX_NAME_LENGTH = 200;
const MAX_REASON_LENGTH = 400;
const LISTED_SEVERITIES = ['breaking', 'potentiallyBreaking'] as const satisfies readonly Severity[];

export interface CommentOptions {
    /** Link to the workflow run or artifact with the full report. */
    reportUrl?: string | null;
    /** Commit of the checkout when the head is the working tree. */
    worktreeCommit?: string | null;
    maxLength?: number;
}

type ListLimits = Record<(typeof LISTED_SEVERITIES)[number], number>;

function shorten(text: string, maxLength: number): string {
    const singleLine = text.replace(/\s+/g, ' ').trim();

    return singleLine.length > maxLength ? `${singleLine.slice(0, maxLength - 1)}…` : singleLine;
}

export function codeSpan(text: string): string {
    const value = shorten(text, MAX_NAME_LENGTH);
    const longestRun = Math.max(0, ...(value.match(/`+/g) ?? []).map((run) => run.length));
    const fence = '`'.repeat(longestRun + 1);
    const padding = value.startsWith('`') || value.endsWith('`') ? ' ' : '';

    return `${fence}${padding}${value}${padding}${fence}`;
}

function escapePlain(text: string): string {
    return text
        .split(/(@[\w-]+)/)
        .map((part, index) => (index % 2 === 1 ? codeSpan(part) : part.replace(/[\\`*_[\]<>|~&#]/g, '\\$&')))
        .join('');
}

/** Escapes text for inline Markdown, keeping balanced code spans as they are. */
export function escapeMarkdown(text: string): string {
    const value = shorten(text, MAX_REASON_LENGTH);
    const codeSpans = /(`+)(?!`)([\s\S]*?[^`])\1(?!`)/g;
    let result = '';
    let last = 0;

    for (const match of value.matchAll(codeSpans)) {
        result += escapePlain(value.slice(last, match.index)) + match[0];
        last = match.index + match[0].length;
    }

    return result + escapePlain(value.slice(last));
}

function shortSha(commit: string | null | undefined): string | null {
    return commit ? commit.slice(0, 7) : null;
}

function describeRef(ref: string, commit: string | null, worktreeCommit: string | null): string {
    if (ref === WORKTREE_REF) {
        const sha = shortSha(worktreeCommit);

        return sha ? `${codeSpan(ref)} (checkout of ${codeSpan(sha)})` : `${codeSpan(ref)} (checkout)`;
    }

    const sha = shortSha(commit);

    return sha ? `${codeSpan(ref)} @ ${codeSpan(sha)}` : codeSpan(ref);
}

function memberName(change: ApiChange): string | null {
    if (change.kind === 'component') {
        return null;
    }

    if (change.kind === 'objectMember' && change.member.startsWith(change.owner)) {
        return change.member.slice(change.owner.length).replace(/^[.#]/, '') || change.member;
    }

    return change.member;
}

function renderChange(change: ApiChange): string {
    const member = memberName(change);
    const name = member === null ? codeSpan(change.owner) : `${codeSpan(change.owner)} › ${codeSpan(member)}`;
    const reasons = change.reasons.length > 0 ? `: ${escapeMarkdown(change.reasons.join(' '))}` : '';

    return `- ${name} (${KIND_LABELS[change.kind].toLowerCase()}, ${change.change})${reasons}`;
}

function renderList(changes: ApiChange[], limit: number): string[] {
    const lines = changes.slice(0, limit).map(renderChange);

    if (changes.length > limit) {
        lines.push(`- … and ${changes.length - limit} more`);
    }

    return lines;
}

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function sanitizeUrl(url: string): string {
    return new URL(url).href.replace(/\(/g, '%28').replace(/\)/g, '%29');
}

function render(diff: ApiDiff, options: CommentOptions, limits: ListLimits): string {
    const { summary } = diff;
    const worktreeCommit = options.worktreeCommit ?? null;
    const breaking = diff.changes.filter((change) => change.severity === 'breaking');
    const potentiallyBreaking = diff.changes.filter((change) => change.severity === 'potentiallyBreaking');
    const others = SEVERITIES.filter((severity) => !(LISTED_SEVERITIES as readonly Severity[]).includes(severity));
    const lines: string[] = [
        COMMENT_MARKER,
        '## Admin API diff',
        '',
        `Compares ${describeRef(diff.base.ref, diff.base.commit, null)} (base) with ${describeRef(diff.head.ref, diff.head.commit, worktreeCommit)} (head): ${plural(summary.total, 'change')} to the public extension API of the Administration.`,
        '',
        summary.hasBreaking
            ? `**${plural(summary.bySeverity.breaking, 'breaking change')}.** Please explain ${summary.bySeverity.breaking === 1 ? 'it' : 'them'} in the PR or deprecate first.`
            : '**No breaking changes.**',
        '',
        '| Status | Count |',
        '| --- | ---: |',
        ...SEVERITIES.map(
            (severity) =>
                `| ${SEVERITY_META[severity].symbol} ${SEVERITY_META[severity].label} | ${summary.bySeverity[severity]} |`,
        ),
    ];

    if (summary.bySeverity.private > 0) {
        const privateCounts = PUBLIC_SEVERITIES.map(
            (severity) => `${Math.trunc(summary.privateBySeverity[severity])} ${PRIVATE_LABELS[severity]}`,
        );
        lines.push('', `**Private API** (not a public contract, never turns this check red): ${privateCounts.join(' · ')}`);
    }

    if (breaking.length > 0) {
        lines.push('', `### Breaking changes (${breaking.length})`, '', ...renderList(breaking, limits.breaking));
    }

    if (potentiallyBreaking.length > 0) {
        lines.push(
            '',
            '<details>',
            `<summary>Potentially breaking changes (${potentiallyBreaking.length})</summary>`,
            '',
            ...renderList(potentiallyBreaking, limits.potentiallyBreaking),
            '',
            '</details>',
        );
    }

    const otherCounts = others
        .filter((severity) => summary.bySeverity[severity] > 0)
        .map((severity) => `${summary.bySeverity[severity]} ${SEVERITY_META[severity].label.toLowerCase()}`);

    if (otherCounts.length > 0) {
        lines.push('', `Other changes: ${otherCounts.join(', ')}.`);
    }

    const diagnostics = diff.base.diagnostics.length + diff.head.diagnostics.length;

    if (diagnostics > 0) {
        lines.push(
            '',
            `> [!WARNING]`,
            `> The extraction reported ${plural(diagnostics, 'problem')} (base ${diff.base.diagnostics.length}, head ${diff.head.diagnostics.length}). Some changes may be missing; the full report lists them.`,
        );
    }

    if (options.reportUrl) {
        lines.push(
            '',
            `[Download the full report](${sanitizeUrl(options.reportUrl)}) (workflow artifact ${codeSpan(REPORT_ARTIFACT_NAME)}). Unzip it and open ${codeSpan('index.html')}.`,
        );
    }

    lines.push(
        '',
        `To explore it locally, run ${codeSpan(REPRODUCE_COMMAND)} in the Docker container, open http://localhost:5175 and choose ${codeSpan(diff.base.ref)} as base.`,
        '',
        '<sub>This check is not required, so merging stays possible. Removing something that was deprecated before does not count as breaking.</sub>',
        '',
    );

    return lines.join('\n');
}

function trimLimits(diff: ApiDiff, limits: ListLimits, overshoot: number): ListLimits {
    const key = limits.potentiallyBreaking > 0 ? 'potentiallyBreaking' : 'breaking';
    const changes = diff.changes.filter((change) => change.severity === key).slice(0, limits[key]);
    let limit = changes.length;
    let removed = 0;

    while (limit > 0 && removed < overshoot) {
        limit -= 1;
        removed += renderChange(changes[limit]).length + 1;
    }

    return { ...limits, [key]: limit };
}

/** The PR comment; lists are cut with "… and N more" to stay below `maxLength`. */
export function renderPrComment(diff: ApiDiff, options: CommentOptions = {}): string {
    const maxLength = options.maxLength ?? MAX_COMMENT_LENGTH;
    let limits: ListLimits = {
        breaking: diff.summary.bySeverity.breaking,
        potentiallyBreaking: diff.summary.bySeverity.potentiallyBreaking,
    };
    let comment = render(diff, options, limits);

    while (comment.length > maxLength && limits.breaking + limits.potentiallyBreaking > 0) {
        limits = trimLimits(diff, limits, comment.length - maxLength);
        comment = render(diff, options, limits);
    }

    return comment;
}
