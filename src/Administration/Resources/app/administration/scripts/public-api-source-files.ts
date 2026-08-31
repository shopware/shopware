/**
 * @package admin
 * @private
 *
 * Which files carry extension SDK public API, and how the identifiers are read out of them. Every
 * `scripts/generate-*` list generator and the `src/meta/meta.spec.js` guard share what is exported
 * here, so the two cannot disagree.
 */

// Fixture components under `_mocks_`/`__fixtures__` and inside split `*.spec/` directories are test
// scaffolding, not shipped public API. Without this the first fixture declaring an extension point
// silently becomes tracked API that the guard then defends forever.
const FIXTURE_PATH_REGEX = /(\/(?:_mocks_|__fixtures__)\/|\.spec\/)/;

const isSourceFile = (extensionRegex: RegExp) => (filePath: string) =>
    extensionRegex.test(filePath) && !FIXTURE_PATH_REGEX.test(filePath);

/**
 * A native setup SFC declares its blocks as `<sw-block name="...">` and keeps its markup in the
 * `.vue` template instead of a `.html.twig`, so a twig-only scan reports every block and position
 * identifier of a converted component as removed public API.
 */
export const isTemplateSourceFile = isSourceFile(/\.(html\.twig|vue)$/);

/**
 * The SFC codemod moves `.publishData(` into `<script setup>`, so a `.js`/`.ts`-only scan reports
 * every data set of a converted component as removed public API. The lookbehinds sit before the
 * extension alternation, so `foo.spec.vue` and `foo.vue2.ts` are excluded too.
 */
export const isDataSetSourceFile = isSourceFile(/(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts|vue)$/);

/**
 * Every first capture group of every pattern, in pattern order.
 */
export function captures(code: string, ...patterns: RegExp[]): string[] {
    return patterns.flatMap((pattern) => [...code.matchAll(pattern)].map(([, capture]) => capture));
}
