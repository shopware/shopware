import type {
    MethodDeclaration,
    ObjectLiteralElementLike,
    ParameterDeclaration,
    PropertyAssignment,
    ShorthandPropertyAssignment,
} from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { quoteJsString } from '../string-literals';

const RESERVED_IDENTIFIERS = new Set([
    'await',
    'break',
    'case',
    'catch',
    'class',
    'const',
    'continue',
    'debugger',
    'default',
    'delete',
    'do',
    'else',
    'enum',
    'export',
    'extends',
    'false',
    'finally',
    'for',
    'function',
    'if',
    'implements',
    'import',
    'in',
    'instanceof',
    'interface',
    'let',
    'new',
    'null',
    'package',
    'private',
    'protected',
    'public',
    'return',
    'static',
    'super',
    'switch',
    'this',
    'throw',
    'true',
    'try',
    'typeof',
    'var',
    'void',
    'while',
    'with',
    'yield',
]);

export function isDefined<T>(value: T | undefined): value is T {
    return value !== undefined;
}

export function isSafeIdentifier(name: string): boolean {
    return /^[$A-Z_a-z][$\w]*$/u.test(name) && !RESERVED_IDENTIFIERS.has(name);
}

export function sanitizeTodoCommentText(value: string): string {
    return value
        .replace(/\r\n?|\n/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function buildPropertyAccess(target: string, name: string): string {
    return isSafeIdentifier(name) ? `${target}.${name}` : `${target}[${quoteJsString(name)}]`;
}

export interface WatchPath {
    /** The member the key starts at — `entity` for `'entity.name'`. */
    root: string;
    /** The property names below the root — `['name']` for `'entity.name'`. */
    propertyPath: string[];
}

/**
 * Reads an Options API watch key. Vue applies a key containing a dot with
 * `createPathGetter`, which splits on '.' and walks the segments, so
 * `'entity.name'` always watches the `name` property of the `entity` member and
 * never a member of that literal name. A key without a dot is the member name
 * itself and is returned unchanged, including quoted forms that are not
 * identifiers.
 *
 * Returns null for a path with a segment that cannot be written as a property
 * access — brackets, spaces, reserved words — so those keep the manual fallback.
 */
export function parseWatchPath(name: string): WatchPath | null {
    if (!name.includes('.')) {
        return { root: name, propertyPath: [] };
    }

    const segments = name.split('.');

    return segments.every(isSafeIdentifier) ? { root: segments[0], propertyPath: segments.slice(1) } : null;
}

/** The member a watch key starts at — `entity` for `'entity.name'`. */
export function getWatchRootName(name: string): string {
    return parseWatchPath(name)?.root ?? name;
}

export function serializeMethodLikeFunction(method: MethodDeclaration): string {
    const asyncPrefix = method.isAsync() ? 'async ' : '';
    const paramsText = method
        .getParameters()
        .map((param) => param.getText())
        .join(', ');
    const bodyText = method.getBodyText() ?? '';

    return `${asyncPrefix}function(${paramsText}) {${bodyText ? `\n${bodyText}\n` : ''}}`;
}

/**
 * A parameter that maps 1:1 to a Composition API arrow parameter. Default
 * values, rest syntax, and destructuring patterns are dropped by `getName()`,
 * so they must be reported as unsupported instead of silently rewritten.
 */
export function isSimpleParameter(param: ParameterDeclaration): boolean {
    return Node.isIdentifier(param.getNameNode()) && !param.hasInitializer() && !param.isRestParameter();
}

/**
 * The option name a root property declares, however it is written. A quoted or
 * bracketed key names the same option as the bare one, and `getProperty(name)`
 * does not find those — so every lookup that decides whether an option is
 * present has to go through this, or the option vanishes from the output with no
 * report and `--delete-originals` removes the source.
 *
 * Returns undefined for a spread and for a computed key that is not a string
 * literal: neither names a known option statically.
 */
export function readOptionName(prop: ObjectLiteralElementLike): string | undefined {
    if (prop.isKind(SyntaxKind.SpreadAssignment)) {
        return undefined;
    }

    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    if (Node.isComputedPropertyName(nameNode)) {
        const expression = nameNode.getExpression();

        return expression.isKind(SyntaxKind.StringLiteral) ? expression.getLiteralValue() : undefined;
    }

    return nameNode.getText();
}

export function getPropertyName(prop: PropertyAssignment | MethodDeclaration | ShorthandPropertyAssignment): string {
    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    return prop.getName();
}
