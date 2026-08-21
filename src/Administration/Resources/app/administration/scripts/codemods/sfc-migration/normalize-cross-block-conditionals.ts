/**
 * @sw-package framework
 */

/**
 * Reconnects `v-if` chains that the `{% block %}` → `<sw-block>` conversion tore apart.
 *
 * Twig blocks are transparent, `<sw-block>` elements are not: a `v-else`/`v-else-if` whose `v-if`
 * lived in the previous block loses its adjacent sibling and Vue rejects the template. Every such
 * continuation gets an empty guard branch carrying the conditions of all preceding branches, so the
 * chain is adjacent again and still renders nothing when an earlier branch already matched.
 */

import { NodeTypes } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { parseExpression } from '@babel/parser';
import { traverseFast } from '@babel/types';
import type * as t from '@babel/types';
import { elementChildren, isConvertedBlock, parseTemplate } from './template-ast';

const ORPHANED_CONTINUATION = 'orphaned cross-block v-else (no preceding v-if)';
const GUARD_COMMENT = '<!-- Keeps the conditional chain connected across sw-block. -->';
const SIDE_EFFECTING_EXPRESSIONS = new Set([
    'CallExpression',
    'OptionalCallExpression',
    'AssignmentExpression',
    'UpdateExpression',
    'NewExpression',
    'AwaitExpression',
    'YieldExpression',
    'TaggedTemplateExpression',
    'SequenceExpression',
]);

type ConditionDirective = {
    name: 'if' | 'else-if' | 'else';
    expression: string;
};

/** The branches opened so far, and whether a `<sw-block>` boundary sits between them and the next sibling. */
type ConditionChain = {
    conditions: string[];
    crossedBlock: boolean;
};

type NormalizeContext = {
    source: string;
    guards: { offset: number; markup: string }[];
    orphaned: boolean;
    unsafe: boolean;
};

type NormalizeResult = {
    template: string | null;
    blockers: string[];
};

function getConditionDirective(node: ElementNode): ConditionDirective | undefined {
    const prop = node.props.find((candidate): candidate is DirectiveNode => {
        return (
            candidate.type === NodeTypes.DIRECTIVE &&
            (candidate.name === 'if' || candidate.name === 'else-if' || candidate.name === 'else')
        );
    });

    if (!prop) {
        return undefined;
    }

    return {
        name: prop.name as ConditionDirective['name'],
        expression: prop.exp && 'content' in prop.exp ? String(prop.exp.content).trim() : '',
    };
}

/** The chain still open after the last sibling, read backwards; `null` when it is closed or broken. */
function collectTrailingChain(children: ElementNode[]): string[] | null {
    const conditions: string[] = [];

    for (let index = children.length - 1; index >= 0; index -= 1) {
        const condition = getConditionDirective(children[index]);

        if (!condition || condition.name === 'else') {
            return null;
        }

        conditions.unshift(condition.expression);

        if (condition.name === 'if') {
            return conditions;
        }
    }

    return null;
}

/** The continuation branches a block opens with, appended to the conditions it inherited. */
function collectLeadingChain(
    children: ElementNode[],
    inherited: string[],
): { conditions: string[]; endedWithElse: boolean; lastIndex: number } {
    const conditions = [...inherited];
    let endedWithElse = false;
    let lastIndex = -1;

    for (let index = 0; index < children.length; index += 1) {
        const condition = getConditionDirective(children[index]);

        if (!condition || condition.name === 'if') {
            break;
        }

        lastIndex = index;

        if (condition.name === 'else') {
            endedWithElse = true;
            break;
        }

        conditions.push(condition.expression);
    }

    return { conditions, endedWithElse, lastIndex };
}

