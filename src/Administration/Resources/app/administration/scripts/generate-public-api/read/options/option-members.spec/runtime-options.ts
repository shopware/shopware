/**
 * @sw-package framework
 *
 * The member names of a component as its options object holds them once the source has run: spreads are
 * applied and helpers such as `mapPropertyErrors` have returned their members. The runtime spec compares them
 * with the names the generator reads from the syntax.
 */
import path from 'node:path';
import type { MemberKind } from '../../../model/members';
import { ADMINISTRATION_ROOT } from '../../../source/checkout';

type Options = Record<string, unknown>;

/**
 * @private
 *
 * The kinds of members whose names the options object holds.
 */
export const OPTION_KINDS = [
    'mixin',
    'inject',
    'provide',
    'prop',
    'emit',
    'data',
    'computed',
    'method',
] as const satisfies readonly MemberKind[];

/** @private */
export type OptionKind = (typeof OPTION_KINDS)[number];

/** @private */
export type RuntimeNames = Record<OptionKind, string[] | null>;

function namesOf(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map(String);
    }

    return typeof value === 'object' && value !== null ? Object.keys(value) : [];
}

/** Stands for `this` and for everything read from it, so `data()` and `provide()` run without an instance. */
function anything(): unknown {
    return new Proxy(() => anything(), {
        get: (_target, key) => (key === Symbol.toPrimitive ? () => '' : anything()),
    });
}

/** The keys a function such as `data()` returns, `null` when it needs more than a stand-in for `this`. */
function returnedNames(value: unknown): string[] | null {
    if (typeof value !== 'function') {
        return namesOf(value);
    }

    try {
        return namesOf((value as (this: unknown) => unknown).call(anything()));
    } catch {
        return null;
    }
}

/**
 * @private
 *
 * The names per kind, `null` for a kind whose function did not run. The mixins of a component are objects
 * without a name, so they are counted.
 */
export function runtimeNames(options: Options): RuntimeNames {
    return {
        mixin: namesOf(options.mixins).map((_mixin, position) => `#${position}`),
        inject: namesOf(options.inject),
        provide: returnedNames(options.provide),
        prop: namesOf(options.props),
        emit: namesOf(options.emits),
        data: returnedNames(options.data),
        computed: namesOf(options.computed),
        method: namesOf(options.methods),
    };
}

/**
 * @private
 *
 * Runs a source file of the Administration and returns what it exports.
 */
export function loadSource(source: string): { default?: unknown } {
    // eslint-disable-next-line @typescript-eslint/no-require-imports -- the spec reads the paths from the registrations
    return require(path.join(ADMINISTRATION_ROOT, source)) as { default?: unknown };
}

/**
 * @private
 *
 * The options object the source file exports, as the component factory gets it.
 */
export function loadOptions(source: string): Options {
    const { default: options } = loadSource(source);

    if (typeof options !== 'object' || options === null) {
        throw new Error(`${source} exports no options object.`);
    }

    return options as Options;
}
