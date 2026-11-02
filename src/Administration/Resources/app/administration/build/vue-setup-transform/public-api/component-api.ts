/**
 * @sw-package framework
 */

/**
 * Reads what a base SFC declares: its props, emits and slots from Vue's macros, its slots and blocks from
 * the template, and its public setup bindings from `swDefinePublic()`.
 *
 * Type arguments (`defineProps<Props>()`) are resolved with Vue's own type resolver, so a local interface or
 * a type imported by a relative path gives the same names Vue compiles.
 */

import fs from 'node:fs';
import type { CallExpression, File as BabelFile, Node as BabelNode } from '@babel/types';
import { extractRuntimeEmits, resolveTypeElements, type SimpleTypeResolveContext } from '@vue/compiler-sfc';
import { collectMacroCallEntries } from '../script-analyzer/macro-registry';
import { absoluteRange, unwrapTransparentMacroExpression } from '../script-analyzer/utils';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import type { PublicApiLists } from './declaration';

/**
 * Builds the context Vue's type resolver needs, with errors anchored in the SFC.
 */
function createTypeContext(
    ast: BabelFile,
    script: string,
    filename: string,
    scriptOffset: number,
): SimpleTypeResolveContext {
    return {
        filename,
        source: script,
        ast: ast.program.body,
        options: {
            fs: {
                fileExists: (file) => fs.existsSync(file),
                readFile: (file) => fs.readFileSync(file, 'utf8'),
            },
        },
        isCE: false,
        propsTypeDecl: undefined,
        propsRuntimeDefaults: undefined,
        propsDestructuredBindings: {},
        emitsTypeDecl: undefined,
        helper: (key) => key,
        getString: (node) => script.slice(node.start ?? 0, node.end ?? 0),
        error: (message, node) => {
            throw new ShopwareSetupTransformError(
                `Cannot read the type for the index.ts check: ${message}`,
                absoluteRange(node, scriptOffset),
            );
        },
    };
}

function unreadable(node: BabelNode, what: string, scriptOffset: number): ShopwareSetupTransformError {
    return new ShopwareSetupTransformError(
        `Cannot read the ${what} of this component for the index.ts check. ` +
            'Declare them with an array, an object literal or a type the file defines or imports by a relative path.',
        absoluteRange(node, scriptOffset),
    );
}

/**
 * The names of a runtime declaration: `['a', 'b']` or `{ a: String, 'b-c': null }`.
 */
function runtimeNames(node: BabelNode, what: string, scriptOffset: number): string[] {
    if (node.type === 'ArrayExpression') {
        return node.elements.map((element) => {
            if (element?.type !== 'StringLiteral') {
                throw unreadable(element ?? node, what, scriptOffset);
            }

            return element.value;
        });
    }

    if (node.type === 'ObjectExpression') {
        return node.properties.map((property) => {
            if (property.type === 'SpreadElement' || property.computed) {
                throw unreadable(property, what, scriptOffset);
            }

            if (property.key.type === 'Identifier') {
                return property.key.name;
            }

            if (property.key.type === 'StringLiteral') {
                return property.key.value;
            }

            throw unreadable(property, what, scriptOffset);
        });
    }

    throw unreadable(node, what, scriptOffset);
}

function typeArgument(call: CallExpression): BabelNode | null {
    return call.typeParameters?.params[0] ?? null;
}

/**
 * `withDefaults(defineProps<P>(), {...})` declares the props of its inner `defineProps()`.
 */
function propsCallOf(entryName: string, call: CallExpression): CallExpression | null {
    if (entryName === 'defineProps') {
        return call;
    }

    const inner = unwrapTransparentMacroExpression(call.arguments[0]);

    return inner?.type === 'CallExpression' ? inner : null;
}

/**
 * The model's prop name: `defineModel('title')` declares `title`, `defineModel()` declares `modelValue`.
 */
function modelName(call: CallExpression): string {
    const [first] = call.arguments;

    return first?.type === 'StringLiteral' ? first.value : 'modelValue';
}

/**
 * Collects the public API a base SFC declares, in the shape `index.ts` lists it.
 *
 * Every prop, emit, slot and block is public, because nothing can hide one from a parent or an override.
 * `defineModel('x')` adds the prop `x` and the emit `update:x`; its `xModifiers` prop comes with it unlisted.
 */
function collectComponentApi(input: {
    ast: BabelFile;
    script: string;
    filename: string;
    scriptOffset: number;
    publicEntries: string[];
    blockNames: string[];
    slotNames: string[];
}): PublicApiLists {
    const { ast, script, filename, scriptOffset } = input;
    const context = createTypeContext(ast, script, filename, scriptOffset);
    const api: PublicApiLists = {
        props: [],
        emits: [],
        slots: [...input.slotNames],
        blocks: [...input.blockNames],
        setup: [...input.publicEntries],
    };

    ast.program.body
        .flatMap((statement) => collectMacroCallEntries(statement))
        .forEach(({ name, call }) => {
            if (name === 'defineProps' || name === 'withDefaults') {
                const propsCall = propsCallOf(name, call);

                if (!propsCall) {
                    throw unreadable(call, 'props', scriptOffset);
                }

                const type = typeArgument(propsCall);
                const runtime = propsCall.arguments[0];

                if (type) {
                    api.props.push(...Object.keys(resolveTypeElements(context, type).props));
                } else if (runtime) {
                    api.props.push(...runtimeNames(runtime, 'props', scriptOffset));
                }

                return;
            }

            if (name === 'defineEmits') {
                const type = typeArgument(call);
                const runtime = call.arguments[0];

                if (type) {
                    api.emits.push(...extractRuntimeEmits({ ...context, emitsTypeDecl: type }));
                } else if (runtime) {
                    api.emits.push(...runtimeNames(runtime, 'emits', scriptOffset));
                }

                return;
            }

            if (name === 'defineSlots') {
                const type = typeArgument(call);

                if (type) {
                    api.slots.push(...Object.keys(resolveTypeElements(context, type).props));
                }

                return;
            }

            if (name === 'defineModel') {
                const model = modelName(call);

                api.props.push(model);
                api.emits.push(`update:${model}`);
            }
        });

    return api;
}

/**
 * @private
 */
export { collectComponentApi };
