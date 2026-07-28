/**
 * @sw-package framework
 */

/**
 * Scope-aware reference detection inside one template JS/TS expression.
 *
 * Given an expression such as `items.map(({ info }) => info + label)`, these helpers report which
 * identifiers read outer (setup) scope - here `items` and `label` - while pattern-local names,
 * shadowed callback parameters, member-access property names, and static object keys are ignored.
 *
 * Shared vocabulary used throughout this file:
 * - **reference**: an identifier that *reads* a value from an outer scope (not one declared locally,
 *   and not a static key / property name / declaration id).
 * - **binding pattern**: the left-hand side of a destructure that *declares* names rather than reading
 *   them - `{ a, b: c }`, `[x, ...rest]`, `a = default`.
 * - **`templateScope`** (public entry points): names already in scope from the surrounding Vue template
 *   - v-for aliases, slot-scope props. They are template-local, so they are never reported as
 *   references.
 * - **`scopes`** (internal): a stack of name Sets, innermost first, that are in scope at the current
 *   node. A name present in any of them is declared, so it is not a reference. Entering a
 *   function/block/catch pushes a new Set of the names it declares.
 * - **`references` / `targets`** (internal): the *output* Set the walk accumulates into - it is mutated
 *   in place (an out-parameter), not returned, so a single Set collects across the whole subtree.
 * - **`parent`**: the parent AST node, which is what distinguishes a read from a declaration or a name -
 *   e.g. the `x` in `obj.x` is the MemberExpression's `property`, so it is not a read. That judgement
 *   lives in `isValueReadPosition` (`./identifier-position`), shared with the setup-script pass.
 */

import { parse, parseExpression, type ParserPlugin } from '@babel/parser';
import type { Node as BabelNode, PatternLike } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import { childBabelNodes, isFunctionLikeNode, isTypeKey } from '../utils/ast-traversal';
import { isValueReadPosition } from './identifier-position';

type BindingPatternResult = {
    pattern: PatternLike;
    offset: number;
};

const EXPRESSION_PLUGINS: ParserPlugin[] = [
    'typescript',
];

/**
 * Parses one template JS snippet into a Babel node.
 *
 * Vue bindings are usually expressions (`a + b`), so it tries `parseExpression` first; inline event
 * handlers can be statements (`count = 1; save()`), so it falls back to parsing a module and returning
 * the `Program`.
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
 * Parses a v-slot / v-for binding expression (a **binding pattern** such as `#default="{ item, index }"`).
 *
 * Vue gives the pattern text without a declaration context, so it is wrapped in `const <pattern> =
 * __slot;` and parsed. Returns the pattern node plus `offset` - where the pattern text starts within
 * the wrapped source - so callers can translate positions back to the original expression.
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
 * Adds every name a **binding pattern** declares into `scope` (mutated in place).
 *
 * e.g. `{ a, b: c, d = fallback }` declares `a`, `c`, `d`; the default `fallback` and any computed key
 * are reads, not declarations, so they are not added.
 */
function addPatternNames(pattern: BabelNode | null | undefined, scope: Set<string>): void {
    forEachPatternIdentifier(pattern, (identifier) => {
        scope.add(identifier.name);
    });
}

/**
 * Collects the outer-scope references a **binding pattern** reads, into `references` (out-parameter).
 *
 * A pattern only reads through its destructuring defaults (`{ a = fallback }` reads `fallback`) and
 * computed keys (`{ [key]: v }` reads `key`); the names it declares go into `patternScope` and are not
 * reads. Earlier-declared names shadow later defaults, so `{ a, b: c = a }` reads nothing.
 *
 * @param outerScopes enclosing scope stack (see the file header); `patternScope` is layered on top of
 *   it when evaluating defaults/computed keys, so a name declared earlier in the pattern shadows them.
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
                );
            }

            collectPatternReferences(property.value, outerScopes, references, patternScope);
        });
    }
}

/**
 * Whether `name` is declared in any scope on the stack (so it is not an outer reference).
 */
function isDeclared(name: string, scopes: Set<string>[]): boolean {
    return scopes.some((scope) => scope.has(name));
}

/**
 * Walks a Babel expression/statement tree and adds every outer-scope read into `references`.
 *
 * `references` is the output Set (mutated in place). `scopes` is the scope stack (innermost first):
 * each function, block, and catch clause pushes a new Set of the names it declares, so an identifier
 * is a reference only if `isValueReadPosition` says it is a read *and* it is not `isDeclared` in any
 * scope. `parent` is threaded so read-vs-declaration can be decided (see the file header).
 */
