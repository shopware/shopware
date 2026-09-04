/**
 * Fail the workflow audit when zizmor silently skips a file.
 *
 * zizmor validates every collected workflow against a schema and *warns* — but
 * still exits 0 — when a file does not match. Such a file is not audited at all,
 * so an unpinned action added to it would pass the lint unnoticed. That is the
 * exact "green without proving anything" failure mode the CI rules exist to
 * prevent, so the skips are reconciled against an explicit list instead.
 *
 * The list is empty, and the goal is to keep it that way. zizmor's schema does not
 * model a whole `strategy:` block that is an expression, so the dynamic-matrix
 * workflows put the expression on `strategy.matrix` instead — the shape zizmor does
 * parse. Moving it back onto `strategy:` silently drops those files out of the audit.
 *
 * The check runs in both directions on purpose: a file that starts being skipped
 * is a new coverage hole, and a listed file that is no longer skipped means the
 * entry is stale and should be deleted.
 */

import { readFileSync } from 'node:fs';

export const KNOWN_UNCOLLECTABLE: string[] = [];

const SCHEMA_FAILURE = /failed to validate file:\/\/(\S+?) as \w+: input does not match expected validation schema/g;

export const parseSkippedFiles = (stderr: string): string[] => {
    const found = new Set<string>();

    for (const match of stderr.matchAll(SCHEMA_FAILURE)) {
        found.add(match[1].replace(/^\.\//, ''));
    }

    return [...found].sort();
};

export type CollectionDiff = {
    unexpected: string[];
    stale: string[];
};

export const diffAgainstKnown = (skipped: string[], known: string[] = KNOWN_UNCOLLECTABLE): CollectionDiff => ({
    unexpected: skipped.filter((file) => !known.includes(file)).sort(),
    stale: known.filter((file) => !skipped.includes(file)).sort(),
});

export const formatReport = ({ unexpected, stale }: CollectionDiff): string => {
    const lines: string[] = [];

    if (unexpected.length > 0) {
        lines.push(
            'zizmor could not parse the following file(s), so they were NOT audited:',
            ...unexpected.map((file) => `  ${file}`),
            '',
            'Fix the file so zizmor can read it. Only add it to KNOWN_UNCOLLECTABLE in',
            '.github/bin/js/zizmor-collection-guard.ts when the construct is valid GitHub',
            'Actions that zizmor does not model yet — and say which construct in the comment.',
        );
    }

    if (stale.length > 0) {
        if (lines.length > 0) {
            lines.push('');
        }

        lines.push(
            'zizmor now parses the following file(s), so their KNOWN_UNCOLLECTABLE entries',
            'in .github/bin/js/zizmor-collection-guard.ts are stale and should be removed:',
            ...stale.map((file) => `  ${file}`),
        );
    }

    return lines.join('\n');
};

const main = (): void => {
    const logPath = process.argv[2];

    if (logPath === undefined) {
        process.stderr.write('usage: zizmor-collection-guard.ts <zizmor-stderr-log>\n');
        process.exit(2);
    }

    const diff = diffAgainstKnown(parseSkippedFiles(readFileSync(logPath, 'utf8')));

    if (diff.unexpected.length === 0 && diff.stale.length === 0) {
        return;
    }

    process.stderr.write(`${formatReport(diff)}\n`);
    process.exit(1);
};

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}
