/**
 * Verify that every feature heading documented in trunk's RELEASE_INFO for a version prefix is also
 * present on the release branch, and that the commit which introduced it on trunk is reachable from
 * that branch.
 *
 * Per documented heading three checks are combined:
 *  1. text      — is the heading present in the branch's copy of the file?
 *  2. commit    — is the trunk commit that introduced the heading (pickaxe `-S`) an ancestor of the branch?
 *  3. docs-only — did that commit touch only RELEASE_INFO (so reachability says nothing about the code)?
 *
 * The verdict is reported through the `release-content/verify` commit status on the release branch
 * head, not the job result: a missing entry keeps the job green and blocks via the status. An
 * operational error (bad usage, refs not fetched) throws, so a red job always means the verification
 * itself could not run. This mirrors `release-info-sections.ts`.
 */

import { execFileSync } from 'node:child_process';

export const STATUS_CONTEXT = 'release-content/verify';

export const NOTE_TEXT_WITHOUT_COMMIT = 'RELEASE_INFO present but trunk commit not in branch — verify cherry-pick includes feature code';
export const NOTE_COMMIT_WITHOUT_TEXT = 'code commit present but RELEASE_INFO entry missing from branch';
export const NOTE_DOCS_ONLY = 'RELEASE_INFO was updated in a docs-only commit — feature code commit is unknown, verify manually';

/** GitHub caps a commit status description at 140 characters. */
const STATUS_DESCRIPTION_LIMIT = 140;

/**
 * Read-only git access. Abstracting it keeps the verification logic free of subprocess calls, so it
 * can be unit-tested against an in-memory fake instead of a real repository.
 */
export type GitReader = {
    /** Content of `path` at `ref`, or an empty string when the ref or file does not exist. */
    showFile(ref: string, path: string): string;
    /** True when `ref` resolves to a commit (i.e. it has been fetched). */
    refExists(ref: string): boolean;
    /** Commit SHA for `ref`, or an empty string when the ref does not resolve. */
    resolveCommit(ref: string): string;
    /** Most recent commit on `ref` that changed the count of `needle` in `path`, or '' when none. */
    findIntroducingCommit(ref: string, needle: string, path: string): string;
    /** True when `commit` is a direct ancestor of `ref`. */
    isAncestor(commit: string, ref: string): boolean;
    /** Paths changed by `commit`. */
    changedFiles(commit: string): string[];
};

export type MissingEntry = {
    heading: string;
    sha: string;
};

export type WarningEntry = {
    heading: string;
    sha: string;
    note: string;
};

export type VerificationResult = {
    total: number;
    confirmed: number;
    missing: MissingEntry[];
    warnings: WarningEntry[];
};

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** A fenced block may quote "# ..." lines that are content, not structure. */
const FENCE_PATTERN = /^ {0,3}(`{3,}|~{3,})(.*)$/;

/** Collects every "### " heading that appears under a "# <version-prefix>.*" section. */
export function extractHeadings(content: string, versionPrefix: string): string[] {
    const sectionOpen = new RegExp(`^#\\s+${escapeRegExp(versionPrefix)}\\.`);
    const headings: string[] = [];
    let inSection = false;
    let fence: string | undefined;

    for (const line of content.split('\n')) {
        // RELEASE_INFO quotes YAML and Markdown, so a fenced block carries lines like
        // "# config/packages/shopware.yaml" and "### Example". Read as structure they cut a
        // section short — every entry below is then never verified — or add an entry that does
        // not exist. Both fail silently, which is the worst way for this check to be wrong.
        const delimiter = FENCE_PATTERN.exec(line);
        if (delimiter) {
            const [, marker, info] = delimiter;
            if (fence === undefined) {
                fence = marker[0];
            } else if (fence === marker[0] && info.trim() === '') {
                fence = undefined;
            }
            continue;
        }
        if (fence !== undefined) {
            continue;
        }

        // "# 6.7.11.0" → enter the section for this version prefix.
        if (sectionOpen.test(line)) {
            inSection = true;
            continue;
        }
        // Any other top-level "# " heading closes the section ("### " is not matched by "# ").
        if (inSection && /^#\s/.test(line)) {
            inSection = false;
            continue;
        }
        if (inSection && /^###\s/.test(line)) {
            headings.push(line);
        }
    }

    return headings;
}

