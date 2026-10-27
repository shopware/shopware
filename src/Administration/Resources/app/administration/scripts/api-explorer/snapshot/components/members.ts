/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import type {
    ApiMeta,
    ComputedEntry,
    MethodEntry,
    NamedEntry,
    ParameterEntry,
    PropEntry,
    SnapshotDiagnostic,
    TypedEntry,
    TypeSource,
} from '../types';
import { leadingComments, parseDeprecatedOption, parseDocInfo, type DocInfo } from './doc-comments';
import { typeText, widenedTypeText } from './type-text';

export interface MemberScope {
    context: AnalysisContext;
    sourceFile: ts.SourceFile;
    /** Admin-root relative */
    file: string;
    isTypeScript: boolean;
    /** Instance type of a `wrapComponentConfig`/`defineComponent` call. */
    instanceType: ts.Type | null;
    diagnostics: SnapshotDiagnostic[];
}

export interface ComponentMembers {
    props: PropEntry[];
    emits: NamedEntry[];
    data: TypedEntry[];
    computed: ComputedEntry[];
    methods: MethodEntry[];
    inject: NamedEntry[];
    mixins: NamedEntry[];
}

type FunctionNode = ts.MethodDeclaration | ts.FunctionExpression | ts.ArrowFunction | ts.FunctionDeclaration;

const MAX_RESOLVE_DEPTH = 5;

export function normalizeSource(text: string): string {
    return text.replace(/\s+/g, ' ').trim();
}

export function lineOf(node: ts.Node): number {
    const sourceFile = node.getSourceFile();

    return sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile)).line + 1;
}

export function propertyName(name: ts.PropertyName | undefined): string | null {
    if (!name) {
        return null;
    }

    if (ts.isIdentifier(name) || ts.isStringLiteralLike(name) || ts.isNumericLiteral(name) || ts.isPrivateIdentifier(name)) {
        return name.text;
    }

    if (ts.isComputedPropertyName(name) && ts.isStringLiteralLike(name.expression)) {
        return name.expression.text;
    }

    return null;
}

function skipOuterExpressions(expression: ts.Expression): ts.Expression {
    let current = expression;

    while (
        ts.isParenthesizedExpression(current) ||
        ts.isAsExpression(current) ||
        ts.isSatisfiesExpression(current) ||
        ts.isNonNullExpression(current) ||
        ts.isTypeAssertionExpression(current)
    ) {
        current = current.expression;
    }

    return current;
}

export function symbolOf(context: AnalysisContext, identifier: ts.Identifier): ts.Symbol | undefined {
    const { parent } = identifier;

    return ts.isShorthandPropertyAssignment(parent) && parent.name === identifier
        ? context.checker.getShorthandAssignmentValueSymbol(parent)
        : context.checker.getSymbolAtLocation(identifier);
}

/** Follows identifiers to the initializer of their variable declaration. */
export function resolveExpression(context: AnalysisContext, expression: ts.Expression, depth = 0): ts.Expression {
    const current = skipOuterExpressions(expression);

    if (!ts.isIdentifier(current) || depth >= MAX_RESOLVE_DEPTH) {
        return current;
    }

    let symbol = symbolOf(context, current);

    if (symbol && symbol.flags & ts.SymbolFlags.Alias) {
        symbol = context.checker.getAliasedSymbol(symbol);
    }

    const declaration = symbol?.declarations?.find(ts.isVariableDeclaration);

    return declaration?.initializer ? resolveExpression(context, declaration.initializer, depth + 1) : current;
}

function optionElement(options: ts.ObjectLiteralExpression, key: string): ts.ObjectLiteralElementLike | undefined {
    return options.properties.filter((property) => propertyName(property.name) === key).pop();
}

export function optionValue(
    context: AnalysisContext,
    options: ts.ObjectLiteralExpression,
    key: string,
): ts.Expression | ts.MethodDeclaration | undefined {
    const element = optionElement(options, key);

    if (!element) {
        return undefined;
    }

    if (ts.isPropertyAssignment(element)) {
        return resolveExpression(context, element.initializer);
    }

    if (ts.isShorthandPropertyAssignment(element)) {
        return resolveExpression(context, element.name);
    }

    return ts.isMethodDeclaration(element) ? element : undefined;
}

function docOf(scope: MemberScope, node: ts.Node): DocInfo {
    return parseDocInfo(leadingComments(node));
}

function metaOf(scope: MemberScope, node: ts.Node, name: string, doc = docOf(scope, node)): ApiMeta {
    return {
        visibility: doc.visibility === 'public' && name.startsWith('_') ? 'private' : doc.visibility,
        deprecation: doc.deprecation,
        location: { file: scope.file, line: lineOf(node) },
    };
}

