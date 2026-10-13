/**
 * @sw-package framework
 */
import MagicString from 'magic-string';
import type { Plugin } from 'vite';

type AstNode = { type: string; start: number; end: number } & Record<string, unknown>;

const negatedExtglobPattern = /!\(([^()]*)\)/;
const extglobPattern = /[!@?*+]\(/;

/**
 * @private
 *
 * `./**\/!(*.spec|*.test).ts` becomes `['./**\/*.ts', '!./**\/{*.spec,*.test}.ts']`. A negated pattern cannot add files
 * back, so `!./index!(*.spec).ts` excludes every file of `./index*.ts`.
 */
export function toNegatedGlobPatterns(pattern: string): string[] {
    const isNegated = pattern.startsWith('!') && !pattern.startsWith('!(');
    const body = isNegated ? pattern.slice(1) : pattern;
    const extglob = negatedExtglobPattern.exec(body);

    if (!extglob) {
        return [pattern];
    }

    const allFiles = body.replace(extglob[0], '*');

    if (isNegated) {
        return [`!${allFiles}`];
    }

    const alternatives = extglob[1].split('|');
    const excludedFiles = alternatives.length > 1 ? `{${alternatives.join(',')}}` : extglob[1];

    return [
        allFiles,
        `!${body.replace(extglob[0], excludedFiles)}`,
    ];
}

function isAstNode(value: unknown): value is AstNode {
    return typeof value === 'object' && value !== null && typeof (value as AstNode).type === 'string';
}

function visit(value: unknown, callback: (node: AstNode) => void): void {
    if (Array.isArray(value)) {
        value.forEach((child) => visit(child, callback));
        return;
    }

    if (!isAstNode(value)) {
        return;
    }

    callback(value);
    Object.values(value).forEach((child) => visit(child, callback));
}

function isIdentifier(value: unknown, name: string): boolean {
    return isAstNode(value) && value.type === 'Identifier' && value.name === name;
}

function isImportMetaGlobCall(node: AstNode): boolean {
    const callee = node.callee;

    if (node.type !== 'CallExpression' || !isAstNode(callee) || callee.type !== 'MemberExpression') {
        return false;
    }

    const object = callee.object;

    return (
        isAstNode(object) &&
        object.type === 'MetaProperty' &&
        isIdentifier(object.meta, 'import') &&
        isIdentifier(object.property, 'meta') &&
        isIdentifier(callee.property, 'glob')
    );
}

function getStaticString(node: AstNode): string | null {
    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node.type === 'TemplateLiteral' && (node.expressions as unknown[]).length === 0) {
        const [quasi] = node.quasis as { value: { cooked: string | null } }[];

        return quasi.value.cooked;
    }

    return null;
}

/**
 * @private
 *
 * @deprecated tag:v6.8.0 - Will be removed. The Administration uses negated glob patterns then.
 *
 * Vite 8 no longer matches extglob patterns like `!(*.spec)` in `import.meta.glob`, so builds silently match no files.
 * The Administration keeps them as long as Vite 6 is the default, because the Jest setups of extensions transform its
 * sources with older versions of `babel-plugin-shopware-vite-meta-glob`, which only support a single string pattern.
 * This plugin rewrites negated extglobs into negated patterns. It has no `enforce`: registered after `vue()`, it runs
 * after the SFC and TypeScript syntax was removed and before Vite resolves the globs.
 */
export default function ExtglobCompatPlugin({ warnOnRewrite = false } = {}): Plugin {
    return {
        name: 'shopware-vite-plugin-extglob-compat',

        transform: {
            filter: {
                id: { exclude: /\/node_modules\// },
                code: 'import.meta.glob',
            },
            handler(code, id) {
                const magicString = new MagicString(code);

                visit(this.parse(code), (node) => {
                    if (!isImportMetaGlobCall(node)) {
                        return;
                    }

                    const [globs] = node.arguments as AstNode[];

                    if (!globs) {
                        return;
                    }

                    const elements = globs.type === 'ArrayExpression' ? (globs.elements as (AstNode | null)[]) : [globs];

                    elements.forEach((element) => {
                        const pattern = element ? getStaticString(element) : null;

                        if (!element || pattern === null || !extglobPattern.test(pattern)) {
                            return;
                        }

                        const patterns = toNegatedGlobPatterns(pattern);

                        if (patterns.some((rewritten) => extglobPattern.test(rewritten))) {
                            this.warn(
                                `Vite 8 does not match the extglob pattern "${pattern}" in ${id}. Use negated patterns instead.`,
                            );
                            return;
                        }

                        if (warnOnRewrite) {
                            this.warn(
                                `Vite 8 does not match the extglob pattern "${pattern}" in ${id}. Shopware rewrites it until 6.8, use ${JSON.stringify(patterns)} instead.`,
                            );
                        }

                        const replacement = patterns.map((rewritten) => JSON.stringify(rewritten)).join(', ');
                        magicString.overwrite(
                            element.start,
                            element.end,
                            element === globs ? `[${replacement}]` : replacement,
                        );
                    });
                });

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