function insertGuardBefore(node: ElementNode, conditions: string[], context: NormalizeContext): void {
    if (conditions.some((condition) => !isSideEffectFreeCondition(condition))) {
        context.unsafe = true;

        return;
    }

    const offset = node.loc.start.offset;
    const linePrefix = context.source.slice(context.source.lastIndexOf('\n', offset - 1) + 1, offset);
    const indentation = /^\s*$/.test(linePrefix) ? linePrefix : '';
    const expression = conditions
        .map((condition) => `(${condition})`)
        .join(' || ')
        .replace(/"/g, '&quot;');

    context.guards.push({
        offset,
        markup: `<template v-if="${expression}">${GUARD_COMMENT}</template>\n${indentation}`,
    });
}

/**
 * Guard insertion evaluates the preceding conditions again. Only expressions with no callable,
 * assignment, update, allocation, or async evaluation may therefore cross a generated sw-block.
 * Parse failures are conservative: a normal compiler validation pass can still accept the source,
 * but this normalizer must not claim equivalent evaluation timing for syntax it cannot inspect.
 */
function isSideEffectFreeCondition(expression: string): boolean {
    let parsed: t.Expression;

    try {
        parsed = parseExpression(expression, { plugins: ['typescript'] }) as t.Expression;
    } catch {
        return false;
    }

    let safe = true;

    traverseFast(parsed, (node) => {
        if (SIDE_EFFECTING_EXPRESSIONS.has(node.type)) {
            safe = false;
        }
    });

    return safe;
}

function walkElement(node: ElementNode, chain: ConditionChain | null, context: NormalizeContext): ConditionChain | null {
    const condition = getConditionDirective(node);

    if (condition?.name === 'if') {
        walkSiblings(node.children, context);

        return { conditions: [condition.expression], crossedBlock: false };
    }

    if (condition && chain) {
        if (chain.crossedBlock) {
            insertGuardBefore(node, chain.conditions, context);
        }

        walkSiblings(node.children, context);

        return condition.name === 'else'
            ? null
            : {
                  conditions: [
                      ...chain.conditions,
                      condition.expression,
                  ],
                  crossedBlock: chain.crossedBlock,
              };
    }

    walkSiblings(node.children, context);

    return null;
}

function walkConvertedBlock(
    node: ElementNode,
    chain: ConditionChain | null,
    context: NormalizeContext,
): ConditionChain | null {
    const children = elementChildren(node);
    const first = children[0];
    const firstCondition = first ? getConditionDirective(first) : undefined;
    let next: ConditionChain | null;

    if (firstCondition && firstCondition.name !== 'if') {
        if (!chain) {
            context.orphaned = true;

            return null;
        }

        insertGuardBefore(first, chain.conditions, context);

        const leading = collectLeadingChain(children, chain.conditions);
        // A leading chain reaching the last sibling stays open across the next block boundary too.
        const conditions =
            leading.lastIndex === children.length - 1 && !leading.endedWithElse
                ? leading.conditions
                : collectTrailingChain(children);

        next = conditions ? { conditions, crossedBlock: true } : null;
    } else {
        const trailing = collectTrailingChain(children);

        next = trailing
            ? { conditions: trailing, crossedBlock: true }
            : // An empty block interrupts nothing, so an inherited chain survives it.
              chain && children.length === 0
              ? { ...chain, crossedBlock: true }
              : null;
    }

    walkSiblings(node.children, context);

    return next;
}

function walkSiblings(children: TemplateChildNode[], context: NormalizeContext): void {
    let chain: ConditionChain | null = null;

    for (const child of children) {
        if (child.type !== NodeTypes.ELEMENT) {
            // Comments and whitespace keep Vue's condition adjacency intact, any other content breaks it.
            if (child.type !== NodeTypes.COMMENT && child.loc.source.trim() !== '') {
                chain = null;
            }

            continue;
        }

        chain = isConvertedBlock(child) ? walkConvertedBlock(child, chain, context) : walkElement(child, chain, context);
    }
}

/**
 * Returns the markup with guard branches inserted, or the blocker when a continuation has no
 * preceding `v-if` at all — that one cannot be reconnected, only reported.
 */
function normalizeCrossBlockConditionals(body: string): NormalizeResult {
    const ast = parseTemplate(body);

    if (ast === null) {
        return { template: body, blockers: [] };
    }

    const context: NormalizeContext = { source: body, guards: [], orphaned: false, unsafe: false };

    walkSiblings(ast.children, context);

    if (context.orphaned) {
        return { template: null, blockers: [ORPHANED_CONTINUATION] };
    }

    if (context.unsafe) {
        return {
            template: null,
            blockers: ['cross-block conditional contains a side-effecting expression'],
        };
    }

    const template = context.guards
        .sort((a, b) => b.offset - a.offset)
        .reduce((source, guard) => source.slice(0, guard.offset) + guard.markup + source.slice(guard.offset), body);

    return { template, blockers: [] };
}

export { normalizeCrossBlockConditionals, type NormalizeResult };
