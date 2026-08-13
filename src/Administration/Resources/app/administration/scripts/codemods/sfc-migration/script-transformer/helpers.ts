import type { MethodDeclaration, ParameterDeclaration, PropertyAssignment, ShorthandPropertyAssignment } from 'ts-morph';
import { Node } from 'ts-morph';
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

/**
 * Rewrites the `function` expressions inside a property-assignment method value
 * into arrows: `debounce(function onSave() { … })` becomes
 * `debounce(() => { … })`. The wrapper call itself is preserved — flattening it
 * would drop the debounce — and `this` inside is rewritten separately.
 *
 * Applied at extraction, so the shape checks and the emitter read the same text.
 * That matters for a *named* function expression: its name is a binding inside
 * itself, so `debounce(function onSave() { this.onSave(); })` looks like a
 * shadowed rewrite target until the name is gone, which it is here.
 */
export function normalizeMethodValueFunctions(rawText: string): string {
    return rawText.replace(/\bfunction\s+\w*\s*\(([^)]*)\)\s*\{/g, '($1) => {');
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

export function getPropertyName(prop: PropertyAssignment | MethodDeclaration | ShorthandPropertyAssignment): string {
    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    return prop.getName();
}
