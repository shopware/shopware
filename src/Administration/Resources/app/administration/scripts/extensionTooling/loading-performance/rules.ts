/**
 * @sw-package framework
 */
import { AST_NODE_TYPES, TSESLint } from '@typescript-eslint/utils';
import type { TSESTree } from '@typescript-eslint/utils';

type Context = TSESLint.RuleContext<'eager', []>;
type Loader = TSESTree.ArrowFunctionExpression | TSESTree.FunctionExpression | TSESTree.FunctionDeclaration;
type Variable = TSESLint.Scope.Variable;

function unwrap(node: TSESTree.Node): TSESTree.Node {
    if (
        node.type === AST_NODE_TYPES.TSAsExpression ||
        node.type === AST_NODE_TYPES.TSSatisfiesExpression ||
        node.type === AST_NODE_TYPES.TSNonNullExpression ||
        node.type === AST_NODE_TYPES.ChainExpression
    ) {
        return unwrap(node.expression);
    }
    return node;
}

function propertyName(node: TSESTree.MemberExpression | TSESTree.Property): string | null {
    const property = node.type === AST_NODE_TYPES.MemberExpression ? node.property : node.key;
    if (!node.computed && property.type === AST_NODE_TYPES.Identifier) return property.name;
    if (property.type === AST_NODE_TYPES.Literal && typeof property.value === 'string') return property.value;
    return null;
}

function variableFor(node: TSESTree.Identifier, context: Context): Variable | undefined {
    let scope: TSESLint.Scope.Scope | null = context.sourceCode.getScope(node);
    while (scope) {
        const variable = scope.set.get(node.name);
        if (variable) return variable;
        scope = scope.upper;
    }
    return undefined;
}

function immutableInitializer(variable: Variable): TSESTree.Node | null {
    if (variable.defs.length !== 1 || variable.references.some((reference) => reference.isWrite() && !reference.init)) {
        return null;
    }
    const definition = variable.defs[0];
    if (definition.type === TSESLint.Scope.DefinitionType.FunctionName) return definition.node;
    if (definition.type !== TSESLint.Scope.DefinitionType.Variable) return null;
    return definition.node.init;
}

function destructuredPath(pattern: TSESTree.Node, identifier: TSESTree.Identifier): string[] | null {
    if (pattern === identifier) return [];
    if (pattern.type !== AST_NODE_TYPES.ObjectPattern) return null;
    for (const property of pattern.properties) {
        if (property.type !== AST_NODE_TYPES.Property) continue;
        const key = propertyName(property);
        const rest = destructuredPath(property.value, identifier);
        if (key !== null && rest !== null) return [key, ...rest];
    }
    return null;
}

function apiPath(node: TSESTree.Node, context: Context, seen = new Set<Variable>()): string[] | null {
    node = unwrap(node);
    if (node.type === AST_NODE_TYPES.MemberExpression) {
        const base = apiPath(node.object, context, seen);
        const key = propertyName(node);
        return base && key !== null ? [...base, key] : null;
    }
    if (node.type !== AST_NODE_TYPES.Identifier) return null;
    const variable = variableFor(node, context);
    if (!variable || variable.defs.length === 0) {
        if (variable?.references.some((reference) => reference.isWrite())) return null;
        if (node.name === 'Shopware') return ['Shopware'];
        if (node.name === 'window' || node.name === 'globalThis') return [];
        return null;
    }
    if (seen.has(variable)) return null;
    seen.add(variable);
    const initializer = immutableInitializer(variable);
    if (!initializer) return null;
    const definition = variable.defs[0];
    if (definition.type !== TSESLint.Scope.DefinitionType.Variable) return null;
    const suffix = destructuredPath(definition.node.id, definition.name);
    const base = apiPath(initializer, context, seen);
    return suffix !== null && base ? [...base, ...suffix] : null;
}

