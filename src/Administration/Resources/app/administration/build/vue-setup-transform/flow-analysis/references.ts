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
 * - **`visit`** (internal): the callback the walk hands every outer-scope read, together with its parent
 *   node. Name collection and occurrence collection are the same walk with two different callbacks.
 * - **occurrence**: one reference *site* - the name plus its `start`/`end` offsets within the parsed
 *   source, and the syntax a replacement has to reproduce there. Callers that rewrite references need
 *   the sites; callers that only decide forwarding need the names.
 * - **`parent`**: the parent AST node, which is what distinguishes a read from a declaration or a name -
 *   e.g. the `x` in `obj.x` is the MemberExpression's `property`, so it is not a read. That judgement
 *   lives in `isValueReadPosition` (`./identifier-position`), shared with the setup-script pass.
 */

import { parse, parseExpression, type ParserPlugin } from '@babel/parser';
import type { Identifier, Node as BabelNode, PatternLike } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { forEachPatternIdentifier } from '../utils/babel-patterns';
import { childBabelNodes, isFunctionLikeNode, isTypeKey } from '../utils/ast-traversal';
import { isShorthandPropertyValue, isValueReadPosition } from './identifier-position';

type BindingPatternResult = {
    pattern: PatternLike;
    offset: number;
};

/**
 * How much of the surrounding syntax an occurrence's replacement text has to reproduce.
 *
 * A plain occurrence can be swapped for the replacement; a shorthand object property (`{ info }`) shares
 * one source range with its key, so the replacement has to spell the key out as well.
 */
type OccurrenceExpansion = 'plain' | 'shorthand-property';

/**
 * One reference site inside a parsed expression or binding pattern.
 *
 * `start`/`end` are offsets within the source that was handed to the collector, so a caller that knows
 * where that source sits in the SFC can translate them without re-parsing.
 */
type ExpressionOccurrence = {
    name: string;
    start: number;
    end: number;
    expansion: OccurrenceExpansion;
};

/**
 * Receives every outer-scope read the walk finds, with the parent node that gives it its syntax.
 */
type ReferenceVisitor = (identifier: Identifier, parent: BabelNode | null) => void;

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
 * Hands `visit` every outer-scope reference a **binding pattern** reads.
 *
 * A pattern only reads through its destructuring defaults (`{ a = fallback }` reads `fallback`) and
 * computed keys (`{ [key]: v }` reads `key`); the names it declares go into `patternScope` and are not
 * reads. Earlier-declared names shadow later defaults, so `{ a, b: c = a }` reads nothing.
 *
 * @param outerScopes enclosing scope stack (see the file header); `patternScope` is layered on top of
 *   it when evaluating defaults/computed keys, so a name declared earlier in the pattern shadows them.
 */
function forEachPatternReference(
    pattern: BabelNode | null | undefined,
    outerScopes: Set<string>[],
    visit: ReferenceVisitor,
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
        forEachPatternReference(pattern.argument, outerScopes, visit, patternScope);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        forEachBabelReference(
            pattern.right,
            [
                patternScope,
                ...outerScopes,
            ],
            visit,
            pattern,
        );
        forEachPatternReference(pattern.left, outerScopes, visit, patternScope);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => forEachPatternReference(element, outerScopes, visit, patternScope));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                forEachPatternReference(property.argument, outerScopes, visit, patternScope);
                return;
            }

            if (property.computed) {
                forEachBabelReference(
                    property.key,
                    [
                        patternScope,
                        ...outerScopes,
                    ],
                    visit,
                    property,
                );
            }

            forEachPatternReference(property.value, outerScopes, visit, patternScope);
        });
    }
}

/**
 * Collects the outer-scope references a **binding pattern** reads, into `references` (out-parameter).
 *
 * Thin name-only wrapper over {@link forEachPatternReference} for callers that just need the names.
 */
function collectPatternReferences(
    pattern: BabelNode | null | undefined,
    outerScopes: Set<string>[],
    references: Set<string>,
    patternScope: Set<string> = new Set(),
): void {
    forEachPatternReference(pattern, outerScopes, (identifier) => references.add(identifier.name), patternScope);
}

/**
 * Whether `name` is declared in any scope on the stack (so it is not an outer reference).
 */
function isDeclared(name: string, scopes: Set<string>[]): boolean {
    return scopes.some((scope) => scope.has(name));
}

/**
 * Walks a Babel expression/statement tree and hands every outer-scope read to `visit`.
 *
 * `scopes` is the scope stack (innermost first): each function, block, and catch clause pushes a new
 * Set of the names it declares, so an identifier is a reference only if `isValueReadPosition` says it
 * is a read *and* it is not `isDeclared` in any scope. `parent` is threaded so read-vs-declaration can
 * be decided (see the file header), and passed on to `visit` so a rewriting caller can tell which
 * syntax the occurrence sits in.
 */
