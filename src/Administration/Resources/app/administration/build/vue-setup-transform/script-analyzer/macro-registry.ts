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
import { absoluteStart, unwrapTransparentMacroExpression } from './utils';

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
    /**
     * Lookup group for macros that are interchangeable to a consumer; defineProps/withDefaults share
     * `props` so `getMacroGroupEntry(entries, 'props')` finds either. Lookup only - multiplicity is
     * counted per name, so do not reach for this to express "only one of these two".
     */
    group?: string;
    /** Error for the second top-level call of this name. Omit for no multiplicity limit. */
    duplicateMessage?: string;
    /** Requires exactly one top-level call of this name in the listed modes, with its error. */
    required?: { modes: ShopwareSetupMode[]; message: string };
    /** Wrong-mode calls of this name are also rejected in nested positions (via the AST walk). */
    rejectAnywhereInWrongMode?: boolean;
    /** Calls of this name outside the top level are rejected (via the AST walk), with this error. */
    topLevelOnly?: { message: string };
    /** Declaration initializers of this name read a setup input (props/emits/slots object). */
    setupInput?: boolean;
    /** Identifier declarations of this name are exposed as private setup state. */
    exposable?: boolean;
    /** Declarations of this name alias a runtime input and are never returned as state. */
    alias?: boolean;
    /** A Vue compiler macro: importing its name is accepted (from anywhere) and never shadows it. */
    vueBuiltin?: boolean;
};