export function verifyReleaseContent(
    git: GitReader,
    options: { versionPrefix: string; trunkRef: string; branchRef: string; releaseInfoFile: string },
): VerificationResult {
    const { versionPrefix, trunkRef, branchRef, releaseInfoFile } = options;

    // Trunk is the authoritative source for what is supposed to be in the release; the branch is what
    // actually shipped (or will ship).
    const trunkHeadings = extractHeadings(git.showFile(trunkRef, releaseInfoFile), versionPrefix);
    const branchHeadings = new Set(extractHeadings(git.showFile(branchRef, releaseInfoFile), versionPrefix));

    const missing: MissingEntry[] = [];
    const warnings: WarningEntry[] = [];
    let confirmed = 0;

    for (const heading of trunkHeadings) {
        const textPresent = branchHeadings.has(heading);

        const introducingCommit = git.findIntroducingCommit(trunkRef, heading, releaseInfoFile);
        let commitReachable = false;
        let docsOnly = false;

        if (introducingCommit !== '') {
            commitReachable = git.isAncestor(introducingCommit, branchRef);

            const changed = git.changedFiles(introducingCommit);
            docsOnly = changed.length === 1 && changed[0] === releaseInfoFile;
        }

        if (textPresent && commitReachable) {
            if (docsOnly) {
                // Reachable commit that only touched RELEASE_INFO — reachability says nothing about
                // the feature code, so flag it for manual review.
                warnings.push({ heading, sha: introducingCommit, note: NOTE_DOCS_ONLY });
            } else {
                confirmed++; // heading and feature code landed together and are reachable
            }
        } else if (!textPresent && !commitReachable) {
            missing.push({ heading, sha: introducingCommit });
        } else if (textPresent) {
            // heading present but the introducing commit is not an ancestor of the branch
            warnings.push({ heading, sha: introducingCommit, note: NOTE_TEXT_WITHOUT_COMMIT });
        } else {
            // introducing commit reachable but the heading text is missing from the branch
            warnings.push({ heading, sha: introducingCommit, note: NOTE_COMMIT_WITHOUT_TEXT });
        }
    }

    // Order entries by the introducing commit hash so log and summary are stable and grouped by commit.
    const byCommit = (a: { sha: string }, b: { sha: string }): number => (a.sha < b.sha ? -1 : a.sha > b.sha ? 1 : 0);
    missing.sort(byCommit);
    warnings.sort(byCommit);

    return { total: trunkHeadings.length, confirmed, missing, warnings };
}

/** Commit reference for the console log: short SHA locally, "short (full-url)" inside GitHub Actions. */
function commitRef(sha: string, commitUrlBase: string): string {
    if (sha === '') {
        return 'unknown';
    }

    const short = sha.slice(0, 8);

    return commitUrlBase !== '' ? `${short} (${commitUrlBase}/${sha})` : short;
}

/** Commit reference as a Markdown link for the job summary (code span when unknown). */
function commitMd(sha: string, commitUrlBase: string): string {
    if (sha === '') {
        return '`unknown`';
    }

    const short = sha.slice(0, 8);

    return commitUrlBase !== '' ? `[\`${short}\`](${commitUrlBase}/${sha})` : `\`${short}\``;
}

