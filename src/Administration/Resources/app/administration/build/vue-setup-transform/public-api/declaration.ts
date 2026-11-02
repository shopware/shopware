/**
 * @sw-package framework
 */

/**
 * Finds the `definePublicComponent()` declaration in a mini-module's `index.ts` that loads a given SFC,
 * and reads its `public` lists.
 *
 * Only an SFC inside the module's `src/` that is the target of a `load: () => import('./src/...')` has a
 * declaration. Any other SFC, also a private one next to it in `src/`, has none.
 */

import path from 'node:path';
import { parse } from '@babel/parser';
import type { Node as BabelNode, ObjectExpression, ObjectProperty } from '@babel/types';
import { walk } from '../script-analyzer/utils';
import { ShopwareSetupTransformError } from '../utils/transform-error';

/**
 * Reads a file the check depends on, or returns null when it does not exist.
 * Injected so specs can hand the check an `index.ts` without writing it to disk.
 */
type ReadFile = (file: string) => string | null;

/** The kinds a native-setup component lists under `public` in its `index.ts`. */
const NATIVE_SETUP_API_KINDS = [
    'props',
    'emits',
    'slots',
    'blocks',
    'setup',
] as const;

type NativeSetupApiKind = (typeof NATIVE_SETUP_API_KINDS)[number];

/** One list of names per kind; a kind `index.ts` leaves out is an empty list. */
type PublicApiLists = Record<NativeSetupApiKind, string[]>;

/**
 * The declaration of one native-setup component in its mini-module's `index.ts`.
 */
type PublicComponentDeclaration = {
    indexFile: string;
    lists: PublicApiLists;
};

function stripQuery(filename: string): string {
    return filename.split(/[?#]/, 1)[0];
}

function isNativeSetupApiKind(name: string): name is NativeSetupApiKind {
    return (NATIVE_SETUP_API_KINDS as readonly string[]).includes(name);
}

/**
 * A diagnostic about `index.ts` itself. Its position is a line in `index.ts`, so the message carries it:
 * the error's own offset always points into the SFC being transformed.
 */
function declarationError(indexFile: string, node: BabelNode, message: string): ShopwareSetupTransformError {
    const line = node.loc?.start.line ?? 1;
    const column = (node.loc?.start.column ?? 0) + 1;

    return new ShopwareSetupTransformError(`${message}\n\n${indexFile}:${line}:${column}`);
}

function propertyName(property: ObjectProperty): string | null {
    if (property.computed) {
        return null;
    }

    if (property.key.type === 'Identifier') {
        return property.key.name;
    }

    return property.key.type === 'StringLiteral' ? property.key.value : null;
}

function findProperty(object: ObjectExpression, name: string): ObjectProperty | null {
    return (
        object.properties.find(
            (property): property is ObjectProperty => property.type === 'ObjectProperty' && propertyName(property) === name,
        ) ?? null
    );
}

/**
 * The path a `load: () => import('./src/...')` imports, or null for any other shape.
 */
function loadedPath(declaration: ObjectExpression): string | null {
    const load = findProperty(declaration, 'load')?.value;

    if (load?.type !== 'ArrowFunctionExpression' || load.body.type !== 'CallExpression') {
        return null;
    }

    const [specifier] = load.body.arguments;

    if (load.body.callee.type !== 'Import' || specifier?.type !== 'StringLiteral') {
        return null;
    }

    return specifier.value;
}

/**
 * Reads the `public` lists. They must be literal, so the build can compare them without running `index.ts`.
 */
function readPublicLists(declaration: ObjectExpression, indexFile: string): PublicApiLists {
    const lists: PublicApiLists = { props: [], emits: [], slots: [], blocks: [], setup: [] };
    const publicApi = findProperty(declaration, 'public')?.value;

    if (!publicApi) {
        return lists;
    }

    if (publicApi.type !== 'ObjectExpression') {
        throw declarationError(
            indexFile,
            publicApi,
            'The public API of a native-setup component must be an object literal.',
        );
    }

    publicApi.properties.forEach((property) => {
        const kind = property.type === 'ObjectProperty' ? propertyName(property) : null;

        if (property.type !== 'ObjectProperty' || kind === null) {
            throw declarationError(indexFile, property, 'The public API of a native-setup component takes plain keys only.');
        }

        if (!isNativeSetupApiKind(kind)) {
            throw declarationError(
                indexFile,
                property,
                `A native-setup component has no public "${kind}". ` +
                    `Its public API lists ${NATIVE_SETUP_API_KINDS.join(', ')}; ` +
                    'list the bindings of swDefinePublic() under "setup".',
            );
        }

        const list = property.value;

        if (list.type !== 'ArrayExpression') {
            throw declarationError(indexFile, list, `public.${kind} must be an array of string literals.`);
        }

        lists[kind] = list.elements.map((element) => {
            if (element?.type !== 'StringLiteral') {
                throw declarationError(indexFile, element ?? list, `public.${kind} must be an array of string literals.`);
            }

            return element.value;
        });
    });

    return lists;
}

/**
 * The `index.ts` of the mini-module whose `src/` holds the file, or null when the file is in no mini-module.
 */
function findIndexFile(file: string, readFile: ReadFile): { indexFile: string; source: string } | null {
    for (let directory = path.dirname(file); directory !== path.dirname(directory); directory = path.dirname(directory)) {
        if (path.basename(directory) !== 'src') {
            continue;
        }

        const indexFile = path.join(path.dirname(directory), 'index.ts');
        const source = readFile(indexFile);

        if (source?.includes('definePublicComponent(')) {
            return { indexFile, source };
        }
    }

    return null;
}

/**
 * Returns the `definePublicComponent()` declaration that loads this SFC, or null when nothing declares it.
 *
 * @example
 * findPublicComponentDeclaration('/m/sw-example/src/sw-example.vue', readFile);
 * // → { indexFile: '/m/sw-example/index.ts', lists: { props: ['label'], ..., setup: ['reset'] } }
 */
function findPublicComponentDeclaration(filename: string, readFile: ReadFile): PublicComponentDeclaration | null {
    const file = path.resolve(stripQuery(filename));
    const found = findIndexFile(file, readFile);

    if (!found) {
        return null;
    }

    const { indexFile, source } = found;
    const ast = parse(source, { sourceType: 'module', plugins: ['typescript'] });
    let declaration: PublicComponentDeclaration | null = null;

    walk(ast.program, (node) => {
        if (
            declaration ||
            node.type !== 'CallExpression' ||
            node.callee.type !== 'Identifier' ||
            node.callee.name !== 'definePublicComponent' ||
            node.arguments[0]?.type !== 'ObjectExpression'
        ) {
            return;
        }

        const loaded = loadedPath(node.arguments[0]);

        if (loaded !== null && path.resolve(path.dirname(indexFile), loaded) === file) {
            declaration = { indexFile, lists: readPublicLists(node.arguments[0], indexFile) };
        }
    });

    return declaration;
}

/**
 * @private
 */
export {
    type NativeSetupApiKind,
    type PublicApiLists,
    type PublicComponentDeclaration,
    type ReadFile,
    NATIVE_SETUP_API_KINDS,
    findPublicComponentDeclaration,
};
