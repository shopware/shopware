/**
 * @sw-package framework
 */

/**
 * Declares every macro and helper name the transform understands, together with the uniform rules
 * the analyzer enforces for them.
 *
 * One collection pass turns top-level statements into `MacroCallEntry` items (bare statements and
 * declaration initializers alike, through transparent TS wrappers), and one assertion pass applies
 * the declarative rules: allowed modes, per-group multiplicity, required presence, and outright
 * rejection. Macro-specific semantics that do not fit a table - marker entry extraction, props
 * hoisting, callback input replacement - stay in their own modules and read the entries through the
 * accessors below.
 */

import type { CallExpression, Statement } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { getNodeRange, unwrapTransparentMacroExpression } from './utils';

type MacroName =
    | 'defineProps'
    | 'withDefaults'
    | 'defineEmits'
    | 'defineSlots'
    | 'defineExpose'
    | 'defineOptions'
    | 'defineModel'
    | 'swDefinePublic'
    | 'swDefineOverride'
    | 'useSwContext'
    | 'useSwProps'
    | 'useSwPreviousState';

/**
 * One top-level macro/helper call found in a Shopware setup block.
 *
 * `form` distinguishes a bare statement (`defineEmits(['save']);`) from a declaration initializer
 * (`const emit = defineEmits(['save']);`).
 */
type MacroCallEntry = {
    name: MacroName;
    call: CallExpression;
    statement: Statement;
    form: 'statement' | 'declaration';
};

type MacroRule = {
    /** Modes whose analysis accepts this name at the top level. Empty = rejected everywhere. */
    modes: ShopwareSetupMode[];
    /** Error for a top-level call in a mode not listed in `modes` (or for empty `modes`). */
    wrongModeMessage: string;
    /** Multiplicity group; defaults to the macro name. defineProps/withDefaults share `props`. */
    group?: string;
    /** Error for the second top-level call of the same group. Omit for no multiplicity limit. */
    duplicateMessage?: string;
    /** Modes that require exactly one top-level call of this name. */
    requiredInModes?: ShopwareSetupMode[];
    requiredMessage?: string;
    /** Wrong-mode calls of this name are also rejected in nested positions (via the AST walk). */
    rejectAnywhereInWrongMode?: boolean;
    /** Calls of this name outside the top level are rejected (via the AST walk). */
    topLevelOnlyMessage?: string;
    /** Declaration initializers of this name read a setup input (props/emits/slots object). */
    setupInput?: boolean;
    /** Identifier declarations of this name are exposed as private setup state. */
    exposable?: boolean;
    /** Declarations of this name alias a runtime input and are never returned as state. */
    alias?: boolean;
    /** Hoisted to the generated script root, so arguments must not read setup-local bindings. */
    hoistedArguments?: boolean;
};