function typeSourceOf(scope: MemberScope, doc: DocInfo): TypeSource {
    if (scope.isTypeScript) {
        return 'typescript';
    }

    return doc.hasTypeTags ? 'jsdoc' : 'inferred';
}

function spreadTypeSource(scope: MemberScope, member: SpreadMember): TypeSource {
    if (!member.type) {
        return 'unknown';
    }

    return scope.isTypeScript ? 'typescript' : 'inferred';
}

function isFunctionNode(node: ts.Node): node is FunctionNode {
    return (
        ts.isMethodDeclaration(node) ||
        ts.isFunctionExpression(node) ||
        ts.isArrowFunction(node) ||
        ts.isFunctionDeclaration(node)
    );
}

function asObjectLiteral(node: ts.Node | undefined): ts.ObjectLiteralExpression | null {
    return node && ts.isObjectLiteralExpression(node) ? node : null;
}

function report(scope: MemberScope, node: ts.Node, message: string): void {
    scope.diagnostics.push({ file: scope.file, message: `${message} (line ${lineOf(node)})` });
}

function propTypeText(scope: MemberScope, expression: ts.Expression): string {
    const unwrapped = ts.isParenthesizedExpression(expression) ? expression.expression : expression;

    if (
        ts.isAsExpression(unwrapped) &&
        ts.isTypeReferenceNode(unwrapped.type) &&
        unwrapped.type.typeName.getText() === 'PropType' &&
        unwrapped.type.typeArguments?.length === 1
    ) {
        const { checker } = scope.context;

        return typeText(scope.context, checker.getTypeFromTypeNode(unwrapped.type.typeArguments[0]));
    }

    if (ts.isArrayLiteralExpression(unwrapped)) {
        return unwrapped.elements.map((element) => propTypeText(scope, element)).join(' | ');
    }

    return normalizeSource(unwrapped.getText());
}

function extractProps(scope: MemberScope, options: ts.ObjectLiteralExpression): PropEntry[] {
    const value = optionValue(scope.context, options, 'props');

    if (!value) {
        return [];
    }

    if (ts.isArrayLiteralExpression(value)) {
        return value.elements.filter(ts.isStringLiteralLike).map((element) => ({
            name: element.text,
            type: null,
            required: false,
            default: null,
            ...metaOf(scope, element, element.text),
        }));
    }

    if (!ts.isObjectLiteralExpression(value)) {
        report(scope, value, 'The props option is not an object or array literal');
        return [];
    }

    const props: PropEntry[] = [];

    for (const property of value.properties) {
        const name = propertyName(property.name);

        if (!name || ts.isSpreadAssignment(property)) {
            report(scope, property, 'Cannot resolve the prop name');
            continue;
        }

        const doc = docOf(scope, property);
        const meta = metaOf(scope, property, name, doc);
        const prop: PropEntry = { name, type: null, required: false, default: null, ...meta };
        const definition = ts.isPropertyAssignment(property) ? skipOuterExpressions(property.initializer) : null;

        if (definition && ts.isObjectLiteralExpression(definition)) {
            const type = optionElement(definition, 'type');
            const required = optionElement(definition, 'required');
            const defaultValue = optionElement(definition, 'default');
            const deprecated = optionElement(definition, 'deprecated');

            prop.type = type && ts.isPropertyAssignment(type) ? propTypeText(scope, type.initializer) : null;
            prop.required =
                !!required && ts.isPropertyAssignment(required) && required.initializer.kind === ts.SyntaxKind.TrueKeyword;
            prop.default = defaultValue
                ? normalizeSource(
                      ts.isPropertyAssignment(defaultValue) ? defaultValue.initializer.getText() : defaultValue.getText(),
                  )
                : null;
            prop.deprecation =
                (deprecated && ts.isPropertyAssignment(deprecated) ? parseDeprecatedOption(deprecated.initializer) : null) ??
                meta.deprecation;
        } else if (definition && definition.kind !== ts.SyntaxKind.NullKeyword) {
            prop.type = propTypeText(scope, definition);
        }

        props.push(prop);
    }

    return props;
}

function collectEmitCalls(scope: MemberScope, root: ts.Node, emits: Map<string, NamedEntry>): void {
    const visit = (node: ts.Node): void => {
        if (
            ts.isCallExpression(node) &&
            ts.isPropertyAccessExpression(node.expression) &&
            node.expression.name.text === '$emit' &&
            node.arguments[0] &&
            ts.isStringLiteralLike(node.arguments[0])
        ) {
            const name = node.arguments[0].text;

            if (!emits.has(name)) {
                emits.set(name, {
                    name,
                    visibility: 'public',
                    deprecation: null,
                    location: { file: scope.file, line: lineOf(node) },
                });
            }
        }

        ts.forEachChild(node, visit);
    };

    visit(root);
}

