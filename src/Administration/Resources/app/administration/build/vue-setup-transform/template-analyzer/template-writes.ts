/**
 * @sw-package framework
 */

import { NodeTypes, type ExpressionNode, type TemplateChildNode } from '@vue/compiler-dom';
import { extractIdentifiers, isInDestructureAssignment, walkIdentifiers } from '@vue/compiler-sfc';
import type { Node as BabelNode } from '@babel/types';

/**
 * Collects the outer identifiers a template subtree assigns to, mapped to the offset of the writing
 * expression. Scoping follows Vue: `v-for` aliases and slot props are template locals, and
 * `walkIdentifiers` skips JS locals.
 *
 * Reuses the expression ASTs `@vue/compiler-sfc` attaches while parsing the SFC. A simple identifier
 * has `ast === null` and cannot be a write.
 */
function collectTemplateWrites(children: TemplateChildNode[]): Map<string, number> {
    const writes = new Map<string, number>();

    const visitExpression = (exp: ExpressionNode | undefined, scope: Set<string>) => {
        if (exp?.type !== NodeTypes.SIMPLE_EXPRESSION || exp.isStatic || !exp.ast) {
            return;
        }

        const knownIds: Record<string, number> = Object.create(null) as Record<string, number>;
        scope.forEach((name) => {
            knownIds[name] = 1;
        });

        walkIdentifiers(
            exp.ast,
            (identifier, parent, parentStack) => {
                const isWrite =
                    (parent?.type === 'AssignmentExpression' && parent.left === identifier) ||
                    (parent?.type === 'UpdateExpression' && parent.argument === identifier) ||
                    (parent !== null && isInDestructureAssignment(parent, parentStack));

                if (isWrite && !writes.has(identifier.name)) {
                    writes.set(identifier.name, exp.loc.start.offset);
                }
            },
            false,
            [],
            knownIds,
        );
    };

    // A v-for alias or v-slot pattern is parsed as `(pattern) => {}`: its parameters are the names it
    // declares, its defaults are expressions.
    const declare = (exp: ExpressionNode | undefined, scope: Set<string>, into: Set<string>) => {
        if (exp?.type !== NodeTypes.SIMPLE_EXPRESSION) {
            return;
        }

        if (exp.ast === null) {
            into.add(exp.content);
            return;
        }

        if (exp.ast && exp.ast.type === 'ArrowFunctionExpression') {
            visitExpression(exp, scope);
            exp.ast.params.forEach((param) => {
                extractIdentifiers(param as BabelNode).forEach((identifier) => into.add(identifier.name));
            });
        }
    };

    const visit = (node: TemplateChildNode, scope: Set<string>) => {
        if (node.type === NodeTypes.INTERPOLATION) {
            visitExpression(node.content, scope);
            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        const elementScope = new Set(scope);
        const vFor = node.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === 'for');

        if (vFor?.type === NodeTypes.DIRECTIVE && vFor.forParseResult) {
            const { source, value, key, index } = vFor.forParseResult;

            visitExpression(source, scope);
            [value, key, index].forEach((alias) => declare(alias, scope, elementScope));
        }

        const childScope = new Set(elementScope);

        node.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === vFor) {
                return;
            }

            visitExpression(prop.arg, elementScope);

            if (prop.name === 'slot') {
                declare(prop.exp, elementScope, childScope);
            } else {
                visitExpression(prop.exp, elementScope);
            }
        });

        node.children.forEach((child) => visit(child, childScope));
    };

    children.forEach((child) => visit(child, new Set()));

    return writes;
}

/**
 * @private
 */
export { collectTemplateWrites };
