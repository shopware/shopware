/**
 * @sw-package framework
 */

/**
 * Scope-aware reference detection inside one template JS/TS expression.
 *
 * Given an expression such as `items.map(({ info }) => info + label)`, these helpers report which
 * identifiers read outer (setup) scope - here `items` and `label` - while pattern-local names,
 * shadowed callback parameters, member-access property names, and static object keys are ignored.
 */

import { parse, parseExpression, type ParserPlugin } from '@babel/parser';
import type { Node as BabelNode, PatternLike } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { forEachPatternIdentifier, isBabelNodeLike } from '../utils/babel-patterns';

type BindingPatternResult = {
    pattern: PatternLike;
    offset: number;
};

const EXPRESSION_PLUGINS: ParserPlugin[] = [
    'typescript',
];

/**
 * Parses template JavaScript snippets as expressions first, then as statements for inline handlers.
 *
 */
function parseTemplateExpression(source: string): BabelNode {
    try {
        return parseExpression(source, {
            plugins: EXPRESSION_PLUGINS,
        });
    } catch {
        return parse(source, {
            sourceType: 'module',
            plugins: EXPRESSION_PLUGINS,
        }).program;
    }
}

/**
 * Parses a v-slot binding expression as a destructuring target.
 *
 */
function parseBindingPattern(source: string): BindingPatternResult {
    const prefix = 'const ';
    const ast = parse(`${prefix}${source} = __slot;`, {
        sourceType: 'module',
        plugins: EXPRESSION_PLUGINS,
    });
    const statement = ast.program.body[0];

    if (statement.type !== 'VariableDeclaration') {
        throw new ShopwareSetupTransformError('Unable to parse Vue binding pattern.', 0);
    }

    const declaration = statement.declarations[0];

    return {
        pattern: declaration.id as PatternLike,
        offset: prefix.length,
    };
}

/**
 * Adds all names declared by one binding pattern to a scope.
 *
 */
function addPatternNames(pattern: BabelNode | null | undefined, scope: Set<string>): void {
    forEachPatternIdentifier(pattern, (identifier) => {
        scope.add(identifier.name);
    });
}

/**
 * Collects outer-scope references used by one binding pattern without treating declarations as reads.
 *
 */
function collectPatternReferences(
    pattern: BabelNode | null | undefined,
    outerScopes: Set<string>[],
    references: Set<string>,
    patternScope: Set<string> = new Set(),
): void {
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        patternScope.add(pattern.name);
        return;
    }

    if (pattern.type === 'RestElement') {
        collectPatternReferences(pattern.argument, outerScopes, references, patternScope);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectBabelReferences(
            pattern.right,
            [
                patternScope,
                ...outerScopes,
            ],
            references,
            pattern,
            'right',
        );
        collectPatternReferences(pattern.left, outerScopes, references, patternScope);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => collectPatternReferences(element, outerScopes, references, patternScope));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectPatternReferences(property.argument, outerScopes, references, patternScope);
                return;
            }

            if (property.computed) {
                collectBabelReferences(
                    property.key,
                    [
                        patternScope,
                        ...outerScopes,
                    ],
                    references,
                    property,
                    'key',
                );
            }

            collectPatternReferences(property.value, outerScopes, references, patternScope);
        });
    }
}

/**
 * Checks whether a name is declared by the template or JavaScript expression itself.
 *
 */
function isDeclared(name: string, scopes: Set<string>[]): boolean {
    return scopes.some((scope) => scope.has(name));
}

/**
 * Filters AST fields that do not contain runtime JavaScript references.
 *
 */
function shouldSkipBabelKey(key: string): boolean {
    return [
        'loc',
        'range',
        'leadingComments',
        'trailingComments',
        'innerComments',
        'typeAnnotation',
        'typeParameters',
        'returnType',
        'typeArguments',
    ].includes(key);
}

/**
 * Handles Babel object keys and declaration sites so only value references are collected.
 *
 */
function isIdentifierReference(node: BabelNode, parent: BabelNode | null, parentKey: string | null): boolean {
    if (node.type !== 'Identifier' || !parent) {
        return node.type === 'Identifier';
    }

    if (parent.type === 'MemberExpression' || parent.type === 'OptionalMemberExpression') {
        return parentKey !== 'property' || Boolean(parent.computed);
    }

    if (parent.type === 'ObjectProperty') {
        return parentKey !== 'key' || Boolean(parent.computed);
    }

    if (parent.type === 'ObjectMethod') {
        return parentKey !== 'key' || Boolean(parent.computed);
    }

    if (
        parent.type === 'VariableDeclarator' ||
        parent.type === 'FunctionDeclaration' ||
        parent.type === 'FunctionExpression' ||
        parent.type === 'ClassDeclaration' ||
        parent.type === 'ClassExpression'
    ) {
        return parentKey !== 'id';
    }

    if (parentKey === 'params') {
        return false;
    }

    if (parent.type === 'BreakStatement' || parent.type === 'ContinueStatement' || parent.type === 'LabeledStatement') {
        return false;
    }

    return true;
}

/**
 * Visits a Babel expression tree and records identifiers that are read from outer scope.
 *
 */