function extractEmits(scope: MemberScope, options: ts.ObjectLiteralExpression): NamedEntry[] {
    const emits = new Map<string, NamedEntry>();
    const value = optionValue(scope.context, options, 'emits');

    if (value && ts.isArrayLiteralExpression(value)) {
        for (const element of value.elements) {
            if (ts.isStringLiteralLike(element)) {
                emits.set(element.text, { name: element.text, ...metaOf(scope, element, element.text) });
            }
        }
    } else if (value && ts.isObjectLiteralExpression(value)) {
        for (const property of value.properties) {
            const name = propertyName(property.name);

            if (name) {
                emits.set(name, { name, ...metaOf(scope, property, name) });
            }
        }
    } else if (value) {
        report(scope, value, 'The emits option is not an object or array literal');
    }

    collectEmitCalls(scope, options, emits);

    return [...emits.values()];
}

function returnedExpression(fn: FunctionNode): ts.Expression | null {
    if (!fn.body) {
        return null;
    }

    if (!ts.isBlock(fn.body)) {
        return fn.body;
    }

    let found: ts.Expression | null = null;
    const visit = (node: ts.Node): void => {
        if (found || isFunctionNode(node) || ts.isClassLike(node)) {
            return;
        }

        if (ts.isReturnStatement(node) && node.expression) {
            found = node.expression;
            return;
        }

        ts.forEachChild(node, visit);
    };

    ts.forEachChild(fn.body, visit);

    return found;
}

function instanceMemberType(scope: MemberScope, name: string): ts.Type | null {
    const symbol = scope.instanceType && scope.context.checker.getPropertyOfType(scope.instanceType, name);

    return symbol ? scope.context.checker.getTypeOfSymbol(symbol) : null;
}

interface SpreadMember {
    name: string;
    type: ts.Type | null;
}

/** Map helpers that the checker cannot type, like `mapState(store, ['a'])`, still name their keys literally. */
function literalSpreadKeys(expression: ts.Expression): string[] {
    const call = skipOuterExpressions(expression);
    const keys = ts.isCallExpression(call) ? call.arguments[call.arguments.length - 1] : undefined;

    if (keys && ts.isArrayLiteralExpression(keys) && keys.elements.every(ts.isStringLiteralLike)) {
        return keys.elements.map((element) => (element as ts.StringLiteralLike).text);
    }

    if (keys && ts.isObjectLiteralExpression(keys)) {
        return keys.properties.map((property) => propertyName(property.name)).filter((name) => name !== null);
    }

    return [];
}

function spreadMembers(scope: MemberScope, spread: ts.SpreadAssignment, option: string): SpreadMember[] {
    const { checker } = scope.context;
    const type = checker.getTypeAtLocation(spread.expression);
    const properties = type.flags & (ts.TypeFlags.Any | ts.TypeFlags.Unknown) ? [] : checker.getPropertiesOfType(type);

    if (properties.length > 0) {
        return properties.map((symbol) => ({
            name: symbol.name,
            type: checker.getTypeOfSymbolAtLocation(symbol, spread),
        }));
    }

    const keys = literalSpreadKeys(spread.expression);

    if (keys.length === 0) {
        report(scope, spread, `Cannot resolve the keys of the ${option} spread '${normalizeSource(spread.getText())}'`);
    }

    return keys.map((name) => ({ name, type: null }));
}

function extractData(scope: MemberScope, options: ts.ObjectLiteralExpression): TypedEntry[] {
    const value = optionValue(scope.context, options, 'data');

    if (!value) {
        return [];
    }

    const returned = isFunctionNode(value) ? returnedExpression(value) : value;
    const literal = returned && asObjectLiteral(resolveExpression(scope.context, returned));

    if (!literal) {
        report(scope, returned ?? value, 'data() does not return an object literal');
        return [];
    }

    const { checker } = scope.context;
    const data: TypedEntry[] = [];

    for (const property of literal.properties) {
        if (ts.isSpreadAssignment(property)) {
            for (const member of spreadMembers(scope, property, 'data')) {
                data.push({
                    name: member.name,
                    type: member.type ? widenedTypeText(scope.context, member.type) : 'unknown',
                    typeSource: spreadTypeSource(scope, member),
                    ...metaOf(scope, property, member.name),
                });
            }
            continue;
        }

        const name = propertyName(property.name);

        if (!name) {
            report(scope, property, 'Cannot resolve the data key');
            continue;
        }

        const doc = docOf(scope, property);
        const valueNode = ts.isPropertyAssignment(property) ? property.initializer : property.name;
        const type = instanceMemberType(scope, name) ?? checker.getTypeAtLocation(valueNode);

        data.push({
            name,
            type: widenedTypeText(scope.context, type),
            typeSource: typeSourceOf(scope, doc),
            ...metaOf(scope, property, name, doc),
        });
    }

    return data;
}

