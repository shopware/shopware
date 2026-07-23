/**
 * Detects whether a pull request changes only markdown files so the calling
 * workflows can skip their expensive jobs.
 *
 * Loaded by `.github/actions/markdown-only-changes` via actions/github-script.
 * Changed files come from the pulls.listFiles API (merge-base semantics, the
 * "Files changed" tab) instead of a local `git diff`, so no full-history
 * checkout is needed and a stale base branch cannot pollute the result.
 */

// pulls.listFiles stops listing at 3000 files; a listing at the cap may be
// truncated, so it can never prove a pull request is markdown-only.
export const MAX_LISTED_FILES = 3000;

export function shouldCheck(context) {
    return context.eventName === 'pull_request'
        && Number.isInteger(context.payload?.pull_request?.number);
}

/**
 * @param {string[]} paths every path touched by the pull request, including
 *                         the old path of renamed files
 */
export function isMarkdownOnly(paths) {
    return paths.length > 0 && paths.every((path) => /\.md$/i.test(path));
}

/**
 * @param {Array<{filename: string, previous_filename?: string}>} files
 */
export function collectPaths(files) {
    return files.flatMap((file) => (
        file.previous_filename ? [file.filename, file.previous_filename] : [file.filename]
    ));
}

export async function detectDocsOnly({ github, core, context }) {
    if (!shouldCheck(context)) {
        core.setOutput('docs_only', 'false');

        return false;
    }

    let files;
    try {
        files = await github.paginate(github.rest.pulls.listFiles, {
            owner: context.repo.owner,
            repo: context.repo.repo,
            pull_number: context.payload.pull_request.number,
            per_page: 100,
        });
    } catch (error) {
        core.warning(`Could not list pull request files, assuming the pull request is not markdown-only: ${error.message}`);
        core.setOutput('docs_only', 'false');

        return false;
    }

    const docsOnly = files.length < MAX_LISTED_FILES && isMarkdownOnly(collectPaths(files));
    core.setOutput('docs_only', docsOnly ? 'true' : 'false');

    return docsOnly;
}