function forEachBabelReference(
    node: BabelNode | null | undefined,
    scopes: Set<string>[],
    visit: ReferenceVisitor,
    parent: BabelNode | null = null,
): void {
    if (!node || typeof node.type !== 'string') {
        return;
    }

    if (node.type === 'Identifier') {
        if (isValueReadPosition(node, parent) && !isDeclared(node.name, scopes)) {
            visit(node, parent);
        }

        return;
    }

    if (node.type === 'Program') {
        node.body.forEach((statement) => forEachBabelReference(statement, scopes, visit, node));
        return;
    }

    if (node.type === 'BlockStatement') {
        const blockScope = new Set<string>();
        const nextScopes = [
            blockScope,
            ...scopes,
        ];

        node.body.forEach((statement) => forEachBabelReference(statement, nextScopes, visit, node));
        return;
    }

    if (node.type === 'VariableDeclaration') {
        node.declarations.forEach((declaration) => {
            forEachBabelReference(declaration.init, scopes, visit, declaration);
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
        node.params.forEach((parameter) => forEachPatternReference(parameter, scopes, visit, functionScope));

        if (node.type === 'ObjectMethod' && node.computed) {
            forEachBabelReference(node.key, scopes, visit, node);
        }

        forEachBabelReference(
            node.body,
            [
                functionScope,
                ...scopes,
            ],
            visit,
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

        forEachBabelReference(node.superClass, scopes, visit, node);
        forEachBabelReference(
            node.body,
            [
                classScope,
                ...scopes,
            ],
            visit,
            node,
        );
        return;
    }

    if (node.type === 'ObjectProperty') {
        if (node.computed) {
            forEachBabelReference(node.key, scopes, visit, node);
        }

        forEachBabelReference(node.value, scopes, visit, node);
        return;
    }

    if (node.type === 'MemberExpression' || node.type === 'OptionalMemberExpression') {
        forEachBabelReference(node.object, scopes, visit, node);

        if (node.computed) {
            forEachBabelReference(node.property, scopes, visit, node);
        }

        return;
    }

    if (node.type === 'CatchClause') {
        const catchScope = new Set<string>();
        addPatternNames(node.param, catchScope);
        forEachBabelReference(
            node.body,
            [
                catchScope,
                ...scopes,
            ],
            visit,
            node,
        );
        return;
    }

    childBabelNodes(node, isTypeKey).forEach((child) => forEachBabelReference(child, scopes, visit, node));
}

/**
 * Returns the setup-scope references one Vue expression reads, as occurrence *sites*.
 *
 * Offsets are relative to `expression` itself, so the caller - which knows where that expression sits in
 * the template - can translate them without parsing twice.
 *
 * @param templateScope names already bound by the surrounding template (v-for aliases, slot-scope
 *   props). They are seeded as the outermost scope, so they count as declared and never produce an
 *   occurrence.
 */
function collectExpressionOccurrences(expression: string | undefined, templateScope: Set<string>): ExpressionOccurrence[] {
    if (!expression || expression.trim() === '') {
        return [];
    }

    const occurrences: ExpressionOccurrence[] = [];

    forEachBabelReference(
        parseTemplateExpression(expression),
        [
            new Set(templateScope),
        ],
        (identifier, parent) => occurrences.push(toOccurrence(identifier, parent, 0)),
    );

    return occurrences;
}

/**
 * Returns the setup-scope references a Vue **binding pattern** reads, as occurrence sites.
 *
 * A pattern reads only through destructuring defaults and computed keys; the names it declares are the
 * slot's or loop's own bindings. Offsets are relative to `patternSource` - the wrapping the parser needs
 * is subtracted here, so callers never see it.
 */
function collectPatternOccurrences(patternSource: string, templateScope: Set<string>): ExpressionOccurrence[] {
    const occurrences: ExpressionOccurrence[] = [];

    try {
        const { pattern, offset } = parseBindingPattern(patternSource);

        forEachPatternReference(
            pattern,
            [
                new Set(templateScope),
            ],
            (identifier, parent) => occurrences.push(toOccurrence(identifier, parent, offset)),
        );
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
    }

    return occurrences;
}

/**
 * Builds one occurrence record from a visited identifier.
 *
 * `parseOffset` is what the parser added in front of the caller's source (the `const ` a binding pattern
 * is wrapped in), so the reported range addresses the caller's own text.
 */
function toOccurrence(identifier: Identifier, parent: BabelNode | null, parseOffset: number): ExpressionOccurrence {
    return {
        name: identifier.name,
        start: (identifier.start ?? 0) - parseOffset,
        end: (identifier.end ?? 0) - parseOffset,
        expansion: isShorthandPropertyValue(identifier, parent) ? 'shorthand-property' : 'plain',
    };
}

/**
 * Returns the setup-scope identifiers one Vue expression reads.
 *
 * Name-only wrapper over {@link collectExpressionOccurrences} for callers that only decide *whether* a
 * binding is read. e.g. for `info + label` with `templateScope = {info}`, the result is `{label}`.
 */
function collectExpressionReferences(expression: string | undefined, templateScope: Set<string>): Set<string> {
    return new Set(collectExpressionOccurrences(expression, templateScope).map((occurrence) => occurrence.name));
}

/**
 * @private
 */
export {
    type ExpressionOccurrence,
    type OccurrenceExpansion,
    addPatternNames,
    collectExpressionOccurrences,
    collectExpressionReferences,
    collectPatternOccurrences,
    collectPatternReferences,
    parseBindingPattern,
};