function returnTypeOf(scope: MemberScope, type: ts.Type): ts.Type {
    const [signature] = type.getCallSignatures();

    return signature ? scope.context.checker.getReturnTypeOfSignature(signature) : type;
}

function functionReturnType(scope: MemberScope, fn: FunctionNode): ts.Type | null {
    const signature = scope.context.checker.getSignatureFromDeclaration(fn);

    return signature ? scope.context.checker.getReturnTypeOfSignature(signature) : null;
}

function extractComputed(scope: MemberScope, options: ts.ObjectLiteralExpression): ComputedEntry[] {
    const value = optionValue(scope.context, options, 'computed');

    if (!value) {
        return [];
    }

    if (!ts.isObjectLiteralExpression(value)) {
        report(scope, value, 'The computed option is not an object literal');
        return [];
    }

    const { checker } = scope.context;
    const computed: ComputedEntry[] = [];

    for (const property of value.properties) {
        if (ts.isSpreadAssignment(property)) {
            for (const member of spreadMembers(scope, property, 'computed')) {
                computed.push({
                    name: member.name,
                    type: member.type ? typeText(scope.context, returnTypeOf(scope, member.type)) : 'unknown',
                    typeSource: spreadTypeSource(scope, member),
                    writable: false,
                    ...metaOf(scope, property, member.name),
                });
            }
            continue;
        }

        const name = propertyName(property.name);

        if (!name) {
            report(scope, property, 'Cannot resolve the computed key');
            continue;
        }

        const doc = docOf(scope, property);
        let definition: ts.Node = property;

        if (ts.isPropertyAssignment(property)) {
            definition = resolveExpression(scope.context, property.initializer);
        } else if (ts.isShorthandPropertyAssignment(property)) {
            definition = resolveExpression(scope.context, property.name);
        }

        const accessors = asObjectLiteral(definition);
        const getter = accessors ? optionValue(scope.context, accessors, 'get') : definition;
        const writable = !!accessors && !!optionElement(accessors, 'set');
        let type = instanceMemberType(scope, name);

        if (!type && getter && isFunctionNode(getter)) {
            type = functionReturnType(scope, getter);
        } else if (!type && getter && ts.isExpression(getter)) {
            type = returnTypeOf(scope, checker.getTypeAtLocation(getter));
        }

        computed.push({
            name,
            type: type ? typeText(scope.context, type) : 'unknown',
            typeSource: type ? typeSourceOf(scope, doc) : 'unknown',
            writable,
            ...metaOf(scope, property, name, doc),
        });
    }

    return computed;
}

function parametersOfDeclaration(scope: MemberScope, fn: FunctionNode): ParameterEntry[] {
    return fn.parameters
        .filter((parameter) => !(ts.isIdentifier(parameter.name) && parameter.name.text === 'this'))
        .map((parameter) => ({
            name: normalizeSource(parameter.name.getText()),
            type: typeText(
                scope.context,
                scope.context.checker.getTypeAtLocation(ts.isIdentifier(parameter.name) ? parameter.name : parameter),
            ),
            optional: !!parameter.questionToken || !!parameter.initializer,
            rest: !!parameter.dotDotDotToken,
        }));
}

function parametersOfSignature(scope: MemberScope, signature: ts.Signature, location: ts.Node): ParameterEntry[] {
    return signature.parameters.map((symbol) => {
        const declaration = symbol.valueDeclaration;
        const parameter = declaration && ts.isParameter(declaration) ? declaration : null;

        return {
            name: symbol.name,
            type: typeText(scope.context, scope.context.checker.getTypeOfSymbolAtLocation(symbol, location)),
            optional: !!parameter && (!!parameter.questionToken || !!parameter.initializer),
            rest: !!parameter?.dotDotDotToken,
        };
    });
}

function methodFromType(
    scope: MemberScope,
    type: ts.Type,
    location: ts.Node,
): Pick<MethodEntry, 'parameters' | 'returnType' | 'async'> | null {
    const [signature] = type.getCallSignatures();

    if (!signature) {
        return null;
    }

    return {
        parameters: parametersOfSignature(scope, signature, location),
        returnType: typeText(scope.context, scope.context.checker.getReturnTypeOfSignature(signature)),
        async: false,
    };
}

