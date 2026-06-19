/**
 * @sw-package framework
 */

import crypto from 'crypto';
import path from 'path';
import { NodeTypes, parse as parseTemplate } from '@vue/compiler-dom';
import type {
    DirectiveNode as CoreDirectiveNode,
    ElementNode as CoreElementNode,
    TemplateChildNode,
} from '@vue/compiler-core';
import { parse, parseExpression, type ParserPlugin } from '@babel/parser';
import type { Node as BabelNode, PatternLike } from '@babel/types';
import type { ShopwareSetupScriptAnalysis } from './script-analyzer';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

type TemplateEdit = {
    start: number;
    end: number;
    replacement: string;
};

type DirectiveNode = CoreDirectiveNode & {
    arg?: { content: string; isStatic?: boolean };
    exp?: { content: string; loc: { start: { offset: number }; end: { offset: number } } };
    forParseResult?: {
        value?: { content: string };
        key?: { content: string };
        index?: { content: string };
        source?: { content: string };
    };
    rawName: string;
};

type ElementNode = CoreElementNode & {
    props: Array<CoreElementNode['props'][number] | DirectiveNode>;
    children: TemplateChildNode[];
};

type TemplateAnalysis = {
    edits: TemplateEdit[];
    privateBindings: Set<string>;
    privateNamespace: string | null;
};

type SlotMapping = {
    sourceKey: string;
    source: string;
};

type BindingPatternResult = {
    pattern: PatternLike;
    offset: number;
};

function isBabelNodeLike(value: unknown): value is BabelNode {
    return Boolean(value && typeof value === 'object' && 'type' in value && typeof value.type === 'string');
}

const EXPRESSION_PLUGINS: ParserPlugin[] = [
    'typescript',
];
const RESERVED_OVERRIDE_STATE_NAME = '__swOverride';

/**
 * Keeps override-private namespace names stable for builds, tests, and debugging.
 *
 */
function createOverridePrivateNamespace(filename: string, componentName: string): string {
    const normalizedFilename = path.normalize(filename).split(path.sep).join('/');
    const hash = crypto.createHash('sha1').update(`${normalizedFilename}:${componentName}`).digest('hex').slice(0, 5);
    const readableFilename = path
        .basename(normalizedFilename)
        .replace(/\.[^.]+$/u, '')
        .replace(/[^A-Za-z0-9_$]/gu, '_')
        .replace(/_+/gu, '_')
        .replace(/^([^A-Za-z_$])/u, '_$1');

    return `${readableFilename}_${hash}`;
}

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
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        scope.add(pattern.name);
        return;
    }

    if (pattern.type === 'RestElement') {
        addPatternNames(pattern.argument, scope);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        addPatternNames(pattern.left, scope);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => {
            addPatternNames(element, scope);
        });
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                addPatternNames(property.argument, scope);
                return;
            }

            addPatternNames(property.value, scope);
        });
    }
}

/**
 * Collects outer-scope references used by one binding pattern without treating declarations as reads.
 *
 */
function collectPatternReferences(
    pattern: BabelNode | null | undefined,
    templateScope: Set<string>,
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
        collectPatternReferences(pattern.argument, templateScope, references, patternScope);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectBabelReferences(
            pattern.right,
            [
                patternScope,
                templateScope,
            ],
            references,
            pattern,
            'right',
        );
        collectPatternReferences(pattern.left, templateScope, references, patternScope);
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => collectPatternReferences(element, templateScope, references, patternScope));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectPatternReferences(property.argument, templateScope, references, patternScope);
                return;
            }

            if (property.computed) {
                collectBabelReferences(
                    property.key,
                    [
                        patternScope,
                        templateScope,
                    ],
                    references,
                    property,
                    'key',
                );
            }

            collectPatternReferences(property.value, templateScope, references, patternScope);
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
        node.params.forEach((parameter) => addPatternNames(parameter, functionScope));

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

/**
 * Returns names declared by a Vue v-slot expression.
 *
 */
