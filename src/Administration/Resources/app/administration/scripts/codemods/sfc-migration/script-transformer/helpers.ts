import type { MethodDeclaration, PropertyAssignment, ShorthandPropertyAssignment } from 'ts-morph';
import { Node } from 'ts-morph';
import { quoteJsString } from '../string-literals';
import { indentIdentifierTemplate, isIdentifierTemplate } from './identifier-template';
import type { ScriptSnippet } from './identifier-template';

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

export function serializeMethodLikeFunction(method: MethodDeclaration): string {
    const asyncPrefix = method.isAsync() ? 'async ' : '';
    const paramsText = method
        .getParameters()
        .map((param) => param.getText())
        .join(', ');
    const bodyText = method.getBodyText() ?? '';

    return `${asyncPrefix}function(${paramsText}) {${bodyText ? `\n${bodyText}\n` : ''}}`;
}

export function getPropertyName(prop: PropertyAssignment | MethodDeclaration | ShorthandPropertyAssignment): string {
    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    return prop.getName();
}

export function indentBlock(block: ScriptSnippet, spaces: number): ScriptSnippet {
    if (isIdentifierTemplate(block)) {
        return indentIdentifierTemplate(block, spaces);
    }

    const pad = ' '.repeat(spaces);

    return block
        .split('\n')
        .map((line) => (line.trim() === '' ? '' : pad + line))
        .join('\n');
}