function isLoader(node: TSESTree.Node): node is Loader {
    return (
        node.type === AST_NODE_TYPES.ArrowFunctionExpression ||
        node.type === AST_NODE_TYPES.FunctionExpression ||
        node.type === AST_NODE_TYPES.FunctionDeclaration
    );
}

function returnValues(loader: Loader): TSESTree.Node[] {
    if (!loader.body) return [];
    if (loader.body.type !== AST_NODE_TYPES.BlockStatement) return [loader.body];
    const values: TSESTree.Node[] = [];
    function visit(node: TSESTree.Node): void {
        switch (node.type) {
            case AST_NODE_TYPES.ReturnStatement:
                if (node.argument) values.push(node.argument);
                break;
            case AST_NODE_TYPES.BlockStatement:
                node.body.forEach(visit);
                break;
            case AST_NODE_TYPES.IfStatement:
                visit(node.consequent);
                if (node.alternate) visit(node.alternate);
                break;
            case AST_NODE_TYPES.SwitchStatement:
                node.cases.forEach((branch) => branch.consequent.forEach(visit));
                break;
            case AST_NODE_TYPES.TryStatement:
                visit(node.block);
                if (node.handler) visit(node.handler.body);
                if (node.finalizer) visit(node.finalizer);
                break;
            case AST_NODE_TYPES.ForStatement:
            case AST_NODE_TYPES.ForInStatement:
            case AST_NODE_TYPES.ForOfStatement:
            case AST_NODE_TYPES.WhileStatement:
            case AST_NODE_TYPES.DoWhileStatement:
            case AST_NODE_TYPES.LabeledStatement:
                visit(node.body);
                break;
            default:
                break;
        }
    }
    visit(loader.body);
    return values;
}

function insideLoader(node: TSESTree.Node, loader: Loader | null): boolean {
    return loader !== null && node.range[0] >= loader.range[0] && node.range[1] <= loader.range[1];
}

function eagerImport(
    node: TSESTree.Node,
    context: Context,
    loader: Loader | null = null,
    seen = new Set<Variable>(),
): boolean {
    node = unwrap(node);
    if (isLoader(node)) return returnValues(node).some((value) => eagerImport(value, context, node, new Set(seen)));
    if (node.type === AST_NODE_TYPES.ImportExpression) return !insideLoader(node, loader);
    if (node.type === AST_NODE_TYPES.AwaitExpression) return eagerImport(node.argument, context, loader, seen);
    if (node.type === AST_NODE_TYPES.ConditionalExpression) {
        return (
            eagerImport(node.consequent, context, loader, new Set(seen)) ||
            eagerImport(node.alternate, context, loader, new Set(seen))
        );
    }
    if (
        node.type === AST_NODE_TYPES.CallExpression &&
        node.callee.type === AST_NODE_TYPES.MemberExpression &&
        ['then', 'catch', 'finally'].includes(propertyName(node.callee) ?? '')
    ) {
        return eagerImport(node.callee.object, context, loader, seen);
    }
    if (node.type === AST_NODE_TYPES.MemberExpression) return eagerImport(node.object, context, loader, seen);
    if (
        node.type === AST_NODE_TYPES.CallExpression &&
        node.callee.type === AST_NODE_TYPES.MemberExpression &&
        propertyName(node.callee) === 'resolve' &&
        node.callee.object.type === AST_NODE_TYPES.Identifier &&
        node.callee.object.name === 'Promise' &&
        !variableFor(node.callee.object, context)?.defs.length
    ) {
        const argument = node.arguments[0];
        return argument !== undefined && eagerImport(argument, context, loader, seen);
    }
    if (node.type !== AST_NODE_TYPES.Identifier) return false;
    const variable = variableFor(node, context);
    if (!variable || seen.has(variable)) return false;
    seen.add(variable);
    const definition = variable.defs[0];
    if (variable.defs.length === 1 && definition.type === TSESLint.Scope.DefinitionType.ImportBinding) {
        if (definition.parent.type !== AST_NODE_TYPES.ImportDeclaration) return false;
        if (
            definition.parent.importKind === 'type' ||
            ('importKind' in definition.node && definition.node.importKind === 'type')
        )
            return false;
        // An imported function can itself be a legitimate loader. Only SFC imports or
        // values returned from a loader are unambiguously imported implementations.
        return loader !== null || definition.parent.source.value.endsWith('.vue');
    }
    const initializer = immutableInitializer(variable);
    return initializer !== null && eagerImport(initializer, context, loader, seen);
}

