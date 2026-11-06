/**
 * @sw-package framework
 */

/**
 * Lists the changed files `lint:deprecations` lints, from `git status --porcelain -z --untracked-files=all`
 * output, which lists each file of a new directory instead of the collapsed directory entry.
 * Porcelain paths are relative to the repository root, so `prefix` (from `git rev-parse --show-prefix`)
 * is stripped to keep them relative to the working directory. Rename and copy entries carry their
 * original path as the next NUL-separated token, which is skipped. Deleted files and files ESLint
 * does not lint here are left out. So is the deprecation registry: it declares the deprecated keys
 * by design, and the `--rule private-feature-declarations error` of `lint:deprecations` would
 * override the config that turns that rule off for it.
 *
 * @example
 * getLintableFiles(' M src/app/foo.ts\0R  src/new.vue\0src/old.vue\0', '');
 * // ['src/app/foo.ts', 'src/new.vue']
 */
function getLintableFiles(statusOutput, prefix = '') {
    const tokens = statusOutput.split('\0');
    const files = [];

    for (let index = 0; index < tokens.length; index += 1) {
        const entry = tokens[index];

        if (!entry) {
            continue;
        }

        const status = entry.slice(0, 2);
        const filePath = entry.slice(3);

        if (/[RC]/.test(status)) {
            index += 1;
        }

        if (status.includes('D')) {
            continue;
        }

        const relativePath = filePath.startsWith(prefix) ? filePath.slice(prefix.length) : filePath;

        if (!relativePath.startsWith('src/app/deprecation-registry/') && /\.(?:js|ts|vue|html\.twig)$/.test(relativePath)) {
            files.push(relativePath);
        }
    }

    return files;
}

module.exports = {
    getLintableFiles,
};