function collectSlotScopeNames(slotDirective: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();

    if (!slotDirective?.exp?.content) {
        return scopeNames;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);
        addPatternNames(pattern, scopeNames);
    } catch {
        return scopeNames;
    }

    return scopeNames;
}

/**
 * Returns outer references used by a Vue v-slot binding pattern, such as destructuring defaults and computed keys.
 *
 */
function collectSlotScopeReferences(slotDirective: DirectiveNode | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    if (!slotDirective?.exp?.content) {
        return references;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);
        collectPatternReferences(pattern, templateScope, references);
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
    }

    return references;
}

/**
 * Checks whether a slot binding pattern declares or reads the machine-owned override-private state key.
 *
 */
function hasReservedOverrideSlotBinding(pattern: BabelNode | null | undefined): boolean {
    if (!pattern) {
        return false;
    }

    if (pattern.type === 'Identifier') {
        return pattern.name === RESERVED_OVERRIDE_STATE_NAME;
    }

    if (pattern.type === 'RestElement') {
        return hasReservedOverrideSlotBinding(pattern.argument);
    }

    if (pattern.type === 'AssignmentPattern') {
        return hasReservedOverrideSlotBinding(pattern.left);
    }

    if (pattern.type === 'ArrayPattern') {
        return pattern.elements.some(hasReservedOverrideSlotBinding);
    }

    if (pattern.type === 'ObjectPattern') {
        return pattern.properties.some((property) => {
            if (property.type === 'RestElement') {
                return hasReservedOverrideSlotBinding(property.argument);
            }

            if (getObjectPatternSourceKey(property) === RESERVED_OVERRIDE_STATE_NAME) {
                return true;
            }

            return hasReservedOverrideSlotBinding(property.value);
        });
    }

    return false;
}

/**
 * Rejects user-authored slot bindings that would collide with the generated override-private state channel.
 *
 */
function assertNoReservedOverrideSlotScope(slotDirective: DirectiveNode | undefined): void {
    if (!slotDirective?.exp?.content) {
        return;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);

        if (!hasReservedOverrideSlotBinding(pattern)) {
            return;
        }
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
        return;
    }

    throw new ShopwareSetupTransformError(
        '"__swOverride" is reserved for Shopware override-private state and must not be used as a slot-scope binding.',
        0,
    );
}

/**
 * Returns v-for aliases declared on an element.
 *
 */
function collectForScopeNames(forDirective: DirectiveNode | undefined): Set<string> {
    const scopeNames = new Set<string>();
    const parseResult = forDirective?.forParseResult;

    [
        parseResult?.value,
        parseResult?.key,
        parseResult?.index,
    ].forEach((expression) => {
        if (!expression?.content) {
            return;
        }

        try {
            const { pattern } = parseBindingPattern(expression.content);
            addPatternNames(pattern, scopeNames);
        } catch {
            scopeNames.add(expression.content);
        }
    });

    return scopeNames;
}

/**
 * Returns outer references used in v-for aliases, such as destructuring defaults and computed keys.
 *
 */
function collectForAliasReferences(forDirective: DirectiveNode | undefined, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();
    const parseResult = forDirective?.forParseResult;

    [
        parseResult?.value,
        parseResult?.key,
        parseResult?.index,
    ].forEach((expression) => {
        if (!expression?.content) {
            return;
        }

        try {
            const { pattern } = parseBindingPattern(expression.content);
            collectPatternReferences(pattern, templateScope, references);
        } catch {
            // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
        }
    });

    return references;
}

/**
 * Checks whether a directive is the default slot shorthand/longhand.
 *
 */
function isDefaultSlotDirective(directive: DirectiveNode): boolean {
    return Boolean(
        directive.type === NodeTypes.DIRECTIVE &&
            directive.name === 'slot' &&
            (!directive.arg || (directive.arg.isStatic && directive.arg.content === 'default')),
    );
}

/**
 * Finds the default slot directive on an element.
 *
 */
function getDefaultSlotDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && isDefaultSlotDirective(prop as DirectiveNode),
    );
}

/**
 * Returns the static v-for directive on an element, when present.
 *
 */