const MACRO_RULES: Record<MacroName, MacroRule> = {
    defineProps: {
        modes: ['base'],
        wrongModeMessage: 'defineProps() is only supported in base Shopware setup blocks.',
        group: 'props',
        duplicateMessage: 'Only one props declaration macro is allowed in a base Shopware setup block.',
        setupInput: true,
        exposable: true,
        hoistedArguments: true,
    },
    withDefaults: {
        modes: ['base'],
        wrongModeMessage: 'withDefaults() is only supported in base Shopware setup blocks.',
        group: 'props',
        duplicateMessage: 'Only one props declaration macro is allowed in a base Shopware setup block.',
        setupInput: true,
        exposable: true,
        hoistedArguments: true,
    },
    defineEmits: {
        modes: ['base'],
        wrongModeMessage: 'defineEmits() is only supported in base Shopware setup blocks.',
        duplicateMessage: 'Only one defineEmits() call is allowed in a base Shopware setup block.',
        setupInput: true,
        exposable: true,
        hoistedArguments: true,
    },
    defineSlots: {
        modes: ['base'],
        wrongModeMessage: 'defineSlots() is only supported in base Shopware setup blocks.',
        duplicateMessage: 'Only one defineSlots() call is allowed in a base Shopware setup block.',
        setupInput: true,
        exposable: true,
    },
    defineExpose: {
        modes: ['base'],
        wrongModeMessage: 'defineExpose() is only supported in base Shopware setup blocks.',
        duplicateMessage: 'Only one defineExpose() call is allowed in a base Shopware setup block.',
    },
    defineOptions: {
        modes: ['base'],
        wrongModeMessage: 'defineOptions() is only supported in base Shopware setup blocks.',
        duplicateMessage: 'Only one defineOptions() call is allowed in a base Shopware setup block.',
        hoistedArguments: true,
    },
    defineModel: {
        modes: [],
        wrongModeMessage: 'Vue macro defineModel() is not supported inside Shopware setup blocks.',
    },
    swDefinePublic: {
        modes: ['base'],
        wrongModeMessage: [
            'swDefinePublic() is a Shopware setup compile-time macro for base components.',
            'It declares which setup bindings are public and may be replaced by overrides.',
            'Override components must use swDefineOverride() to declare replacement bindings instead.',
        ].join(' '),
        duplicateMessage: 'Only one swDefinePublic() call is allowed in a base Shopware setup block.',
        topLevelOnlyMessage: 'swDefinePublic() must be called once at the top level of a base Shopware setup block.',
    },
    swDefineOverride: {
        modes: ['override'],
        wrongModeMessage: [
            'swDefineOverride() is a Shopware setup compile-time macro for override components.',
            'It declares which base component bindings this override replaces.',
            'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
        ].join(' '),
        duplicateMessage: 'Only one swDefineOverride() call is allowed in an override Shopware setup block.',
        requiredInModes: [
            'override',
        ],
        requiredMessage: 'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        topLevelOnlyMessage:
            'swDefineOverride() must be called once at the top level of an override Shopware setup block.',
    },
    useSwContext: {
        modes: [
            'base',
            'override',
        ],
        wrongModeMessage: 'useSwContext() is only supported inside Shopware setup blocks.',
        alias: true,
    },
    useSwProps: {
        modes: ['override'],
        wrongModeMessage:
            "useSwProps() is only supported in override Shopware setup blocks. Base components must use Vue's defineProps() macro instead.",
        rejectAnywhereInWrongMode: true,
        setupInput: true,
        alias: true,
    },
    useSwPreviousState: {
        modes: ['override'],
        wrongModeMessage: 'useSwPreviousState() is only supported in override Shopware setup blocks.',
        rejectAnywhereInWrongMode: true,
        alias: true,
    },
};

const MACRO_NAMES = Object.keys(MACRO_RULES) as MacroName[];

function isMacroName(name: string): name is MacroName {
    return name in MACRO_RULES;
}

/**
 * Returns the registry macro call a node represents, through transparent TS wrappers.
 *
 * Matches `defineEmits(['save'])` as well as wrapped forms such as `defineOptions({...}) as void`.
 */
function getMacroCall(node: unknown): { name: MacroName; call: CallExpression } | null {
    const call = unwrapTransparentMacroExpression(node as Parameters<typeof unwrapTransparentMacroExpression>[0]);

    if (call?.type === 'CallExpression' && call.callee.type === 'Identifier' && isMacroName(call.callee.name)) {
        return {
            name: call.callee.name,
            call,
        };
    }

    return null;
}

/**
 * Collects the registry macro calls one top-level statement contains.
 *
 * Only the forms Vue compiler-sfc treats as setup macros are collected - bare statements and
 * declaration initializers. Nested calls stay untouched, like native setup.
 */
function collectMacroCallEntries(statement: Statement): MacroCallEntry[] {
    // e.g. `defineEmits(['save']);` or `swDefinePublic({ count });`
    if (statement.type === 'ExpressionStatement') {
        const macro = getMacroCall(statement.expression);

        return macro
            ? [
                  {
                      ...macro,
                      statement,
                      form: 'statement',
                  },
              ]
            : [];
    }

    // e.g. `const emit = defineEmits(['save']);`
    if (statement.type === 'VariableDeclaration') {
        return statement.declarations.flatMap((declaration) => {
            const macro = getMacroCall(declaration.init);

            return macro
                ? [
                      {
                          ...macro,
                          statement,
                          form: 'declaration' as const,
                      },
                  ]
                : [];
        });
    }

    return [];
}

/**
 * Applies the declarative registry rules to the collected top-level entries.
 *
 * Rules run per macro in registry order (Vue macros before Shopware markers), each checking wrong
 * mode first, then multiplicity, then required presence.
 */