const MACRO_RULES: Record<MacroName, MacroRule> = {
    defineProps: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'defineProps() is only supported in base Shopware setup blocks.',
        group: 'props',
        setupInput: true,
        exposable: true,
    },
    withDefaults: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'withDefaults() is only supported in base Shopware setup blocks.',
        group: 'props',
        setupInput: true,
        exposable: true,
    },
    defineEmits: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'defineEmits() is only supported in base Shopware setup blocks.',
        setupInput: true,
        exposable: true,
    },
    defineSlots: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'defineSlots() is only supported in base Shopware setup blocks.',
        setupInput: true,
        exposable: true,
    },
    defineExpose: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'defineExpose() is only supported in base Shopware setup blocks.',
    },
    defineOptions: {
        vueBuiltin: true,
        modes: ['base'],
        wrongModeMessage: 'defineOptions() is only supported in base Shopware setup blocks.',
    },
    defineModel: {
        vueBuiltin: true,
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
        topLevelOnly: {
            message: 'swDefinePublic() must be called once at the top level of a base Shopware setup block.',
        },
    },
    swDefineOverride: {
        modes: ['override'],
        wrongModeMessage: [
            'swDefineOverride() is a Shopware setup compile-time macro for override components.',
            'It declares which base component bindings this override replaces.',
            'Base components must use swDefinePublic() to expose overrideable setup bindings instead.',
        ].join(' '),
        duplicateMessage: 'Only one swDefineOverride() call is allowed in an override Shopware setup block.',
        required: {
            modes: ['override'],
            message: 'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        },
        topLevelOnly: {
            message: 'swDefineOverride() must be called once at the top level of an override Shopware setup block.',
        },
    },
    useSwContext: {
        modes: ['override'],
        // Base bodies run as plain `<script setup>`, so there is nothing to bridge: Vue's own composables
        // already give an author the setup context. Only overrides need a helper, because their body runs
        // inside a generated callback that receives the context as a parameter.
        wrongModeMessage:
            "useSwContext() is only supported in override Shopware setup blocks. A base component runs as a native <script setup>, so use Vue's own APIs instead - useAttrs(), useSlots(), useTemplateRef(), or defineEmits() for the emitter.",
        rejectAnywhereInWrongMode: true,
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
 * declaration initializers. Nested calls stay untouched, like native setup. Macros are matched by
 * name and always win, also like native setup: an import of the same name is accepted but never
 * shadows the macro.
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
 * mode first, then multiplicity, then required presence - so an author who both misplaces a macro and
 * duplicates it hears about the misplacement.
 */
function assertMacroRules(entries: MacroCallEntry[], mode: ShopwareSetupMode, scriptOffset: number): void {
    MACRO_NAMES.forEach((name) => {
        const rule = MACRO_RULES[name];
        const named = entries.filter((entry) => entry.name === name);

        if (!rule.modes.includes(mode) && named.length > 0) {
            throw new ShopwareSetupTransformError(rule.wrongModeMessage, absoluteStart(named[0].call, scriptOffset));
        }

        // Multiplicity is per name, not per `group`: the only macros with a duplicate limit are the
        // swDefine* markers, which have no group, and the grouped props macros deliberately leave
        // multiplicity to Vue's own "duplicate defineProps() call" error.
        if (rule.duplicateMessage && named.length > 1) {
            throw new ShopwareSetupTransformError(rule.duplicateMessage, absoluteStart(named[1].call, scriptOffset));
        }

        if (rule.required?.modes.includes(mode) && named.length === 0) {
            throw new ShopwareSetupTransformError(rule.required.message, scriptOffset);
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
 * Returns the first entry of one multiplicity group, or null. (A duplicate would be rejected by Vue's
 * own compiler downstream for the Vue macros, and by assertMacroRules for the swDefine* markers.)
 */
function getMacroGroupEntry(entries: MacroCallEntry[], group: string): MacroCallEntry | null {
    return entries.find((entry) => (MACRO_RULES[entry.name].group ?? entry.name) === group) ?? null;
}

// The derived views below are pure functions of the frozen-in-practice MACRO_RULES constant, so they
// are computed once as module-level constants rather than rebuilt per call. Only the two mode-dependent
// views stay functions.

/** Names whose declaration initializers read a setup input (`const props = defineProps()`). */
const SETUP_INPUT_MACRO_NAMES = new Set<string>(MACRO_NAMES.filter((name) => MACRO_RULES[name].setupInput));

/** Names whose identifier declarations are exposed as private setup state. */
const EXPOSABLE_SETUP_MACRO_NAMES = new Set<string>(MACRO_NAMES.filter((name) => MACRO_RULES[name].exposable));

/** All registry names; user bindings must not shadow them. */
const RESERVED_HELPER_NAMES = new Set<string>(MACRO_NAMES);

/** Vue compiler macro names: their imports are accepted, like native setup, and never shadow the macro. */
const VUE_BUILTIN_MACRO_NAMES = new Set<string>(MACRO_NAMES.filter((name) => MACRO_RULES[name].vueBuiltin));

/** Names the AST walk must reject outside the top level, with their messages. */
const TOP_LEVEL_ONLY_WALK_CHECKS: { name: MacroName; message: string }[] = MACRO_NAMES.flatMap((name) => {
    const topLevelOnly = MACRO_RULES[name].topLevelOnly;

    return topLevelOnly ? [{ name, message: topLevelOnly.message }] : [];
});

/** Names whose declarations alias a runtime input in the given mode. */
function getRuntimeInputAliasNames(mode: ShopwareSetupMode): Set<string> {
    return new Set(MACRO_NAMES.filter((name) => MACRO_RULES[name].alias && MACRO_RULES[name].modes.includes(mode)));
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

/**
 * @private
 */
export {
    type MacroCallEntry,
    type MacroName,
    EXPOSABLE_SETUP_MACRO_NAMES,
    RESERVED_HELPER_NAMES,
    SETUP_INPUT_MACRO_NAMES,
    TOP_LEVEL_ONLY_WALK_CHECKS,
    VUE_BUILTIN_MACRO_NAMES,
    assertMacroRules,
    collectMacroCallEntries,
    getMacroEntries,
    getMacroGroupEntry,
    getRuntimeInputAliasNames,
    getWrongModeWalkChecks,
};