export const noEagerComponentImports: TSESLint.RuleModule<'eager', []> = {
    meta: {
        type: 'suggestion',
        docs: { description: 'Keep component implementation imports inside their registered loaders.' },
        schema: [],
        messages: {
            eager: 'Import the component inside its loader. Returning an already imported module or an import that already started does not defer loading.',
        },
    },
    defaultOptions: [],
    create(context) {
        return {
            CallExpression(node) {
                const path = apiPath(node.callee, context);
                if (!path || path.length !== 3 || path[0] !== 'Shopware' || path[1] !== 'Component') return;
                const method = path[2];
                if (!['register', 'override', 'extend'].includes(method)) return;
                const config = node.arguments[method === 'extend' ? 2 : 1];
                if (config && eagerImport(config, context)) context.report({ node: config, messageId: 'eager' });
            },
        };
    },
};

function stringValue(node: TSESTree.Node): string | null {
    node = unwrap(node);
    if (node.type === AST_NODE_TYPES.Literal && typeof node.value === 'string') return node.value;
    if (node.type === AST_NODE_TYPES.TemplateLiteral && node.expressions.length === 0) return node.quasis[0].value.cooked;
    return null;
}

function implementationGlob(node: TSESTree.Node): boolean {
    node = unwrap(node);
    if (node.type === AST_NODE_TYPES.ArrayExpression)
        return node.elements.some((item) => item !== null && implementationGlob(item));
    const pattern = stringValue(node);
    if (pattern === null || pattern.startsWith('!') || pattern.endsWith('.override.vue')) return false;
    return /(?:^|\/)(?:components?|pages?|views?)(?:\/|$)/.test(pattern) || pattern.endsWith('.vue');
}

function isEager(options: TSESTree.Node | undefined): boolean {
    if (!options) return false;
    options = unwrap(options);
    if (options.type !== AST_NODE_TYPES.ObjectExpression) return false;
    let eager = false;
    for (const property of options.properties) {
        if (property.type === AST_NODE_TYPES.SpreadElement || propertyName(property) === null) eager = false;
        else if (propertyName(property) === 'eager') {
            eager = property.value.type === AST_NODE_TYPES.Literal && property.value.value === true;
        }
    }
    return eager;
}

export const noEagerComponentGlob: TSESLint.RuleModule<'eager', []> = {
    meta: {
        type: 'suggestion',
        docs: { description: 'Keep implementation globs lazy in component startup code.' },
        schema: [],
        messages: {
            eager: 'Keep component, page and view globs lazy. Document an exception when their startup side effects are required.',
        },
    },
    defaultOptions: [],
    create(context) {
        return {
            CallExpression(node) {
                const callee = node.callee;
                if (
                    callee.type !== AST_NODE_TYPES.MemberExpression ||
                    callee.object.type !== AST_NODE_TYPES.MetaProperty ||
                    callee.object.meta.name !== 'import' ||
                    callee.object.property.name !== 'meta'
                )
                    return;
                const method = propertyName(callee);
                if (method !== 'glob' && method !== 'globEager') return;
                const pattern = node.arguments[0];
                if (pattern && implementationGlob(pattern) && (method === 'globEager' || isEager(node.arguments[1]))) {
                    context.report({ node, messageId: 'eager' });
                }
            },
        };
    },
};

export default {
    'no-eager-component-imports': noEagerComponentImports,
    'no-eager-component-glob': noEagerComponentGlob,
};
