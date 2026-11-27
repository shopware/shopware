/**
 * @sw-package framework
 *
 * Decides in which flag states a piece of source runs or renders, from the feature checks in its conditions.
 */
import ts from 'typescript';
import { BOTH_STATES, inBothStates, perState } from '../model/flag-states';
import type { FlagContext, FlagState, FlagStates } from '../model/flag-states';

/** `null` when the value depends on something else than the major flags. */
type Truth = boolean | null;

type Truths = Record<FlagState, Truth>;

const UNKNOWN: Truths = perState(() => null);

const FEATURE_OBJECT = /(?:^|\.)\$?feature$/i;

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
    return perState((state) => operator(a[state], b[state]));
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
 * Evaluates the feature checks of a condition per flag state. Only `feature.isActive('<major flag>')` with `!`,
 * `&&` and `||` is decided, everything else may be true in both states.
 */
function conditionTruths(node: ts.Expression, flags: FlagContext): Truths {
    if (ts.isParenthesizedExpression(node)) {
        return conditionTruths(node.expression, flags);
    }

    if (ts.isPrefixUnaryExpression(node) && node.operator === ts.SyntaxKind.ExclamationToken) {
        const operand = conditionTruths(node.operand, flags);

        return perState((state) => not(operand[state]));
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
    private taken: FlagStates = perState(() => false);

    public branch(condition: Truths): FlagStates {
        const states = perState((state) => !this.taken[state] && condition[state] !== false);

        this.taken = perState((state) => this.taken[state] || condition[state] === true);

        return states;
    }

    public elseBranch(): FlagStates {
        return this.branch(BOTH_STATES);
    }
}

/**
 * @private
 *
 * The flag states in which the `if` statements around `node` let it run.
 */
export function guardStates(node: ts.Node, flags: FlagContext): FlagStates {
    let states = BOTH_STATES;

    for (let child = node; child.parent !== undefined; child = child.parent) {
        const { parent } = child;

        if (ts.isIfStatement(parent) && child !== parent.expression) {
            const chain = new ConditionChain();
            const thenStates = chain.branch(conditionTruths(parent.expression, flags));

            states = inBothStates(states, child === parent.thenStatement ? thenStates : chain.elseBranch());
        }
    }

    return states;
}