function extractMethods(scope: MemberScope, options: ts.ObjectLiteralExpression): MethodEntry[] {
    const value = optionValue(scope.context, options, 'methods');

    if (!value) {
        return [];
    }

    if (!ts.isObjectLiteralExpression(value)) {
        report(scope, value, 'The methods option is not an object literal');
        return [];
    }

    const { checker } = scope.context;
    const methods: MethodEntry[] = [];

    for (const property of value.properties) {
        if (ts.isSpreadAssignment(property)) {
            for (const member of spreadMembers(scope, property, 'methods')) {
                const shape = member.type ? methodFromType(scope, member.type, property) : null;

                methods.push({
                    name: member.name,
                    parameters: shape?.parameters ?? [],
                    returnType: shape?.returnType ?? 'unknown',
                    async: false,
                    typeSource: shape ? spreadTypeSource(scope, member) : 'unknown',
                    ...metaOf(scope, property, member.name),
                });
            }
            continue;
        }

        const name = propertyName(property.name);

        if (!name) {
            report(scope, property, 'Cannot resolve the method name');
            continue;
        }

        const doc = docOf(scope, property);
        let definition: ts.Node = property;

        if (ts.isPropertyAssignment(property)) {
            definition = resolveExpression(scope.context, property.initializer);
        } else if (ts.isShorthandPropertyAssignment(property)) {
            definition = resolveExpression(scope.context, property.name);
        }

        let shape: Pick<MethodEntry, 'parameters' | 'returnType' | 'async'> | null = null;

        if (isFunctionNode(definition)) {
            const returnType = functionReturnType(scope, definition);

            shape = {
                parameters: parametersOfDeclaration(scope, definition),
                returnType: returnType ? typeText(scope.context, returnType) : 'unknown',
                async: !!ts.getModifiers(definition)?.some(({ kind }) => kind === ts.SyntaxKind.AsyncKeyword),
            };
        } else if (ts.isExpression(definition)) {
            shape = methodFromType(scope, checker.getTypeAtLocation(definition), definition);
        }

        methods.push({
            name,
            parameters: shape?.parameters ?? [],
            returnType: shape?.returnType ?? 'unknown',
            async: shape?.async ?? false,
            typeSource: shape ? typeSourceOf(scope, doc) : 'unknown',
            ...metaOf(scope, property, name, doc),
        });
    }

    return methods;
}

function extractInject(scope: MemberScope, options: ts.ObjectLiteralExpression): NamedEntry[] {
    const value = optionValue(scope.context, options, 'inject');

    if (!value) {
        return [];
    }

    if (ts.isArrayLiteralExpression(value)) {
        return value.elements
            .filter(ts.isStringLiteralLike)
            .map((element) => ({ name: element.text, ...metaOf(scope, element, element.text) }));
    }

    if (ts.isObjectLiteralExpression(value)) {
        return value.properties.flatMap((property) => {
            const name = propertyName(property.name);

            return name ? [{ name, ...metaOf(scope, property, name) }] : [];
        });
    }

    report(scope, value, 'The inject option is not an object or array literal');

    return [];
}

function mixinName(scope: MemberScope, element: ts.Expression): string {
    const expression = skipOuterExpressions(element);

    if (ts.isStringLiteralLike(expression)) {
        return expression.text;
    }

    if (
        ts.isCallExpression(expression) &&
        /(?:^|\.)getByName$/.test(expression.expression.getText()) &&
        expression.arguments[0] &&
        ts.isStringLiteralLike(expression.arguments[0])
    ) {
        return expression.arguments[0].text;
    }

    return normalizeSource(expression.getText());
}

function extractMixins(scope: MemberScope, options: ts.ObjectLiteralExpression): NamedEntry[] {
    const value = optionValue(scope.context, options, 'mixins');

    if (!value) {
        return [];
    }

    if (!ts.isArrayLiteralExpression(value)) {
        report(scope, value, 'The mixins option is not an array literal');
        return [];
    }

    return value.elements.map((element) => {
        const name = mixinName(scope, element);

        return { name, ...metaOf(scope, element, name) };
    });
}

export function extractMembers(scope: MemberScope, options: ts.ObjectLiteralExpression): ComponentMembers {
    return {
        props: extractProps(scope, options),
        emits: extractEmits(scope, options),
        data: extractData(scope, options),
        computed: extractComputed(scope, options),
        methods: extractMethods(scope, options),
        inject: extractInject(scope, options),
        mixins: extractMixins(scope, options),
    };
}
