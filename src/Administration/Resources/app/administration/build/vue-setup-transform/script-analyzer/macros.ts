/**
 * @sw-package framework
 */

import type { CallExpression, Node as BabelNode, Statement } from '@babel/types';
import type { ShopwareSetupMode } from '../naming';
import { OVERRIDE_LOCAL_STATE_KEY } from '../naming';
import { ShopwareSetupTransformError, nodeRange } from '../utils/transform-error';

type MacroRule = {
    modes: ShopwareSetupMode[];
    wrongMode: string | Record<ShopwareSetupMode, string>;
    /** A Vue macro: importing its name from 'vue' is allowed, like in native `<script setup>`. */
    vue?: true;
    /** Wrong-mode calls are rejected at any depth, not only at the top level. */
    nested?: true;
    /**
     * How `const x = macro()` is classified: `props` keeps an identifier as state and leaves a
     * destructure to Vue's reactive props destructure; `alias` names a callback input, never state.
     */
    binding?: 'props' | 'alias';
};

const MACRO_TABLE = {
    defineProps: {
        vue: true,
        modes: ['base'],
        wrongMode: 'defineProps() is only supported in base Shopware setup blocks.',
        binding: 'props',
    },
    withDefaults: {
        vue: true,
        modes: ['base'],
        wrongMode: 'withDefaults() is only supported in base Shopware setup blocks.',
        binding: 'props',
    },
    defineEmits: {
        vue: true,
        modes: ['base'],
        wrongMode: 'defineEmits() is only supported in base Shopware setup blocks.',
    },
    defineSlots: {
        vue: true,
        modes: ['base'],
        wrongMode: 'defineSlots() is only supported in base Shopware setup blocks.',
    },
    defineExpose: {
        vue: true,
        modes: [],
        wrongMode: {
            base: [
                'defineExpose() is not supported inside Shopware setup blocks.',
                'Use swDefinePublic({ ... }) instead, which will call it for you automatically.',
            ].join(' '),
            override: [
                'defineExpose() is not supported inside Shopware setup blocks.',
                'The base component owns the exposed API and its swDefinePublic() entries generate it, so a binding',
                'this override replaces is already what a parent reads.',
                'Declare replacement bindings with swDefineOverride({ ... }) instead.',
            ].join(' '),
        },
    },
    defineOptions: {
        vue: true,
        modes: ['base'],
        wrongMode: 'defineOptions() is only supported in base Shopware setup blocks.',
    },
    defineModel: {
        vue: true,
        modes: [],
        wrongMode: 'Vue macro defineModel() is not supported inside Shopware setup blocks.',
    },
    swDefinePublic: {
        modes: ['base'],
        wrongMode: [
            'swDefinePublic() is a Shopware setup compile-time macro for base components.',
            'It declares which setup bindings are public and may be replaced by overrides.',
            'Override components must use swDefineOverride() to declare replacement bindings instead.',
        ].join(' '),
    },
    swDefineOverride: {
        modes: ['override'],
        wrongMode: [
            'swDefineOverride() is a Shopware setup compile-time macro for override components.',
            'It declares which base component bindings this override replaces.',
            'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
        ].join(' '),
    },
    useSwContext: {
        modes: ['override'],
        wrongMode:
            "useSwContext() is only supported in override Shopware setup blocks. A base component runs as a native <script setup>, so use Vue's own APIs instead - useAttrs(), useSlots(), useTemplateRef(), or defineEmits() for the emitter.",
        nested: true,
        binding: 'alias',
    },
    useSwProps: {
        modes: ['override'],
        wrongMode:
            "useSwProps() is only supported in override Shopware setup blocks. Base components must use Vue's defineProps() macro instead.",
        nested: true,
        binding: 'alias',
    },
    useSwPreviousState: {
        modes: ['override'],
        wrongMode: 'useSwPreviousState() is only supported in override Shopware setup blocks.',
        nested: true,
        binding: 'alias',
    },
} satisfies Record<string, MacroRule>;

type MacroName = keyof typeof MACRO_TABLE;

const MACROS: Record<MacroName, MacroRule> = MACRO_TABLE;

const MACRO_NAMES = Object.keys(MACROS) as MacroName[];

const MARKERS = {
    base: {
        name: 'swDefinePublic',
        entryType: 'public',
        duplicate: 'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
        statementOnly:
            'swDefinePublic() is a compile-time marker and returns nothing. Call it as a statement at the ' +
            'top level instead of assigning its result.',
        // Required even when nothing is public, so a reader can tell the file is an extension point and the
        // author states its extension surface deliberately.
        missing:
            'A base Shopware setup component must declare its extension surface. Add swDefinePublic({ ... }) ' +
            'at the top level - pass an empty object if no binding is public.',
    },
    override: {
        name: 'swDefineOverride',
        entryType: 'override',
        duplicate: 'Only one swDefineOverride() call is allowed in an override Shopware setup block.',
        statementOnly:
            'swDefineOverride() is a compile-time marker and returns nothing. Call it as a statement at the ' +
            'top level instead of assigning its result.',
        missing: 'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
    },
} as const;

/**
 * A top-level macro call: a bare statement, or the initializer of a declaration.
 */
