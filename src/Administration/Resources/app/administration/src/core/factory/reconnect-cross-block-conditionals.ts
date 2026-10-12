/**
 * @sw-package framework
 */

/**
 * Reconnects `v-if` chains that a `<sw-block>` boundary tore apart.
 *
 * Twig blocks are transparent, `<sw-block>` elements are not: a `v-else`/`v-else-if` whose `v-if`
 * lived in the previous block loses its adjacent sibling and Vue rejects the template. Every such
 * continuation gets an empty guard branch carrying the conditions of all preceding branches, so the
 * chain is adjacent again and still renders nothing when an earlier branch already matched.
 *
 * Two callers share this module. The sfc-migration codemod runs it once at conversion time on the
 * `<sw-block>` markup it emits, and the template factory runs it at runtime on Twig templates whose
 * blocks were wrapped in `<sw-block>` extension points. The codemod passes a Babel-backed
 * `isSafeCondition` and refuses side-effecting conditions; the runtime cannot refuse a template and
 * passes none. This file therefore has to stay free of Babel and every other tooling-only dependency.
 */

import { NodeTypes, parse } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode, RootNode, TemplateChildNode } from '@vue/compiler-dom';
// Relative on purpose: the sfc-migration codemod loads this module under ts-node, where the `src/` alias does not resolve.
import { warn } from '../service/utils/debug.utils';

const ORPHANED_CONTINUATION = 'orphaned cross-block v-else (no preceding v-if)';
const UNSAFE_CONTINUATION = 'cross-block conditional contains a side-effecting expression';
const GUARD_COMMENT = '<!-- Keeps the conditional chain connected across sw-block. -->';

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
    isSafeCondition: (expression: string) => boolean;
};

/**
 * Options for `normalizeCrossBlockConditionals()`.
 *
 * `isSafeCondition` decides whether a condition may be evaluated a second time inside a guard. When
 * it is omitted every condition is accepted; a caller that can refuse a template should pass a check.
 *
 * @private
 */
export type NormalizeOptions = {
    isSafeCondition?: (expression: string) => boolean;
};

/**
 * The normalized markup, or `null` with the blockers naming why the chain could not be reconnected.
 *
 * @private
 */
export type NormalizeResult = {
    template: string | null;
    blockers: string[];
};

/**
 * Parses template markup, or returns `null` when Vue rejects it. Markup Vue cannot parse is nothing
 * the callers can inspect or repair; Vue's own compiler reports it instead.
 *
 * @private
 */
export function parseTemplate(source: string): RootNode | null {
    try {
        return parse(source, { comments: true });
    } catch {
        return null;
    }
}

/**
 * Only blocks with a static name take part in chain reconnection; a hand-written `<sw-block>` that
 * binds its name dynamically is left alone.
 *
 * @private
 */
export function isConvertedBlock(node: ElementNode): boolean {
    return node.tag === 'sw-block' && node.props.some((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === 'name');
}

/**
 * The element children of a node, without text, comments and interpolations.
 *
 * @private
 */
export function elementChildren(node: ElementNode): ElementNode[] {
    return node.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
}

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

/**
 * A guard evaluates the preceding conditions a second time. The caller's `isSafeCondition` decides
 * whether that is acceptable for each of them; a refused condition marks the whole result as unsafe.
 */
function insertGuardBefore(node: ElementNode, conditions: string[], context: NormalizeContext): void {
    if (conditions.some((condition) => !context.isSafeCondition(condition))) {
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
 * Returns the markup with guard branches inserted, or a blocker when the chain cannot be reconnected:
 * a continuation without any preceding `v-if`, or, with an `isSafeCondition` that refuses one of the
 * conditions, a guard that would re-evaluate a side-effecting expression.
 *
 * @private
 */
export function normalizeCrossBlockConditionals(body: string, options: NormalizeOptions = {}): NormalizeResult {
    const ast = parseTemplate(body);

    if (ast === null) {
        return { template: body, blockers: [] };
    }

    const context: NormalizeContext = {
        source: body,
        guards: [],
        orphaned: false,
        unsafe: false,
        isSafeCondition: options.isSafeCondition ?? (() => true),
    };

    walkSiblings(ast.children, context);

    if (context.orphaned) {
        return { template: null, blockers: [ORPHANED_CONTINUATION] };
    }

    if (context.unsafe) {
        return { template: null, blockers: [UNSAFE_CONTINUATION] };
    }

    const template = context.guards
        .sort((a, b) => b.offset - a.offset)
        .reduce((source, guard) => source.slice(0, guard.offset) + guard.markup + source.slice(guard.offset), body);

    return { template, blockers: [] };
}

const NATIVE_BLOCK_WRAPPER = '<sw-block name=';
const CHAIN_CONTINUATION = /\sv-else(?:-if)?\b/;

/**
 * Runtime entry point for the template factory.
 *
 * Wrapping a Twig block in an `<sw-block>` extension point splits any `v-if` chain that ran through
 * the block boundary; this reconnects it before the legacy condition transform and Vue see the markup.
 * Templates without a wrapper or without a chain continuation are handed back untouched, which keeps
 * the cost at a string check for the common installation without native overrides.
 *
 * No condition is refused here: a template that cannot be reconnected is left as it is, with a
 * warning, and Vue reports the orphaned branch itself. Conditions of the preceding branches are
 * evaluated a second time inside the guard.
 *
 * @private
 */
export function reconnectCrossBlockConditionals(html: string, componentName: string): string {
    if (!html.includes(NATIVE_BLOCK_WRAPPER) || !CHAIN_CONTINUATION.test(html)) {
        return html;
    }

    const result = normalizeCrossBlockConditionals(html);

    if (result.template === null) {
        warn(
            'TemplateFactory',
            `The template of "${componentName}" continues a v-if chain across a native extension point ` +
                `without a preceding v-if (${result.blockers.join(', ')}). The template is left unchanged and ` +
                'Vue reports the orphaned branch.',
        );

        return html;
    }

    return result.template;
}