function collectBabelReferences(
    node: BabelNode | null | undefined,
    scopes: Set<string>[],
    references: Set<string>,
    parent: BabelNode | null = null,
): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    if (node.type === 'Identifier') {
        if (isValueReadPosition(node, parent) && !isDeclared(node.name, scopes)) {
            references.add(node.name);
        }

        return;
    }

    if (node.type === 'Program') {
        node.body.forEach((statement) => collectBabelReferences(statement, scopes, references, node));
        return;
    }

    if (node.type === 'BlockStatement') {
        const blockScope = new Set<string>();
        const nextScopes = [
            blockScope,
            ...scopes,
        ];

        node.body.forEach((statement) => collectBabelReferences(statement, nextScopes, references, node));
        return;
    }

    if (node.type === 'VariableDeclaration') {
        node.declarations.forEach((declaration) => {
            collectBabelReferences(declaration.init, scopes, references, declaration);
            addPatternNames(declaration.id, scopes[0]);
        });
        return;
    }

    if (isFunctionLikeNode(node)) {
        const functionScope = new Set<string>();

        // A named function *expression* binds its own name only inside its body (for self-reference); a
        // function *declaration* binds it in the enclosing scope. Putting an expression id in `scopes[0]`
        // would wrongly suppress a same-named sibling setup read.
        if ('id' in node && node.id) {
            (node.type === 'FunctionExpression' ? functionScope : scopes[0]).add(node.id.name);
        }

        // Parameter defaults and computed keys are reads, e.g. `({ label = fallbackLabel }) => label`
        // reads `fallbackLabel` from setup scope. Parameters are scanned left to right so earlier
        // parameter names shadow reads in later defaults (`(a, { b = a }) => b` reads nothing).
        node.params.forEach((parameter) => collectPatternReferences(parameter, scopes, references, functionScope));

        if (node.type === 'ObjectMethod' && node.computed) {
            collectBabelReferences(node.key, scopes, references, node);
        }

        collectBabelReferences(
            node.body,
            [
                functionScope,
                ...scopes,
            ],
            references,
            node,
        );
        return;
    }

    if (node.type === 'ClassDeclaration' || node.type === 'ClassExpression') {
        // Like function expressions: a named class *expression* binds its own name only inside the class
        // body (e.g. `static self = C`), not in the surrounding scope. The `extends` clause is evaluated
        // in the enclosing scope, so it never sees the class name.
        const classScope = new Set<string>();

        if (node.id) {
            (node.type === 'ClassExpression' ? classScope : scopes[0]).add(node.id.name);
        }

        collectBabelReferences(node.superClass, scopes, references, node);
        collectBabelReferences(
            node.body,
            [
                classScope,
                ...scopes,
            ],
            references,
            node,
        );
        return;
    }

    if (node.type === 'ObjectProperty') {
        if (node.computed) {
            collectBabelReferences(node.key, scopes, references, node);
        }

        collectBabelReferences(node.value, scopes, references, node);
        return;
    }

    if (node.type === 'MemberExpression' || node.type === 'OptionalMemberExpression') {
        collectBabelReferences(node.object, scopes, references, node);

        if (node.computed) {
            collectBabelReferences(node.property, scopes, references, node);
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
        );
        return;
    }

    childBabelNodes(node, isTypeKey).forEach((child) => collectBabelReferences(child, scopes, references, node));
}

/**
 * Collects identifiers written by one expression: assignment targets and update (`x++`) operands.
 *
 * Only direct identifier targets are collected (`count = 1`, `count++`) - the case where a template
 * write to a forwarded override binding silently no-ops. Member writes (`count.value = 1`) and nested
 * shadowing are out of scope; template-local names are filtered by the caller's scope.
 */
function collectBabelWriteTargets(root: BabelNode | null | undefined): Set<string> {
    const targets = new Set<string>();

    function visit(node: BabelNode | null | undefined): void {
        if (!node || typeof node.type !== 'string') {
            return;
        }

        if (node.type === 'AssignmentExpression' && node.left.type === 'Identifier') {
            targets.add(node.left.name);
        }

        if (node.type === 'UpdateExpression' && node.argument.type === 'Identifier') {
            targets.add(node.argument.name);
        }

        childBabelNodes(node).forEach(visit);
    }

    visit(root);

    return targets;
}

/**
 * Returns the outer-scope identifiers one Vue expression writes to (assignment/update targets).
 *
 * @param templateScope names already bound by the surrounding template (v-for aliases, slot props);
 *   a write to one of those is template-local, so it is filtered out of the result.
 */
function collectExpressionWriteTargets(expression: string | undefined, templateScope: Set<string>): Set<string> {
    if (!expression || expression.trim() === '') {
        return new Set<string>();
    }

    const targets = collectBabelWriteTargets(parseTemplateExpression(expression));

    return new Set([...targets].filter((name) => !templateScope.has(name)));
}

/**
 * Returns the setup-scope identifiers one Vue expression reads.
 *
 * @param templateScope names already bound by the surrounding template (v-for aliases, slot-scope
 *   props). They are seeded as the outermost scope, so they count as declared and are excluded from
 *   the result - only genuine reads of setup state come back. e.g. for `info + label` with
 *   `templateScope = {info}`, the result is `{label}`.
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

/**
 * @private
 */
export {
    addPatternNames,
    collectExpressionReferences,
    collectExpressionWriteTargets,
    collectPatternReferences,
    parseBindingPattern,
};