type MacroCall = {
    name: MacroName;
    call: CallExpression;
    statement: Statement;
    form: 'statement' | 'declaration';
};

function wrongModeMessage(name: MacroName, mode: ShopwareSetupMode): string {
    const message = MACROS[name].wrongMode;

    return typeof message === 'string' ? message : message[mode];
}

function isMacroName(name: string): name is MacroName {
    return Object.prototype.hasOwnProperty.call(MACROS, name);
}

/**
 * Returns the macro a node calls, looking through the TS wrappers Vue also looks through.
 */
function getMacroCall(node: BabelNode | null | undefined): { name: MacroName; call: CallExpression } | null {
    while (
        node?.type === 'TSAsExpression' ||
        node?.type === 'TSSatisfiesExpression' ||
        node?.type === 'TSTypeAssertion' ||
        node?.type === 'TSNonNullExpression' ||
        node?.type === 'ParenthesizedExpression'
    ) {
        node = node.expression;
    }

    if (node?.type === 'CallExpression' && node.callee.type === 'Identifier' && isMacroName(node.callee.name)) {
        return { name: node.callee.name, call: node };
    }

    return null;
}

function collectMacroCalls(statement: Statement): MacroCall[] {
    if (statement.type === 'ExpressionStatement') {
        const macro = getMacroCall(statement.expression);

        return macro ? [{ ...macro, statement, form: 'statement' }] : [];
    }

    if (statement.type === 'VariableDeclaration') {
        return statement.declarations.flatMap((declaration) => {
            const macro = getMacroCall(declaration.init);

            return macro ? [{ ...macro, statement, form: 'declaration' as const }] : [];
        });
    }

    return [];
}

/**
 * Checks the collected macros and returns the mode's marker call. Wrong-mode macros are reported first,
 * so a base file that calls swDefineOverride() is not told to add swDefinePublic().
 */
function assertMacroRules(calls: MacroCall[], mode: ShopwareSetupMode, offset: number): MacroCall {
    MACRO_NAMES.forEach((name) => {
        const call = calls.find((entry) => entry.name === name);
        if (call && !MACROS[name].modes.includes(mode)) {
            throw new ShopwareSetupTransformError(wrongModeMessage(name, mode), nodeRange(call.call, offset));
        }
    });

    const marker = MARKERS[mode];
    const markerCalls = calls.filter((entry) => entry.name === marker.name);
    const declaration = markerCalls.find((entry) => entry.form === 'declaration');

    if (markerCalls.length > 1) {
        throw new ShopwareSetupTransformError(marker.duplicate, nodeRange(markerCalls[1].call, offset));
    }

    if (declaration) {
        throw new ShopwareSetupTransformError(marker.statementOnly, nodeRange(declaration.call, offset));
    }

    if (markerCalls.length === 0) {
        throw new ShopwareSetupTransformError(marker.missing, offset);
    }

    return markerCalls[0];
}

/**
 * Extracts the shorthand names of a marker call such as `swDefinePublic({ a, b })`. Only shorthand is
 * allowed, so a public key always equals the local binding it exposes.
 */
function extractMarkerEntries(marker: MacroCall, mode: ShopwareSetupMode, offset: number): string[] {
    const { name, entryType } = MARKERS[mode];
    const [argument] = marker.call.arguments;

    if (marker.call.arguments.length !== 1 || argument.type !== 'ObjectExpression') {
        throw new ShopwareSetupTransformError(
            `${name}() requires exactly one object-literal argument.`,
            nodeRange(marker.call, offset),
        );
    }

    const seen = new Set<string>();

    return argument.properties.map((property) => {
        if (property.type === 'SpreadElement') {
            throw new ShopwareSetupTransformError(
                `Spread properties are not supported inside ${name}().`,
                nodeRange(property, offset),
            );
        }

        if (property.type !== 'ObjectProperty') {
            throw new ShopwareSetupTransformError(
                `${name}() only supports plain object properties.`,
                nodeRange(property, offset),
            );
        }

        if (property.computed || !property.shorthand || property.key.type !== 'Identifier') {
            throw new ShopwareSetupTransformError(
                `${name}() only supports shorthand bindings such as { a, b }. Renaming and string or computed keys (for example { a: b } or { 'a': b }) are not supported.`,
                nodeRange(property, offset),
            );
        }

        const localName = property.key.name;

        if (localName === OVERRIDE_LOCAL_STATE_KEY) {
            throw new ShopwareSetupTransformError(
                `"${localName}" is reserved for Shopware override-private state and cannot be exposed with ${name}().`,
                nodeRange(property, offset),
            );
        }

        if (seen.has(localName)) {
            throw new ShopwareSetupTransformError(
                `Duplicate ${entryType} Shopware setup binding key "${localName}".`,
                nodeRange(property, offset),
            );
        }

        seen.add(localName);

        return localName;
    });
}

/**
 * @private
 */
export {
    type MacroCall,
    type MacroName,
    MACROS,
    MARKERS,
    assertMacroRules,
    collectMacroCalls,
    extractMarkerEntries,
    getMacroCall,
    isMacroName,
    wrongModeMessage,
};