function assertMacroRules(entries: MacroCallEntry[], mode: ShopwareSetupMode, scriptOffset: number): void {
    MACRO_NAMES.forEach((name) => {
        const rule = MACRO_RULES[name];
        const named = entries.filter((entry) => entry.name === name);

        if (!rule.modes.includes(mode) && named.length > 0) {
            throw new ShopwareSetupTransformError(
                rule.wrongModeMessage,
                scriptOffset + getNodeRange(named[0].call, scriptOffset).start,
            );
        }
    });

    MACRO_NAMES.forEach((name) => {
        const rule = MACRO_RULES[name];

        if (rule.duplicateMessage) {
            const group = rule.group ?? name;
            const grouped = entries.filter((entry) => (MACRO_RULES[entry.name].group ?? entry.name) === group);

            if (grouped.length > 1) {
                throw new ShopwareSetupTransformError(
                    rule.duplicateMessage,
                    scriptOffset + getNodeRange(grouped[1].call, scriptOffset).start,
                );
            }
        }
    });

    MACRO_NAMES.forEach((name) => {
        const rule = MACRO_RULES[name];

        if (rule.requiredInModes?.includes(mode) && !entries.some((entry) => entry.name === name)) {
            throw new ShopwareSetupTransformError(String(rule.requiredMessage), scriptOffset);
        }
    });
}

/**
 * Returns the entries for one macro name, optionally restricted to one form.
 */
function getMacroEntries(entries: MacroCallEntry[], name: MacroName, form?: MacroCallEntry['form']): MacroCallEntry[] {
    return entries.filter((entry) => entry.name === name && (!form || entry.form === form));
}

/**
 * Returns the single entry for one macro name, or null.
 *
 * Only meaningful after `assertMacroRules` ran: the multiplicity rules guarantee at most one entry
 * per limited macro, so consumers can drop the array handling.
 */
function getMacroEntry(entries: MacroCallEntry[], name: MacroName, form?: MacroCallEntry['form']): MacroCallEntry | null {
    return getMacroEntries(entries, name, form)[0] ?? null;
}

/**
 * Returns the entries of one multiplicity group in source order (e.g. `props` for
 * defineProps/withDefaults).
 */
function getMacroGroupEntries(entries: MacroCallEntry[], group: string): MacroCallEntry[] {
    return entries.filter((entry) => (MACRO_RULES[entry.name].group ?? entry.name) === group);
}

/**
 * Returns the single entry of one multiplicity group, or null. Same post-assert contract as
 * `getMacroEntry`.
 */
function getMacroGroupEntry(entries: MacroCallEntry[], group: string): MacroCallEntry | null {
    return getMacroGroupEntries(entries, group)[0] ?? null;
}

/** Names whose declaration initializers read a setup input (`const props = defineProps()`). */
function getSetupInputMacroNames(): Set<string> {
    return new Set(MACRO_NAMES.filter((name) => MACRO_RULES[name].setupInput));
}

/** Names whose identifier declarations are exposed as private setup state. */
function getExposableSetupMacroNames(): Set<string> {
    return new Set(MACRO_NAMES.filter((name) => MACRO_RULES[name].exposable));
}

/** Names whose declarations alias a runtime input in the given mode. */
function getRuntimeInputAliasNames(mode: ShopwareSetupMode): Set<string> {
    return new Set(MACRO_NAMES.filter((name) => MACRO_RULES[name].alias && MACRO_RULES[name].modes.includes(mode)));
}

/** All registry names; user bindings must not shadow them. */
function getReservedHelperNames(): Set<string> {
    return new Set(MACRO_NAMES);
}

/** Wrong-mode names that the AST walk must also reject in nested positions, with their messages. */
function getWrongModeWalkChecks(mode: ShopwareSetupMode): { name: MacroName; message: string }[] {
    return MACRO_NAMES.filter(
        (name) => MACRO_RULES[name].rejectAnywhereInWrongMode && !MACRO_RULES[name].modes.includes(mode),
    ).map((name) => ({
        name,
        message: MACRO_RULES[name].wrongModeMessage,
    }));
}

/** Names the AST walk must reject outside the top level, with their messages. */
function getTopLevelOnlyWalkChecks(): { name: MacroName; message: string }[] {
    return MACRO_NAMES.filter((name) => MACRO_RULES[name].topLevelOnlyMessage).map((name) => ({
        name,
        message: String(MACRO_RULES[name].topLevelOnlyMessage),
    }));
}

/** Names hoisted to the generated script root whose arguments must not read setup locals. */
function getHoistedArgumentMacroNames(): MacroName[] {
    return MACRO_NAMES.filter((name) => MACRO_RULES[name].hoistedArguments);
}

export {
    type MacroCallEntry,
    type MacroName,
    assertMacroRules,
    collectMacroCallEntries,
    getExposableSetupMacroNames,
    getHoistedArgumentMacroNames,
    getMacroEntries,
    getMacroEntry,
    getMacroGroupEntries,
    getMacroGroupEntry,
    getReservedHelperNames,
    getRuntimeInputAliasNames,
    getSetupInputMacroNames,
    getTopLevelOnlyWalkChecks,
    getWrongModeWalkChecks,
};
