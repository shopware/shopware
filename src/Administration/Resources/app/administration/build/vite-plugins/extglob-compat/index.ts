/**
 * @sw-package framework
 */
import MagicString from 'magic-string';
import type { Plugin } from 'vite';

const globCallPattern = /import\.meta\.glob\s*(?:<[^>]*>)?\s*\(\s*(\[[^\]]*\]|'[^']*'|"[^"]*")/g;
const stringLiteralPattern = /'([^']*)'|"([^"]*)"/g;
const extglobPattern = /!\(([^()]*)\)/;

/**
 * @private
 *
 * Converts a negated extglob like `./**\/!(*.spec).ts` into the negated patterns Vite 8 supports:
 * `['./**\/*.ts', '!./**\/*.spec.ts']`.
 */
export function toNegatedGlobPatterns(patterns: string[]): string[] {
    return patterns.flatMap((pattern) => {
        const isNegated = pattern.startsWith('!');
        const body = isNegated ? pattern.slice(1) : pattern;
        const extglob = extglobPattern.exec(body);

        if (!extglob) {
            return [pattern];
        }

        const allFiles = body.replace(extglob[0], '*');

        if (isNegated) {
            return [`!${allFiles}`];
        }

        return [
            allFiles,
            `!${body.replace(extglob[0], extglob[1])}`,
        ];
    });
}

/**
 * @private
 *
 * @deprecated tag:v6.8.0 - Will be removed. The Administration uses negated glob patterns then.
 *
 * Vite 8 no longer matches extglob patterns like `!(*.spec)` in `import.meta.glob`. The Administration keeps them as
 * long as Vite 6 is the default, because the Jest setups of extensions transform its sources with older versions of
 * `babel-plugin-shopware-vite-meta-glob`, which only support a single string pattern. This plugin rewrites them for
 * Vite 8 before Vite resolves the globs.
 */
export default function ExtglobCompatPlugin(): Plugin {
    return {
        name: 'shopware-vite-plugin-extglob-compat',

        enforce: 'pre',

        transform: {
            filter: {
                id: { exclude: /\/node_modules\// },
                code: 'import.meta.glob',
            },
            handler(code, id) {
                const magicString = new MagicString(code);

                for (const match of code.matchAll(globCallPattern)) {
                    const patterns = [...match[1].matchAll(stringLiteralPattern)].map((literal) => literal[1] ?? literal[2]);

                    if (!patterns.some((pattern) => extglobPattern.test(pattern))) {
                        continue;
                    }

                    const start = match.index + match[0].length - match[1].length;
                    const rewrittenPatterns = toNegatedGlobPatterns(patterns).map((pattern) => `'${pattern}'`);
                    magicString.overwrite(start, start + match[1].length, `[${rewrittenPatterns.join(', ')}]`);
                }

                if (!magicString.hasChanged()) {
                    return null;
                }

                return {
                    code: magicString.toString(),
                    map: magicString.generateMap({ source: id, hires: true }),
                };
            },
        },
    };
}