function getForDirective(node: ElementNode): DirectiveNode | undefined {
    return node.props.find(
        (prop): prop is DirectiveNode => prop.type === NodeTypes.DIRECTIVE && (prop as DirectiveNode).name === 'for',
    );
}

/**
 * Collects references from one directive expression and dynamic argument.
 *
 */
function collectDirectiveReferences(directive: DirectiveNode, templateScope: Set<string>): Set<string> {
    const references = new Set<string>();

    if (directive.name === 'slot') {
        if (directive.arg && !directive.arg.isStatic) {
            collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
        }

        return references;
    }

    if (directive.name === 'for') {
        collectExpressionReferences(directive.forParseResult?.source?.content, templateScope).forEach((name) =>
            references.add(name),
        );
        return references;
    }

    if (directive.arg && !directive.arg.isStatic) {
        collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
    }

    if (directive.exp?.content) {
        collectExpressionReferences(directive.exp.content, templateScope).forEach((name) => references.add(name));
        return references;
    }

    if (directive.name === 'bind' && directive.arg?.isStatic) {
        collectExpressionReferences(directive.arg.content, templateScope).forEach((name) => references.add(name));
    }

    return references;
}

/**
 * Collects references from descendants of a sw-block override slot.
 *
 */
function collectTemplateReferences(children: TemplateChildNode[], initialScope: Set<string>): Set<string> {
    const references = new Set<string>();

    function visit(node: TemplateChildNode, scope: Set<string>): void {
        if (node.type === NodeTypes.INTERPOLATION) {
            collectExpressionReferences((node.content as { content: string }).content, scope).forEach((name) =>
                references.add(name),
            );
            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        const forDirective = getForDirective(node);
        const childScope = new Set<string>(scope);
        const slotScopeNames = new Set<string>();

        if (forDirective) {
            collectDirectiveReferences(forDirective, scope).forEach((name) => references.add(name));
            collectForAliasReferences(forDirective, scope).forEach((name) => references.add(name));
            collectForScopeNames(forDirective).forEach((name) => childScope.add(name));
        }

        node.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === forDirective) {
                return;
            }

            const directive = prop as DirectiveNode;

            collectDirectiveReferences(directive, childScope).forEach((name) => references.add(name));

            if (isDefaultSlotDirective(directive)) {
                collectSlotScopeReferences(directive, childScope).forEach((name) => references.add(name));
                collectSlotScopeNames(directive).forEach((name) => slotScopeNames.add(name));
            }
        });

        const scopedChildrenScope = new Set<string>(childScope);
        slotScopeNames.forEach((name) => scopedChildrenScope.add(name));

        node.children.forEach((child) => visit(child, scopedChildrenScope));
    }

    children.forEach((child) => visit(child, new Set<string>(initialScope)));

    return references;
}

/**
 * Checks whether an element is an override block declaration.
 *
 */
function isSwBlockExtends(node: TemplateChildNode): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'extends';
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === 'extends';
    });
}

/**
 * Checks whether an element is a base sw-block declaration.
 *
 */
function isSwBlockName(node: TemplateChildNode): node is ElementNode {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'name';
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === 'name';
    });
}

/**
 * Checks whether a sw-block already declares its data scope.
 *
 */
function hasSwBlockDataProp(node: ElementNode): boolean {
    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'data';
        }

        const directive = prop as DirectiveNode;

        return directive.name === 'bind' && directive.arg?.isStatic && directive.arg.content === 'data';
    });
}

/**
 * Returns the insertion point before the closing angle bracket of an opening tag.
 *
 */
