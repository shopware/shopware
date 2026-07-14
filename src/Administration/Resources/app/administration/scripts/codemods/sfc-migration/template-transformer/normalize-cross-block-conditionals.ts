import { NodeTypes, parse } from '@vue/compiler-dom';
import type { DirectiveNode, ElementNode, RootNode, TemplateChildNode } from '@vue/compiler-dom';

const ORPHANED_CROSS_BLOCK_CONDITION_BLOCKER = 'orphaned cross-block v-else';
const ORPHANED_CROSS_BLOCK_CONDITION_ERROR =
    'Cross-block v-else/v-else-if without previous v-if block is not supported by the SFC migration codemod.';

type ConditionDirective = {
    expression: string;
    name: 'if' | 'else-if' | 'else';
    prop: DirectiveNode;
};

type Rewrite = {
    end: number;
    replacement: string;
    start: number;
};

type ConditionChain = {
    conditions: string[];
    crossedBlock: boolean;
};

export class CrossBlockConditionTransformError extends Error {
    public readonly blockers = [ORPHANED_CROSS_BLOCK_CONDITION_BLOCKER];

    public constructor() {
        super(ORPHANED_CROSS_BLOCK_CONDITION_ERROR);
        this.name = 'CrossBlockConditionTransformError';
    }
}

/**
 * Extracts the Vue condition directive from an element so the normalizer can
 * reason about `v-if`, `v-else-if`, and `v-else` without touching unrelated
 * directives. Use it while walking parsed Vue template elements.
 *
 * @example
 * const condition = getConditionDirective(element); // { name: 'else-if', expression: 'isActive', prop }
 */
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

    const expression =
        prop.exp && 'content' in prop.exp ? String(prop.exp.content).trim() : (prop.exp?.loc.source.trim() ?? '');

    return {
        expression,
        name: prop.name as ConditionDirective['name'],
        prop,
    };
}

/**
 * Reads the conditional chain at the end of a sibling element list. Use it when
 * a converted `<sw-block>` may carry the last visible branch before another
 * block or continuation element appears.
 *
 * @example
 * collectTrailingConditionChain([ifNode, elseIfNode]); // ['a', 'b']
 */
function collectTrailingConditionChain(children: ElementNode[]): string[] | null {
    const conditions: string[] = [];

    for (let index = children.length - 1; index >= 0; index -= 1) {
        const condition = getConditionDirective(children[index]);

        if (!condition) {
            break;
        }

        if (condition.name === 'else') {
            return null;
        }

        conditions.unshift(condition.expression);

        if (condition.name === 'if') {
            return conditions;
        }
    }

    return null;
}

/**
 * Inserts guard branches before `v-else` / `v-else-if` chains whose adjacency
 * was broken by a converted Shopware block. Use it after Twig blocks were
 * converted to `<sw-block>` markup and before wrapping the result in a Vue SFC
 * template.
 *
 * @example
 * normalizeCrossBlockConditionals('<sw-block><div v-if="a" /></sw-block><div v-else />');
 * // '<sw-block><div v-if="a" /></sw-block><template v-if="(a)">...</template><div v-else />'
 */