function collectBabelReferences(
    node: BabelNode | null | undefined,
    scopes: Set<string>[],
    references: Set<string>,
    parent: BabelNode | null = null,
    parentKey: string | null = null,
): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    if (node.type === 'Identifier') {
        if (isIdentifierReference(node, parent, parentKey) && !isDeclared(node.name, scopes)) {
            references.add(node.name);
        }

        return;
    }

    if (node.type === 'Program') {
        node.body.forEach((statement) => collectBabelReferences(statement, scopes, references, node, 'body'));
        return;
    }

    if (node.type === 'BlockStatement') {
        const blockScope = new Set<string>();
        const nextScopes = [
            blockScope,
            ...scopes,
        ];

        node.body.forEach((statement) => collectBabelReferences(statement, nextScopes, references, node, 'body'));
        return;
    }

    if (node.type === 'VariableDeclaration') {
        node.declarations.forEach((declaration) => {
            collectBabelReferences(declaration.init, scopes, references, declaration, 'init');
            addPatternNames(declaration.id, scopes[0]);
        });
        return;
    }

    if (
        node.type === 'FunctionDeclaration' ||
        node.type === 'FunctionExpression' ||
        node.type === 'ArrowFunctionExpression' ||
        node.type === 'ObjectMethod' ||
        node.type === 'ClassMethod' ||
        node.type === 'ClassPrivateMethod'
    ) {
        if ('id' in node && node.id) {
            scopes[0].add(node.id.name);
        }

        const functionScope = new Set<string>();

        // Parameter defaults and computed keys are reads, e.g. `({ label = fallbackLabel }) => label`
        // reads `fallbackLabel` from setup scope. Parameters are scanned left to right so earlier
        // parameter names shadow reads in later defaults (`(a, { b = a }) => b` reads nothing).
        node.params.forEach((parameter) => collectPatternReferences(parameter, scopes, references, functionScope));

        if (node.type === 'ObjectMethod' && node.computed) {
            collectBabelReferences(node.key, scopes, references, node, 'key');
        }

        collectBabelReferences(
            node.body,
            [
                functionScope,
                ...scopes,
            ],
            references,
            node,
            'body',
        );
        return;
    }

    if (node.type === 'ClassDeclaration' || node.type === 'ClassExpression') {
        if (node.id) {
            scopes[0].add(node.id.name);
        }

        collectBabelReferences(node.superClass, scopes, references, node, 'superClass');
        collectBabelReferences(node.body, scopes, references, node, 'body');
        return;
    }

    if (node.type === 'ObjectProperty') {
        if (node.computed) {
            collectBabelReferences(node.key, scopes, references, node, 'key');
        }

        collectBabelReferences(node.value, scopes, references, node, 'value');
        return;
    }

    if (node.type === 'MemberExpression' || node.type === 'OptionalMemberExpression') {
        collectBabelReferences(node.object, scopes, references, node, 'object');

        if (node.computed) {
            collectBabelReferences(node.property, scopes, references, node, 'property');
        }

        return;
    }

    if (node.type === 'CatchClause') {
        const catchScope = new Set<string>();
        addPatternNames(node.param, catchScope);
        collectBabelReferences(
            node.body,
            [
                catchScope,
                ...scopes,
            ],
            references,
            node,
            'body',
        );
        return;
    }

    Object.entries(node as unknown as Record<string, unknown>).forEach(
        ([
            key,
            value,
        ]) => {
            if (shouldSkipBabelKey(key)) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((child) => {
                    if (isBabelNodeLike(child)) {
                        collectBabelReferences(child, scopes, references, node, key);
                    }
                });
                return;
            }

            if (isBabelNodeLike(value)) {
                collectBabelReferences(value, scopes, references, node, key);
            }
        },
    );
}

/**
 * Collects identifiers written by one expression: assignment targets and update (`x++`) operands.
 *
 * Only direct identifier targets are collected (`count = 1`, `count++`) - the case where a template
 * write to a forwarded override binding silently no-ops. Member writes (`count.value = 1`) and nested
 * shadowing are out of scope; template-local names are filtered by the caller's scope.
 */
function collectBabelWriteTargets(node: BabelNode | null | undefined, targets: Set<string>): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    if (node.type === 'AssignmentExpression' && node.left.type === 'Identifier') {
        targets.add(node.left.name);
    }

    if (node.type === 'UpdateExpression' && node.argument.type === 'Identifier') {
        targets.add(node.argument.name);
    }

    Object.values(node as unknown as Record<string, unknown>).forEach((value) => {
        if (Array.isArray(value)) {
            value.forEach((child) => {
                if (isBabelNodeLike(child)) {
                    collectBabelWriteTargets(child, targets);
                }
            });
            return;
        }

        if (isBabelNodeLike(value)) {
            collectBabelWriteTargets(value, targets);
        }
    });
}

/**
 * Returns the outer-scope identifiers one Vue expression writes to (assignment/update targets).
 */
function collectExpressionWriteTargets(expression: string | undefined, templateScope: Set<string>): Set<string> {
    if (!expression || expression.trim() === '') {
        return new Set<string>();
    }

    const targets = new Set<string>();
    collectBabelWriteTargets(parseTemplateExpression(expression), targets);

    return new Set([...targets].filter((name) => !templateScope.has(name)));
}

/**
 * Collects runtime references from one Vue expression while honoring aliases from parent template scopes.
 *
 */
function collectExpressionReferences(expression: string | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    if (!expression || expression.trim() === '') {
        return references;
    }

    collectBabelReferences(
        parseTemplateExpression(expression),
        [
            new Set(templateScope),
        ],
        references,
    );

    return references;
}

export {
    addPatternNames,
    collectExpressionReferences,
    collectExpressionWriteTargets,
    collectPatternReferences,
    parseBindingPattern,
};
