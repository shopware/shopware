/**
 * @sw-package framework
 */
import type { Plugin } from 'vite';
import MagicString from 'magic-string';

type AstNode = { type: string; start: number; end: number } & Record<string, unknown>;

type ParseWithLang = (input: string, options: { lang: 'js' | 'ts' }) => unknown;

function isAstNode(value: unknown): value is AstNode {
    return typeof value === 'object' && value !== null && typeof (value as AstNode).type === 'string';
}

function visit(value: unknown, parent: AstNode | null, callback: (node: AstNode, parent: AstNode | null) => void): void {
    if (Array.isArray(value)) {
        value.forEach((child) => visit(child, parent, callback));
        return;
    }

    if (!isAstNode(value)) {
        return;
    }

    callback(value, parent);
    Object.values(value).forEach((child) => visit(child, value, callback));
}

function isMemberAccess(node: unknown, objectMatches: (object: unknown) => boolean, property: string): boolean {
    return (
        isAstNode(node) &&
        node.type === 'MemberExpression' &&
        node.computed === false &&
        isAstNode(node.property) &&
        node.property.name === property &&
        objectMatches(node.object)
    );
}

function isProcessEnvNodeEnv(node: AstNode): boolean {
    const isProcess = (object: unknown) => isAstNode(object) && object.type === 'Identifier' && object.name === 'process';

    return isMemberAccess(node, (object) => isMemberAccess(object, isProcess, 'env'), 'NODE_ENV');
}

function isWriteTarget(node: AstNode, parent: AstNode | null): boolean {
    if (!parent) {
        return false;
    }

    switch (parent.type) {
        case 'AssignmentExpression':
        case 'AssignmentPattern':
        case 'ForInStatement':
        case 'ForOfStatement':
            return parent.left === node;
        case 'UpdateExpression':
        case 'RestElement':
        case 'ArrayPattern':
            return true;
        case 'UnaryExpression':
            return parent.operator === 'delete';
        default:
            return false;
    }
}

/**
 * @private
 *
 * In the bundled dev mode, vite-plugin-node-polyfills injects `import process from '.../shims/process'` into the
 * source modules. Vite replaces `process.env.NODE_ENV` only afterwards and skips it then, because `process` is no global
 * identifier anymore. This plugin replaces it before the polyfills are injected, like it happens in production builds.
 */
export default function NodeEnvDefinePlugin(): Plugin {
    let nodeEnv = JSON.stringify('development');

    return {
        name: 'shopware-vite-plugin-node-env-define',

        apply: 'serve',

        enforce: 'pre',

        configResolved(config) {
            nodeEnv =
                (config.define?.['process.env.NODE_ENV'] as string | undefined) ??
                JSON.stringify(process.env.NODE_ENV || config.mode);
        },

        transform: {
            filter: {
                id: { exclude: /\/node_modules\// },
                code: 'process.env.NODE_ENV',
            },
            handler(code, id) {
                // Typed with Vite 6, but the bundled dev mode only exists in Vite 8, whose parser also reads the
                // TypeScript sources this `pre` plugin gets
                const parse = this.parse as unknown as ParseWithLang;
                const lang = /\.[cm]?tsx?$/.test(id.split('?')[0]) ? 'ts' : 'js';
                const magicString = new MagicString(code);

                visit(parse.call(this, code, { lang }), null, (node, parent) => {
                    if (isProcessEnvNodeEnv(node) && !isWriteTarget(node, parent)) {
                        magicString.overwrite(node.start, node.end, nodeEnv);
                    }
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