export function normalizeCrossBlockConditionals(body: string): string {
    const ast = parse(body, {
        comments: true,
    });
    const rewrites: Rewrite[] = [];

    /**
     * Returns only element children and ignores text/comment nodes. Use it when
     * evaluating sibling condition chains because Vue condition adjacency is
     * decided between elements.
     *
     * @example
     * const children = elementChildren(swBlockNode); // [firstInnerElement, secondInnerElement]
     */
    const elementChildren = (node: ElementNode | RootNode): ElementNode[] => {
        return node.children.filter((child): child is ElementNode => child.type === NodeTypes.ELEMENT);
    };

    /**
     * Detects `<sw-block>` elements emitted by this codemod, not arbitrary user
     * components with the same tag. Use it to limit rewrites to adjacency broken
     * by converted Twig blocks.
     *
     * @example
     * isConvertedSwBlock(node); // true for <sw-block name="sw_foo" :data="$dataScope">
     */
    const isConvertedSwBlock = (node: ElementNode): boolean => {
        const dataScope = node.props.find((prop): prop is DirectiveNode => {
            return prop.type === NodeTypes.DIRECTIVE && prop.name === 'bind';
        });

        return (
            node.tag === 'sw-block' &&
            node.props.some((prop) => prop.type === NodeTypes.ATTRIBUTE && prop.name === 'name') &&
            dataScope?.arg?.type === NodeTypes.SIMPLE_EXPRESSION &&
            dataScope.arg.content === 'data' &&
            dataScope.exp?.loc.source.trim() === '$dataScope'
        );
    };

    /**
     * Builds the no-op guard condition for a broken chain. Use it when a
     * continuation needs a local `v-if` branch before its original `v-else-if`
     * or `v-else` can stay unchanged.
     *
     * @example
     * buildGuardExpression(['a', 'b']); // '(a) || (b)'
     */
    const buildGuardExpression = (conditions: string[]): string => {
        return conditions.map((condition) => `(${condition})`).join(' || ');
    };

    /**
     * Inserts an empty template branch before the continuation element. Use it
     * where generated `<sw-block>` markup broke Vue's direct sibling chain but
     * the following `v-else-if` / `v-else` should remain readable.
     *
     * @example
     * insertGuardBefore(vElseNode, ['isLoading']);
     */
    const insertGuardBefore = (node: ElementNode, conditions: string[]): void => {
        const offset = node.loc.start.offset;
        const lineStart = body.lastIndexOf('\n', offset - 1) + 1;
        const linePrefix = body.slice(lineStart, offset);
        const indentation = /^\s*$/.test(linePrefix) ? linePrefix : '';

        rewrites.push({
            start: offset,
            end: offset,
            replacement: `<template v-if="${buildGuardExpression(conditions).replace(/"/g, '&quot;')}"><!-- Keeps the conditional chain connected across sw-block. --></template>\n${indentation}`,
        });
    };

    /**
     * Reads a leading `v-else-if` / `v-else` chain inside a following
     * `<sw-block>`. Use it when a block starts with the continuation of a chain
     * that began in an earlier sibling.
     *
     * @example
     * collectLeadingContinuationChain([elseIfNode, elseNode], ['a']);
     * // { conditions: ['a', 'b'], endedWithElse: true, lastIndex: 1 }
     */
    const collectLeadingContinuationChain = (
        children: ElementNode[],
        previousConditions: string[],
    ): { conditions: string[]; endedWithElse: boolean; lastIndex: number } => {
        const conditions = [...previousConditions];
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
    };

    /**
     * Walks sibling lists recursively and tracks the currently open condition
     * chain. Use it as the main normalizer pass for root children and nested
     * wrappers that can contain converted `<sw-block>` siblings.
     *
     * @example
     * walk(ast.children); // collects rewrites for every broken sibling chain
     */
    const walk = (children: TemplateChildNode[]): void => {
        let conditionChain: ConditionChain | null = null;

        children.forEach((child) => {
            if (child.type !== NodeTypes.ELEMENT) {
                if (child.type !== NodeTypes.COMMENT && child.loc.source.trim() !== '') {
                    conditionChain = null;
                }

                return;
            }

            if (!isConvertedSwBlock(child)) {
                const condition = getConditionDirective(child);

                if (condition?.name === 'if') {
                    walk(child.children);
                    conditionChain = {
                        conditions: [condition.expression],
                        crossedBlock: false,
                    };
                    return;
                }

                if (condition && conditionChain) {
                    if (conditionChain.crossedBlock) {
                        insertGuardBefore(child, conditionChain.conditions);
                    }

                    walk(child.children);
                    conditionChain =
                        condition.name === 'else'
                            ? null
                            : {
                                  conditions: [
                                      ...conditionChain.conditions,
                                      condition.expression,
                                  ],
                                  crossedBlock: conditionChain.crossedBlock,
                              };
                    return;
                }

                walk(child.children);
                conditionChain = null;
                return;
            }

            const convertedBlockChildren = elementChildren(child);
            const firstChild = convertedBlockChildren[0];
            const firstCondition = firstChild ? getConditionDirective(firstChild) : undefined;

            if (firstCondition && firstCondition.name !== 'if') {
                if (!conditionChain) {
                    throw new CrossBlockConditionTransformError();
                }

                insertGuardBefore(firstChild, conditionChain.conditions);
                const continuation = collectLeadingContinuationChain(convertedBlockChildren, conditionChain.conditions);
                const nextConditions =
                    continuation.lastIndex === convertedBlockChildren.length - 1 && !continuation.endedWithElse
                        ? continuation.conditions
                        : collectTrailingConditionChain(convertedBlockChildren);
                conditionChain = nextConditions
                    ? {
                          conditions: nextConditions,
                          crossedBlock: true,
                      }
                    : null;
            } else {
                const trailingConditions = collectTrailingConditionChain(convertedBlockChildren);
                conditionChain = trailingConditions
                    ? {
                          conditions: trailingConditions,
                          crossedBlock: true,
                      }
                    : conditionChain && convertedBlockChildren.length === 0
                      ? {
                            ...conditionChain,
                            crossedBlock: true,
                        }
                      : null;
            }

            walk(child.children);
        });
    };

    walk(ast.children);

    return rewrites
        .sort((a, b) => b.start - a.start)
        .reduce((rewrittenSource, rewrite) => {
            return rewrittenSource.slice(0, rewrite.start) + rewrite.replacement + rewrittenSource.slice(rewrite.end);
        }, body);
}