/** Strip the leading "### " from a heading and escape it for a Markdown table cell. */
function mdTitle(heading: string): string {
    // Escape the backslash first, otherwise the one added for a pipe would be escaped in turn.
    return heading.replace(/^###\s+/, '').replace(/\\/g, '\\\\').replace(/\|/g, '\\|');
}

export function consoleReport(result: VerificationResult, releaseInfoFile: string, commitUrlBase = ''): string {
    let report = '';

    if (result.warnings.length > 0) {
        report += `WARN: ${result.warnings.length} of ${result.total} entries need manual verification:\n\n`;
        for (const warning of result.warnings) {
            report += `  ? ${warning.heading} [${commitRef(warning.sha, commitUrlBase)}] ${warning.note}\n`;
        }
        report += '\n';
    }

    if (result.missing.length > 0) {
        report += `MISSING: ${result.missing.length} of ${result.total} entries documented on trunk are absent from this release branch:\n\n`;
        for (const missing of result.missing) {
            report += `  x ${missing.heading} [${commitRef(missing.sha, commitUrlBase)}]\n`;
        }
        report += '\n';
    }

    if (result.missing.length > 0) {
        return `${report}These features were documented in ${releaseInfoFile} on trunk but have not been merged into this release branch.\n`;
    }

    const ok = result.total - result.warnings.length;

    return `${report}OK: ${ok} of ${result.total} entries confirmed present. ${result.warnings.length} need manual verification (see above).\n`;
}

export function markdownSummary(
    result: VerificationResult,
    options: { versionPrefix: string; branchRef: string; releaseInfoFile: string; commitUrlBase?: string },
): string {
    const { versionPrefix, branchRef, releaseInfoFile, commitUrlBase = '' } = options;
    const lines: string[] = [];

    lines.push(`## Release content verification — \`${versionPrefix}.*\``);
    lines.push('');
    lines.push(`**${result.confirmed}** confirmed · **${result.warnings.length}** warning(s) · **${result.missing.length}** missing (of ${result.total})`);
    lines.push('');
    lines.push(`- branch: \`${branchRef}\``);
    lines.push(`- file: \`${releaseInfoFile}\``);
    lines.push('');

    if (result.missing.length > 0) {
        lines.push('### ❌ Missing from this release branch');
        lines.push('');
        lines.push('| Entry | Trunk commit |');
        lines.push('| --- | --- |');
        for (const missing of result.missing) {
            lines.push(`| ${mdTitle(missing.heading)} | ${commitMd(missing.sha, commitUrlBase)} |`);
        }
        lines.push('');
    }

    if (result.warnings.length > 0) {
        lines.push('### ⚠️ Needs manual verification');
        lines.push('');
        lines.push('| Entry | Trunk commit | Note |');
        lines.push('| --- | --- | --- |');
        for (const warning of result.warnings) {
            lines.push(`| ${mdTitle(warning.heading)} | ${commitMd(warning.sha, commitUrlBase)} | ${warning.note} |`);
        }
        lines.push('');
    }

    if (result.missing.length === 0 && result.warnings.length === 0) {
        lines.push(`✅ All ${result.total} documented entries are present and traceable.`);
    }

    return `${lines.join('\n')}\n`;
}

/** One-line verdict for the commit status (kept within GitHub's 140-character limit). */
export function commitStatusDescription(result: VerificationResult, versionPrefix: string, targetBranch: string): string {
    if (result.total === 0) {
        return `no entries for ${versionPrefix}.* on trunk — nothing to verify`;
    }

    if (result.missing.length > 0) {
        return `${result.missing.length} of ${result.total} documented entries missing from ${targetBranch}`;
    }

    return `${result.confirmed} of ${result.total} entries confirmed, ${result.warnings.length} need manual verification`;
}

/** Derives "6.7.11" from an explicit input or a "6.7.11.x" release-branch ref name. */
export function resolveVersionPrefix(input: string | undefined, refName: string | undefined): string {
    const fromInput = (input ?? '').trim();
    if (fromInput !== '') {
        return fromInput;
    }

    const branchMatch = /^(\d+\.\d+\.\d+)\.x$/.exec(refName ?? '');
    if (branchMatch) {
        return branchMatch[1];
    }

    throw new Error(`cannot derive a version prefix from ref "${refName ?? ''}" — pass version_prefix explicitly`);
}

/** {@link GitReader} backed by the git binary. Commands are argument lists, so there is no shell. */
export function createProcessGitReader(cwd?: string): GitReader {
    const output = (args: string[]): string => {
        return execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    };

    const isExpectedShowFileMiss = (error: unknown): boolean => {
        if (typeof error !== 'object' || error === null || !('status' in error)) {
            return false;
        }

        const status = (error as { status?: unknown }).status;
        if (status !== 128) {
            return false;
        }

        const stderr = (error as { stderr?: unknown }).stderr;
        const message = typeof stderr === 'string' ? stderr : Buffer.isBuffer(stderr) ? stderr.toString('utf8') : '';

        return message.includes('does not exist in')
            || message.includes('exists on disk, but not in')
            || message.includes('invalid object name');
    };

    const outputOrEmpty = (args: string[]): string => {
        try {
            return output(args);
        } catch (error) {
            if (!isExpectedShowFileMiss(error)) {
                throw error;
            }

            // Missing refs/files are an expected "not present" result for RELEASE_INFO comparisons.
            return '';
        }
    };

    const succeeds = (args: string[]): boolean => {
        try {
            execFileSync('git', args, { cwd, stdio: 'ignore' });

            return true;
        } catch {
            return false;
        }
    };

    return {
        showFile: (ref, path) => outputOrEmpty(['show', `${ref}:${path}`]),
        refExists: (ref) => succeeds(['rev-parse', '--verify', '--quiet', ref]),
        resolveCommit: (ref) => output(['rev-parse', '--verify', ref]).trim(),
        findIntroducingCommit: (ref, needle, path) => output(['log', ref, '--format=%H', '--max-count=1', '-S', needle, '--', path]).trim(),
        isAncestor: (commit, ref) => succeeds(['merge-base', '--is-ancestor', commit, ref]),
        changedFiles: (commit) => output(['diff-tree', '--no-commit-id', '-r', '--name-only', commit])
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== ''),
    };
}

