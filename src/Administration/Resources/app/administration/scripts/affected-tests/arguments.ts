/**
 * @sw-package framework
 * @private
 */

/** The options of `npm run unit:affected`. */
export type AffectedTestsArguments = {
    /** Changed files given on the command line; empty means "ask git". */
    files: string[];
    /** The git ref to diff against; null means the merge base with `origin/trunk`. */
    base: string | null;
    /** Print the selection with its reasons instead of running it. */
    dryRun: boolean;
    /** Print only the changed files from git, for a container where git does not work. */
    changedFiles: boolean;
};

/**
 * Parses the command line of `npm run unit:affected`.
 * Throws on an unknown option, so a typo does not silently select the wrong specs.
 *
 * @example
 * parseArguments(['--dry-run', '--base', 'HEAD~1']);
 * // → { files: [], base: 'HEAD~1', dryRun: true, changedFiles: false }
 */
export function parseArguments(argv: string[]): AffectedTestsArguments {
    const parsed: AffectedTestsArguments = { files: [], base: null, dryRun: false, changedFiles: false };

    for (let index = 0; index < argv.length; index += 1) {
        const option = argv[index];

        if (option === '--dry-run') {
            parsed.dryRun = true;
        } else if (option === '--changed-files') {
            parsed.changedFiles = true;
        } else if (option === '--base') {
            index += 1;
            if (!argv[index] || argv[index].startsWith('--')) {
                throw new Error('--base needs a git ref, for example --base HEAD~1');
            }
            parsed.base = argv[index];
        } else if (option.startsWith('--')) {
            throw new Error(`Unknown option ${option}`);
        } else {
            parsed.files.push(option);
        }
    }

    return parsed;
}
