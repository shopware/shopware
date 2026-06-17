/**
 * @sw-package framework
 */

const crypto = require('crypto');
const path = require('path');
const { parse: parseTemplate, NodeTypes } = require('@vue/compiler-dom');
const { parse, parseExpression } = require('@babel/parser');
const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('@vue/compiler-core').TemplateChildNode} TemplateChildNode
 * @typedef {import('@vue/compiler-core').ElementNode} ElementNode
 * @typedef {import('@vue/compiler-core').DirectiveNode} DirectiveNode
 * @typedef {import('@babel/types').Node} BabelNode
 * @typedef {import('@babel/types').PatternLike} PatternLike
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('./script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 * @typedef {import('./script-analyzer').PublicEntry} OverrideEntry
 *
 * @typedef {object} TemplateEdit
 * @property {number} start
 * @property {number} end
 * @property {string} replacement
 *
 * @typedef {object} SlotMapping
 * @property {string} sourceKey
 * @property {string} source
 */

const EXPRESSION_PLUGINS = [
    'typescript',
];

/**
 * Keeps override-private state names stable for builds and tests.
 *
 * @param {string} filename
 * @param {string} componentName
 * @param {string} localName
 * @returns {string}
 */
function createOverridePrivateAlias(filename, componentName, localName) {
    const normalizedFilename = path.normalize(filename).split(path.sep).join('/');
    const hash = crypto.createHash('sha1').update(`${normalizedFilename}:${componentName}`).digest('hex').slice(0, 5);

    return `__swOverride_${hash}_${localName}`;
}

/**
 * Parses template JavaScript snippets as expressions first, then as statements for inline handlers.
 *
 * @param {string} source
 * @returns {BabelNode}
 */
function parseTemplateExpression(source) {
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
 * @param {string} source
 * @returns {{ pattern: PatternLike, offset: number }}
 */
function parseBindingPattern(source) {
    const prefix = 'const ';
    const ast = parse(`${prefix}${source} = __slot;`, {
        sourceType: 'module',
        plugins: EXPRESSION_PLUGINS,
    });
    const declaration = ast.program.body[0].declarations[0];

    return {
        pattern: declaration.id,
        offset: prefix.length,
    };
}

/**
 * Adds all names declared by one binding pattern to a scope.
 *
 * @param {BabelNode | null | undefined} pattern
 * @param {Set<string>} scope
 * @returns {void}
 */
function addPatternNames(pattern, scope) {
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
 * @param {BabelNode | null | undefined} pattern
 * @param {Set<string>} templateScope
 * @param {Set<string>} references
 * @returns {void}
 */
function collectPatternReferences(pattern, templateScope, references) {
    if (!pattern) {
        return;
    }

    if (pattern.type === 'Identifier') {
        return;
    }

    if (pattern.type === 'RestElement') {
        collectPatternReferences(pattern.argument, templateScope, references);
        return;
    }

    if (pattern.type === 'AssignmentPattern') {
        collectPatternReferences(pattern.left, templateScope, references);
        collectBabelReferences(pattern.right, [templateScope], references, pattern, 'right');
        return;
    }

    if (pattern.type === 'ArrayPattern') {
        pattern.elements.forEach((element) => collectPatternReferences(element, templateScope, references));
        return;
    }

    if (pattern.type === 'ObjectPattern') {
        pattern.properties.forEach((property) => {
            if (property.type === 'RestElement') {
                collectPatternReferences(property.argument, templateScope, references);
                return;
            }

            if (property.computed) {
                collectBabelReferences(property.key, [templateScope], references, property, 'key');
            }

            collectPatternReferences(property.value, templateScope, references);
        });
    }
}

/**
 * Checks whether a name is declared by the template or JavaScript expression itself.
 *
 * @param {string} name
 * @param {Set<string>[]} scopes
 * @returns {boolean}
 */
function isDeclared(name, scopes) {
    return scopes.some((scope) => scope.has(name));
}

/**
 * Filters AST fields that do not contain runtime JavaScript references.
 *
 * @param {string} key
 * @returns {boolean}
 */
function shouldSkipBabelKey(key) {
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
 * @param {BabelNode} node
 * @param {BabelNode | null} parent
 * @param {string | null} parentKey
 * @returns {boolean}
 */
function isIdentifierReference(node, parent, parentKey) {
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
 * @param {BabelNode | null | undefined} node
 * @param {Set<string>[]} scopes
 * @param {Set<string>} references
 * @param {BabelNode | null} [parent]
 * @param {string | null} [parentKey]
 * @returns {void}
 */
function collectBabelReferences(node, scopes, references, parent = null, parentKey = null) {
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
        const blockScope = new Set();
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
        if (node.id) {
            scopes[0].add(node.id.name);
        }

        const functionScope = new Set();
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
        const catchScope = new Set();
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

    Object.entries(node).forEach(
        ([
            key,
            value,
        ]) => {
            if (shouldSkipBabelKey(key)) {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((child) => {
                    if (child && typeof child.type === 'string') {
                        collectBabelReferences(child, scopes, references, node, key);
                    }
                });
                return;
            }

            if (value && typeof value.type === 'string') {
                collectBabelReferences(value, scopes, references, node, key);
            }
        },
    );
}

/**
 * Collects runtime references from one Vue expression while honoring aliases from parent template scopes.
 *
 * @param {string | undefined} expression
 * @param {Set<string>} templateScope
 * @returns {Set<string>}
 */
function collectExpressionReferences(expression, templateScope) {
    const references = new Set();

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
 * @param {DirectiveNode | undefined}
 * @returns {Set<string>}
 */
function collectSlotScopeNames(slotDirective) {
    const scopeNames = new Set();

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
 * @param {DirectiveNode | undefined}
 * @param {Set<string>} templateScope
 * @returns {Set<string>}
 */
function collectSlotScopeReferences(slotDirective, templateScope) {
    const references = new Set();

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
 * Returns v-for aliases declared on an element.
 *
 * @param {DirectiveNode | undefined}
 * @returns {Set<string>}
 */
function collectForScopeNames(forDirective) {
    const scopeNames = new Set();
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
 * @param {DirectiveNode | undefined}
 * @param {Set<string>} templateScope
 * @returns {Set<string>}
 */
function collectForAliasReferences(forDirective, templateScope) {
    const references = new Set();
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
 * @param {DirectiveNode} directive
 * @returns {boolean}
 */
function isDefaultSlotDirective(directive) {
    return (
        directive.type === NodeTypes.DIRECTIVE &&
        directive.name === 'slot' &&
        (!directive.arg || (directive.arg.isStatic && directive.arg.content === 'default'))
    );
}

/**
 * Finds the default slot directive on an element.
 *
 * @param {ElementNode} node
 * @returns {DirectiveNode | undefined}
 */
function getDefaultSlotDirective(node) {
    return node.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && isDefaultSlotDirective(prop));
}

/**
 * Returns the static v-for directive on an element, when present.
 *
 * @param {ElementNode} node
 * @returns {DirectiveNode | undefined}
 */
function getForDirective(node) {
    return node.props.find((prop) => prop.type === NodeTypes.DIRECTIVE && prop.name === 'for');
}

/**
 * Collects references from one directive expression and dynamic argument.
 *
 * @param {DirectiveNode} directive
 * @param {Set<string>} templateScope
 * @returns {Set<string>}
 */
function collectDirectiveReferences(directive, templateScope) {
    const references = new Set();

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
 * @param {TemplateChildNode[]} children
 * @param {Set<string>} initialScope
 * @returns {Set<string>}
 */
function collectTemplateReferences(children, initialScope) {
    const references = new Set();

    /**
     * @param {TemplateChildNode} node
     * @param {Set<string>} scope
     * @returns {void}
     */
    function visit(node, scope) {
        if (node.type === NodeTypes.INTERPOLATION) {
            collectExpressionReferences(node.content.content, scope).forEach((name) => references.add(name));
            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        const forDirective = getForDirective(node);
        const childScope = new Set(scope);
        const slotScopeNames = new Set();

        if (forDirective) {
            collectDirectiveReferences(forDirective, scope).forEach((name) => references.add(name));
            collectForAliasReferences(forDirective, scope).forEach((name) => references.add(name));
            collectForScopeNames(forDirective).forEach((name) => childScope.add(name));
        }

        node.props.forEach((prop) => {
            if (prop.type !== NodeTypes.DIRECTIVE || prop === forDirective) {
                return;
            }

            collectDirectiveReferences(prop, childScope).forEach((name) => references.add(name));

            if (isDefaultSlotDirective(prop)) {
                collectSlotScopeReferences(prop, childScope).forEach((name) => references.add(name));
                collectSlotScopeNames(prop).forEach((name) => slotScopeNames.add(name));
            }
        });

        const scopedChildrenScope = new Set(childScope);
        slotScopeNames.forEach((name) => scopedChildrenScope.add(name));

        node.children.forEach((child) => visit(child, scopedChildrenScope));
    }

    children.forEach((child) => visit(child, new Set(initialScope)));

    return references;
}

/**
 * Checks whether an element is an override block declaration.
 *
 * @param {TemplateChildNode} node
 * @returns {node is ElementNode}
 */
function isSwBlockExtends(node) {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'extends';
        }

        return prop.name === 'bind' && prop.arg?.isStatic && prop.arg.content === 'extends';
    });
}

/**
 * Checks whether an element is a base sw-block declaration.
 *
 * @param {TemplateChildNode} node
 * @returns {node is ElementNode}
 */
function isSwBlockName(node) {
    if (node.type !== NodeTypes.ELEMENT || node.tag !== 'sw-block') {
        return false;
    }

    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'name';
        }

        return prop.name === 'bind' && prop.arg?.isStatic && prop.arg.content === 'name';
    });
}

/**
 * Checks whether a sw-block already declares its data scope.
 *
 * @param {ElementNode} node
 * @returns {boolean}
 */
function hasSwBlockDataProp(node) {
    return node.props.some((prop) => {
        if (prop.type === NodeTypes.ATTRIBUTE) {
            return prop.name === 'data';
        }

        return prop.name === 'bind' && prop.arg?.isStatic && prop.arg.content === 'data';
    });
}

/**
 * Returns the insertion point before the closing angle bracket of an opening tag.
 *
 * @param {string} template
 * @param {number} elementStart
 * @returns {number}
 */
function findOpeningTagAttributeEnd(template, elementStart) {
    let quote = null;

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
 * @param {string} template
 * @param {number} elementStart
 * @returns {number}
 */
function findOpeningTagNameEnd(template, elementStart) {
    for (let index = elementStart + 1; index < template.length; index += 1) {
        const character = template[index];

        if (/\s|\/|>/.test(character)) {
            return index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate <sw-block> tag name end.', elementStart);
}

/**
 * Formats one slot-scope destructuring entry.
 *
 * @param {OverrideEntry['key']} key
 * @param {string} localName
 * @returns {SlotMapping}
 */
function createPublicSlotMapping(key, localName) {
    if (key.type === 'identifier' && key.value === localName) {
        return {
            sourceKey: key.value,
            source: key.value,
        };
    }

    if (key.type === 'identifier') {
        return {
            sourceKey: key.value,
            source: `${key.value}: ${localName}`,
        };
    }

    return {
        sourceKey: key.value,
        source: `'${key.value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}': ${localName}`,
    };
}

/**
 * Formats one private slot-scope destructuring entry.
 *
 * @param {string} privateAlias
 * @param {string} localName
 * @returns {SlotMapping}
 */
function createPrivateSlotMapping(privateAlias, localName) {
    return {
        sourceKey: privateAlias,
        source: `${privateAlias}: ${localName}`,
    };
}

/**
 * Extracts the source property name from an existing object pattern entry.
 *
 * @param {BabelNode} property
 * @returns {string | null}
 */
function getObjectPatternSourceKey(property) {
    if (property.type !== 'ObjectProperty' || property.computed) {
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
 * @param {string} expression
 * @param {SlotMapping[]} mappings
 * @returns {string}
 */
function mergeObjectSlotExpression(expression, mappings) {
    const { pattern, offset } = parseBindingPattern(expression);

    if (pattern.type !== 'ObjectPattern') {
        throw new ShopwareSetupTransformError(
            'Shopware setup can only merge generated slot props into object or identifier default slot scopes.',
            0,
        );
    }

    const existingKeys = new Set(pattern.properties.map(getObjectPatternSourceKey).filter(Boolean));
    const newSources = mappings.filter((mapping) => !existingKeys.has(mapping.sourceKey)).map((mapping) => mapping.source);

    if (newSources.length === 0) {
        return expression;
    }

    const existingSources = pattern.properties.map((property) =>
        expression.slice(property.start - offset, property.end - offset),
    );
    const restIndex = pattern.properties.findIndex((property) => property.type === 'RestElement');
    const mergedSources =
        restIndex === -1
            ? [
                  ...existingSources,
                  ...newSources,
              ]
            : [
                  ...existingSources.slice(0, restIndex),
                  ...newSources,
                  ...existingSources.slice(restIndex),
              ];

    return `{ ${mergedSources.join(', ')} }`;
}

/**
 * Merges generated mappings into any supported existing default slot scope.
 *
 * @param {DirectiveNode | undefined} slotDirective
 * @param {SlotMapping[]} mappings
 * @returns {string}
 */
function mergeSlotExpression(slotDirective, mappings) {
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
 * @param {ShopwareSetupBlock} block
 * @param {ElementNode} node
 * @param {DirectiveNode | undefined} slotDirective
 * @param {SlotMapping[]} mappings
 * @returns {TemplateEdit}
 */
function createSlotMergeEdit(block, node, slotDirective, mappings) {
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
 * Creates the template edits and private return aliases required by override SFCs.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {{ edits: TemplateEdit[], privateAliases: Map<string, string> }}
 */
function analyzeOverrideTemplate(block, analysis) {
    if (!block.template) {
        return {
            edits: [],
            privateAliases: new Map(),
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits = [];
    const privateAliases = new Map();
    const overrideEntryByLocalName = new Map(
        analysis.overrideEntries.map((entry) => [
            entry.localName,
            entry,
        ]),
    );

    /**
     * @param {TemplateChildNode} node
     * @returns {void}
     */
    function visit(node) {
        if (isSwBlockExtends(node)) {
            const slotDirective = getDefaultSlotDirective(node);
            const slotScope = collectSlotScopeNames(slotDirective);
            const references = collectTemplateReferences(node.children, slotScope);
            const mappings = [];

            collectSlotScopeReferences(slotDirective, new Set()).forEach((name) => references.add(name));

            analysis.runtimeBindings.forEach((binding) => {
                if (!references.has(binding.name)) {
                    return;
                }

                const overrideEntry = overrideEntryByLocalName.get(binding.name);

                if (overrideEntry) {
                    mappings.push(createPublicSlotMapping(overrideEntry.key, binding.name));
                    return;
                }

                const privateAlias = createOverridePrivateAlias(block.filename, block.componentName, binding.name);
                privateAliases.set(binding.name, privateAlias);
                mappings.push(createPrivateSlotMapping(privateAlias, binding.name));
            });

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
        privateAliases,
    };
}

/**
 * Creates template edits required by base SFCs.
 *
 * @param {ShopwareSetupBlock} block
 * @returns {{ edits: TemplateEdit[], privateAliases: Map<string, string> }}
 */
function analyzeBaseTemplate(block) {
    if (!block.template) {
        return {
            edits: [],
            privateAliases: new Map(),
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits = [];

    /**
     * @param {TemplateChildNode} node
     * @returns {void}
     */
    function visit(node) {
        if (isSwBlockName(node) && !hasSwBlockDataProp(node)) {
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
        privateAliases: new Map(),
    };
}

module.exports = {
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    createOverridePrivateAlias,
};