type Repo = { owner: string; repo: string };

type GithubClient = {
    rest: {
        repos: {
            createCommitStatus(options: {
                owner: string;
                repo: string;
                sha: string;
                state: 'success' | 'failure';
                context: string;
                description: string;
                target_url?: string;
            }): Promise<unknown>;
        };
    };
};

type Logger = {
    info(message: string): void;
    summary: { addRaw(text: string): { write(): Promise<unknown> } };
};

export type Toolkit = { github: GithubClient; core: Logger; context: { repo: Repo; sha: string } };

/** Base URL for commit links when running inside GitHub Actions, else an empty string. */
function commitUrlBaseFromEnv(repo: Repo): string {
    const server = process.env.GITHUB_SERVER_URL;

    return server ? `${server}/${repo.owner}/${repo.repo}/commit` : '';
}

/** The only link a commit status carries, so point it at the run holding the long form. */
function runUrl(repo: Repo): string | undefined {
    const server = process.env.GITHUB_SERVER_URL;
    const runId = process.env.GITHUB_RUN_ID;

    return server && runId ? `${server}/${repo.owner}/${repo.repo}/actions/runs/${runId}` : undefined;
}

function truncate(text: string): string {
    return text.length <= STATUS_DESCRIPTION_LIMIT ? text : `${text.slice(0, STATUS_DESCRIPTION_LIMIT - 1)}…`;
}

/**
 * Runs the verification and reports the verdict as the `release-content/verify` commit status. The
 * `git` reader is injectable so the orchestration can be tested without a real repository; in the
 * workflow it defaults to the git binary in the checked-out repository.
 */
export async function checkReleaseContent(toolkit: Toolkit, git: GitReader = createProcessGitReader()): Promise<void> {
    const { github, core, context } = toolkit;

    const versionPrefix = resolveVersionPrefix(process.env.VERSION_PREFIX, process.env.GITHUB_REF_NAME);
    const majorMinor = versionPrefix.split('.').slice(0, 2).join('.');
    const releaseInfoFile = `RELEASE_INFO-${majorMinor}.md`;
    const targetBranch = `${versionPrefix}.x`;
    const trunkRef = 'origin/trunk';
    const branchRef = `origin/${targetBranch}`;

    if (!git.refExists(trunkRef)) {
        throw new Error(`${trunkRef} not found — fetch it first (git fetch origin trunk).`);
    }
    if (!git.refExists(branchRef)) {
        throw new Error(`release branch ${branchRef} not found — fetch it first (git fetch origin ${targetBranch}).`);
    }

    const branchHeadSha = git.resolveCommit(branchRef);
    if (branchHeadSha === '') {
        throw new Error(`release branch ${branchRef} could not be resolved to a commit.`);
    }

    const commitUrlBase = commitUrlBaseFromEnv(context.repo);
    const result = verifyReleaseContent(git, { versionPrefix, trunkRef, branchRef, releaseInfoFile });

    core.info(`Verifying RELEASE_INFO for ${versionPrefix}.* (trunk=${trunkRef}, branch=${branchRef}, file=${releaseInfoFile})`);
    if (result.total === 0) {
        core.info(`No entries found for ${versionPrefix}.* in trunk's ${releaseInfoFile} — nothing to verify.`);
    } else {
        core.info(consoleReport(result, releaseInfoFile, commitUrlBase));
        await core.summary.addRaw(markdownSummary(result, { versionPrefix, branchRef, releaseInfoFile, commitUrlBase })).write();
    }

    const target = runUrl(context.repo);
    await github.rest.repos.createCommitStatus({
        ...context.repo,
        sha: branchHeadSha,
        state: result.missing.length === 0 ? 'success' : 'failure',
        context: STATUS_CONTEXT,
        description: truncate(commitStatusDescription(result, versionPrefix, targetBranch)),
        ...(target ? { target_url: target } : {}),
    });
}