function findOpeningTagAttributeEnd(template: string, elementStart: number): number {
    let quote: '"' | "'" | null = null;

    for (let index = elementStart; index < template.length; index += 1) {
        const character = template[index];

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
            continue;
        }

        if (character === '>') {
            return template[index - 1] === '/' ? index - 1 : index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate <sw-block> opening tag end.', elementStart);
}

/**
 * Returns the insertion point immediately after an opening tag name.
 *
 */
function findOpeningTagNameEnd(template: string, elementStart: number): number {
    for (let index = elementStart + 1; index < template.length; index += 1) {
        const character = template[index];

        if (/\s|\/|>/.test(character)) {
            return index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate <sw-block> tag name end.', elementStart);
}

/**
 * Formats the private slot-scope destructuring entry for one override file namespace.
 *
 */
function createPrivateSlotMapping(namespace: string, localNames: string[]): SlotMapping {
    return {
        sourceKey: '__swOverride',
        source: `__swOverride: { ${namespace}: { ${localNames.join(', ')} } }`,
    };
}

/**
 * Extracts the source property name from an existing object pattern entry.
 *
 */
function getObjectPatternSourceKey(property: BabelNode | null | undefined): string | null {
    if (!property || property.type !== 'ObjectProperty' || property.computed) {
        return null;
    }

    if (property.key.type === 'Identifier') {
        return property.key.name;
    }

    if (property.key.type === 'StringLiteral') {
        return property.key.value;
    }

    return null;
}

/**
 * Merges generated mappings into an existing object destructuring slot expression.
 *
 */
function mergeObjectSlotExpression(expression: string, mappings: SlotMapping[]): string {
    const { pattern, offset } = parseBindingPattern(expression);

    if (pattern.type !== 'ObjectPattern') {
        throw new ShopwareSetupTransformError(
            'Shopware setup can only merge generated slot props into object or identifier default slot scopes.',
            0,
        );
    }

    const existingKeys = new Set(
        pattern.properties.map(getObjectPatternSourceKey).filter((key): key is string => Boolean(key)),
    );
    const newSources = mappings.filter((mapping) => !existingKeys.has(mapping.sourceKey)).map((mapping) => mapping.source);

    if (newSources.length === 0) {
        return expression;
    }

    const existingSources = pattern.properties.map((property) => {
        if (typeof property?.start !== 'number' || typeof property.end !== 'number') {
            throw new ShopwareSetupTransformError('Unable to merge slot scope without source ranges.', 0);
        }

        return expression.slice(property.start - offset, property.end - offset);
    });
    // Generated bindings are inserted before the first property that can read them (a default
    // value such as `{ x = fallback }`) and before any rest element. This lets existing defaults
    // reference an injected binding without a temporal-dead-zone while keeping the rest element
    // last, as object patterns require.
    const restIndex = pattern.properties.findIndex((property) => property.type === 'RestElement');
    const firstDefaultIndex = pattern.properties.findIndex(
        (property) => property.type === 'ObjectProperty' && property.value?.type === 'AssignmentPattern',
    );
    const insertionIndex = [
        restIndex,
        firstDefaultIndex,
    ]
        .filter((index) => index !== -1)
        .reduce((min, index) => Math.min(min, index), existingSources.length);
    const mergedSources = [
        ...existingSources.slice(0, insertionIndex),
        ...newSources,
        ...existingSources.slice(insertionIndex),
    ];

    return `{ ${mergedSources.join(', ')} }`;
}

/**
 * Merges generated mappings into any supported existing default slot scope.
 *
 */
function mergeSlotExpression(slotDirective: DirectiveNode | undefined, mappings: SlotMapping[]): string {
    const sources = mappings.map((mapping) => mapping.source);

    if (!slotDirective?.exp?.content) {
        return `{ ${sources.join(', ')} }`;
    }

    const expression = slotDirective.exp.content.trim();
    const { pattern } = parseBindingPattern(expression);

    if (pattern.type === 'Identifier') {
        return `{ ${sources.join(', ')}, ...${expression} }`;
    }

    if (pattern.type === 'ObjectPattern') {
        return mergeObjectSlotExpression(expression, mappings);
    }

    throw new ShopwareSetupTransformError(
        'Shopware setup can only merge generated slot props into object or identifier default slot scopes.',
        0,
    );
}

/**
 * Builds the source replacement for one sw-block default slot directive.
 *
 */
function createSlotMergeEdit(
    block: ShopwareSetupBlock,
    node: ElementNode,
    slotDirective: DirectiveNode | undefined,
    mappings: SlotMapping[],
): TemplateEdit {
    if (!block.template) {
        throw new ShopwareSetupTransformError('Unable to merge slot scope without a template block.', 0);
    }

    const mergedExpression = mergeSlotExpression(slotDirective, mappings);

    if (!slotDirective) {
        const insertionPoint = findOpeningTagAttributeEnd(block.template.content, node.loc.start.offset);

        return {
            start: block.template.contentStart + insertionPoint,
            end: block.template.contentStart + insertionPoint,
            replacement: ` #default="${mergedExpression}"`,
        };
    }

    if (!slotDirective.exp) {
        return {
            start: block.template.contentStart + slotDirective.loc.start.offset,
            end: block.template.contentStart + slotDirective.loc.end.offset,
            replacement: `${slotDirective.rawName}="${mergedExpression}"`,
        };
    }

    return {
        start: block.template.contentStart + slotDirective.exp.loc.start.offset,
        end: block.template.contentStart + slotDirective.exp.loc.end.offset,
        replacement: mergedExpression,
    };
}

/**
 * Creates the template edits and private return bindings required by override SFCs.
 *
 */
function analyzeOverrideTemplate(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): TemplateAnalysis {
    if (!block.template) {
        return {
            edits: [],
            privateBindings: new Set<string>(),
            privateNamespace: null,
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits: TemplateEdit[] = [];
    const privateBindings = new Set<string>();
    const overrideLocalNames = new Set<string>(analysis.overrideEntries);
    const privateNamespace = createOverridePrivateNamespace(block.filename, block.componentName);

    function visit(node: TemplateChildNode): void {
        if (isSwBlockExtends(node)) {
            const slotDirective = getDefaultSlotDirective(node);
            assertNoReservedOverrideSlotScope(slotDirective);

            const slotScope = collectSlotScopeNames(slotDirective);
            const references = collectTemplateReferences(node.children, slotScope);
            const publicMappings: SlotMapping[] = [];
            const privateLocalNames: string[] = [];

            collectSlotScopeReferences(slotDirective, new Set()).forEach((name) => references.add(name));

            analysis.runtimeBindings.forEach((binding) => {
                if (!references.has(binding.name)) {
                    return;
                }

                // Public override bindings keep their own name in the slot scope; only private
                // ones need the deterministic override namespace.
                if (overrideLocalNames.has(binding.name)) {
                    publicMappings.push({
                        sourceKey: binding.name,
                        source: binding.name,
                    });
                    return;
                }

                privateBindings.add(binding.name);
                privateLocalNames.push(binding.name);
            });

            const mappings = [
                ...(privateLocalNames.length > 0 ? [createPrivateSlotMapping(privateNamespace, privateLocalNames)] : []),
                ...publicMappings,
            ];

            if (mappings.length > 0) {
                edits.push(createSlotMergeEdit(block, node, slotDirective, mappings));
            }
        }

        if (node.type === NodeTypes.ELEMENT) {
            node.children.forEach(visit);
        }
    }

    ast.children.forEach(visit);

    return {
        edits,
        privateBindings,
        privateNamespace,
    };
}

/**
 * Creates template edits required by base SFCs.
 *
 */
function analyzeBaseTemplate(block: ShopwareSetupBlock): TemplateAnalysis {
    if (!block.template) {
        return {
            edits: [],
            privateBindings: new Set<string>(),
            privateNamespace: null,
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits: TemplateEdit[] = [];

    function visit(node: TemplateChildNode): void {
        if (isSwBlockName(node) && !hasSwBlockDataProp(node)) {
            if (!block.template) {
                return;
            }

            const insertionPoint = findOpeningTagNameEnd(block.template.content, node.loc.start.offset);

            edits.push({
                start: block.template.contentStart + insertionPoint,
                end: block.template.contentStart + insertionPoint,
                replacement: ' :data="$dataScope"',
            });
        }

        if (node.type === NodeTypes.ELEMENT) {
            node.children.forEach(visit);
        }
    }

    ast.children.forEach(visit);

    return {
        edits,
        privateBindings: new Set<string>(),
        privateNamespace: null,
    };
}

export {
    type SlotMapping,
    type TemplateAnalysis,
    type TemplateEdit,
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    createOverridePrivateNamespace,
};
