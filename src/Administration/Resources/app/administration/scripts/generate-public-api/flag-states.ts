/**
 * @sw-package framework
 *
 * The snapshot covers two flag states: `current`, with the major flags off, and `major`, with them on.
 * This file decides in which of the two a piece of source exists.
 */
import ts from 'typescript';
import { isSameFlag, versionOfFlag } from '../feature-flags';

/**
 * @private
 *
 * The flag states in which an element exists.
 */
export interface FlagStates {
    current: boolean;
    major: boolean;
}

/** @private */
export const BOTH_STATES: FlagStates = { current: true, major: true };

/**
 * @private
 *
 * The major flags of one generator run. `majorFlag` is the version flag among them, such as `v6.8.0.0`,
 * and `majorVersion` the release it stands for, `v6.8.0`.
 */
export interface FlagContext {
    majorFlag: string;
    majorVersion: string;
    isMajorFlag(flag: string): boolean;
}

/** `null` when the value depends on something else than the major flags. */
type Truth = boolean | null;

type Truths = Record<keyof FlagStates, Truth>;

const UNKNOWN: Truths = { current: null, major: null };

const FEATURE_OBJECT = /(?:^|\.)\$?feature$/i;

/** @private */
export function createFlagContext(majorFlags: readonly string[]): FlagContext {
    const version = majorFlags.map(versionOfFlag).find((candidate) => candidate !== null);

    if (!version) {
        throw new Error(`None of the major flags is a version flag such as v6.8.0.0: ${majorFlags.join(', ')}`);
    }

    const [major, minor] = version;

    return {
        majorFlag: `v${major}.${minor}.0.0`,
        majorVersion: `v${major}.${minor}.0`,
        isMajorFlag: (flag) => majorFlags.some((majorFlag) => isSameFlag(majorFlag, flag)),
    };
}

/** @private */
export function inBothStates(a: FlagStates, b: FlagStates): FlagStates {
    return { current: a.current && b.current, major: a.major && b.major };
}

/** @private */
export function inAnyState(a: FlagStates, b: FlagStates): FlagStates {
    return { current: a.current || b.current, major: a.major || b.major };
}

function not(value: Truth): Truth {
    return value === null ? null : !value;
}

function and(a: Truth, b: Truth): Truth {
    if (a === false || b === false) {
        return false;
    }

    return a === null || b === null ? null : true;
}

function or(a: Truth, b: Truth): Truth {
    return not(and(not(a), not(b)));
}

function combine(a: Truths, b: Truths, operator: (a: Truth, b: Truth) => Truth): Truths {
    return { current: operator(a.current, b.current), major: operator(a.major, b.major) };
}

function featureCheck(node: ts.CallExpression, flags: FlagContext): Truths {
    const [flag] = node.arguments;
    const isFeatureCheck =
        ts.isPropertyAccessExpression(node.expression) &&
        node.expression.name.text === 'isActive' &&
        FEATURE_OBJECT.test(node.expression.expression.getText()) &&
        node.arguments.length === 1 &&
        ts.isStringLiteralLike(flag);

    if (!isFeatureCheck || !flags.isMajorFlag(flag.text)) {
        return UNKNOWN;
    }

    return { current: false, major: true };
}

/**
 * @private
 *
 * Evaluates the feature checks of a condition per flag state. Only `feature.isActive('<major flag>')` with `!`,
 * `&&` and `||` is decided, everything else may be true in both states.
 */
export function conditionTruths(node: ts.Expression, flags: FlagContext): Truths {
    if (ts.isParenthesizedExpression(node)) {
        return conditionTruths(node.expression, flags);
    }

    if (ts.isPrefixUnaryExpression(node) && node.operator === ts.SyntaxKind.ExclamationToken) {
        const { current, major } = conditionTruths(node.operand, flags);

        return { current: not(current), major: not(major) };
    }

    if (ts.isBinaryExpression(node) && node.operatorToken.kind === ts.SyntaxKind.AmpersandAmpersandToken) {
        return combine(conditionTruths(node.left, flags), conditionTruths(node.right, flags), and);
    }

    if (ts.isBinaryExpression(node) && node.operatorToken.kind === ts.SyntaxKind.BarBarToken) {
        return combine(conditionTruths(node.left, flags), conditionTruths(node.right, flags), or);
    }

    return ts.isCallExpression(node) ? featureCheck(node, flags) : UNKNOWN;
}

/**
 * @private
 *
 * The same for a condition given as text, such as the value of a `v-if`.
 */
export function expressionTruths(expression: string, flags: FlagContext): Truths {
    const [statement] = ts.createSourceFile('condition.ts', `(${expression});`, ts.ScriptTarget.Latest, true).statements;

    return statement && ts.isExpressionStatement(statement) ? conditionTruths(statement.expression, flags) : UNKNOWN;
}

/**
 * @private
 *
 * The branches of one `v-if` chain, or of an `if` with its `else`. A branch exists in a flag state unless its
 * own condition is off there, or an earlier branch is certainly taken.
 */
export class ConditionChain {
    private readonly taken: FlagStates = { current: false, major: false };

    public branch(condition: Truths): FlagStates {
        const states = {
            current: !this.taken.current && condition.current !== false,
            major: !this.taken.major && condition.major !== false,
        };

        this.taken.current ||= condition.current === true;
        this.taken.major ||= condition.major === true;

        return states;
    }

    public elseBranch(): FlagStates {
        return this.branch({ current: true, major: true });
    }
}

/**
 * @private
 *
 * The marker of an element that exists in one flag state only, without brackets.
 */
export function flagStateMarker(states: FlagStates, flags: FlagContext): string | null {
    if (states.current === states.major) {
        return null;
    }

    return `${states.current ? 'until' : 'from'} ${flags.majorVersion}`;
}
