/**
 * @sw-package framework
 */

import * as ts from 'typescript';
import type { Deprecation, Visibility } from '../types';

export interface DocInfo {
    visibility: Visibility;
    deprecation: Deprecation | null;
    packageName: string | null;
    hasTypeTags: boolean;
}

const VISIBILITY_TAGS: Array<[RegExp, Visibility]> = [
    [
        /(?:^|[\s*])@private\b/,
        'private',
    ],
    [
        /(?:^|[\s*])@internal\b/,
        'internal',
    ],
    [
        /(?:^|[\s*])@experimental\b/,
        'experimental',
    ],
];

export function leadingComments(node: ts.Node): string[] {
    const text = node.getSourceFile().getFullText();

    return (ts.getLeadingCommentRanges(text, node.getFullStart()) ?? []).map(({ pos, end }) => text.slice(pos, end));
}

function stripCommentSyntax(comment: string): string {
    return comment
        .replace(/^\/\*\*?|\*\/$|^\{#-?|-?#\}$/g, '')
        .split('\n')
        .map((line) => line.replace(/^\s*(?:\*(?!\/)|\/\/)?\s?/, ''))
        .join('\n');
}

export function parseDeprecation(comment: string): Deprecation | null {
    const text = stripCommentSyntax(comment);
    const match = /(?:^|\s)@deprecated\b([\s\S]*?)(?=\n\s*@\w|$)/.exec(text);

    if (!match) {
        return null;
    }

    const body = match[1].replace(/\s+/g, ' ').trim();
    const tagged = /^tag:(\S+)\s*(?:-\s*)?([\s\S]*)$/.exec(body);

    return tagged ? { version: tagged[1], message: tagged[2].trim() } : { version: null, message: body };
}

export function parseDocInfo(comments: string[]): DocInfo {
    const text = comments.join('\n');
    const visibility = VISIBILITY_TAGS.find(([pattern]) => pattern.test(text))?.[1] ?? 'public';
    const deprecation = comments.map(parseDeprecation).find((entry) => entry !== null) ?? null;
    const packageName = /@sw-package\s+(\S+)/.exec(text)?.[1] ?? null;
    const hasTypeTags = /@(?:type|param|returns?)\s*\{/.test(text);

    return { visibility, deprecation, packageName, hasTypeTags };
}

/** The `deprecated` option of components and props: a version string or `{ version, comment }`. */
export function parseDeprecatedOption(node: ts.Expression | undefined): Deprecation | null {
    if (!node) {
        return null;
    }

    if (ts.isStringLiteralLike(node)) {
        return { version: node.text, message: '' };
    }

    if (!ts.isObjectLiteralExpression(node)) {
        return node.kind === ts.SyntaxKind.TrueKeyword ? { version: null, message: '' } : null;
    }

    const read = (key: string): string | null => {
        const property = node.properties.find(
            (candidate): candidate is ts.PropertyAssignment =>
                ts.isPropertyAssignment(candidate) && ts.isIdentifier(candidate.name) && candidate.name.text === key,
        );

        return property && ts.isStringLiteralLike(property.initializer) ? property.initializer.text : null;
    };

    return { version: read('version'), message: read('comment') ?? '' };
}
