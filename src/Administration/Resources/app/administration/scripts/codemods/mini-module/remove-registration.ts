/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import { scanGlobalUses } from './dependencies/global-uses';

const PACKAGE_DOC = /\/\*\*?(?:(?!\*\/)[\s\S])*@sw-package(?:(?!\*\/)[\s\S])*\*\//;

/** The comments of a file with what follows each, from the TypeScript scanner. */
const comments = (code: string) => {
    const scanner = ts.createScanner(ts.ScriptTarget.Latest, false, ts.LanguageVariant.Standard, code);
    const found: { start: number; end: number; text: string; next: number }[] = [];

    for (let token = scanner.scan(); token !== ts.SyntaxKind.EndOfFileToken; token = scanner.scan()) {
        if (token === ts.SyntaxKind.MultiLineCommentTrivia || token === ts.SyntaxKind.SingleLineCommentTrivia) {
            found.forEach((comment) => {
                comment.next = comment.next === -1 ? scanner.getTokenStart() : comment.next;
            });
            found.push({
                start: scanner.getTokenStart(),
                end: scanner.getTokenEnd(),
                text: scanner.getTokenText(),
                next: -1,
            });
        } else if (token !== ts.SyntaxKind.WhitespaceTrivia && token !== ts.SyntaxKind.NewLineTrivia) {
            found.forEach((comment) => {
                comment.next = comment.next === -1 ? scanner.getTokenStart() : comment.next;
            });
        }
    }

    return found.map((comment) => ({ ...comment, next: comment.next === -1 ? code.length : comment.next }));
};

/** The whole lines of a range, with their line break, to cut them out. */
const lineRange = (code: string, start: number, end: number) => {
    const lineStart = code.lastIndexOf('\n', start - 1) + 1;
    const lineBreak = code.indexOf('\n', end);
    return { start: lineStart, end: lineBreak === -1 ? code.length : lineBreak + 1 };
};

const FEATURE_RULE = 'sw-deprecation-rules/private-feature-declarations';

/** Whether the comment that ends on the line above a node marks it as `private-feature-declarations` accepts it. */
const isDeclaredFeature = (code: string, node: ts.Node, source: ts.SourceFile) => {
    const comment = (ts.getLeadingCommentRanges(code, node.getFullStart()) ?? []).at(-1);
    if (!comment || comment.kind !== ts.SyntaxKind.MultiLineCommentTrivia) {
        return false;
    }

    const nodeLine = source.getLineAndCharacterOfPosition(node.getStart(source)).line;
    const text = code.slice(comment.pos, comment.end);
    return (
        source.getLineAndCharacterOfPosition(comment.end).line === nodeLine - 1 &&
        (text.includes('@private') || text.includes('@deprecated tag:'))
    );
};

/** A call `private-feature-declarations` reports without a doc comment: `register` on `Component`, `Module` or `Service()`. */
const isFeatureRegistration = (statement: ts.ExpressionStatement) => {
    const call = statement.expression;
    if (!ts.isCallExpression(call) || !ts.isPropertyAccessExpression(call.expression)) {
        return false;
    }

    const owner = call.expression.expression.getText();
    return (
        call.expression.name.text === 'register' &&
        /^(Shopware\.)?(Component|Module)$|^(Shopware\.)?Service\(\)$/.test(owner.replace(/\s/g, ''))
    );
};

/**
 * The ranges of the code `private-feature-declarations` reports: feature registrations and exports
 * without a `@private` or `@deprecated tag:` doc comment, as the rule sees them.
 */
const reportedFeatureRanges = (code: string) => {
    const source = ts.createSourceFile('file.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const ranges: { start: number; end: number }[] = [];
    const visit = (node: ts.Node) => {
        const isReported =
            (ts.isExpressionStatement(node) && isFeatureRegistration(node)) ||
            ts.isExportAssignment(node) ||
            (ts.isExportDeclaration(node) && !!node.exportClause) ||
            (ts.isStatement(node) &&
                ts.canHaveModifiers(node) &&
                !!ts.getModifiers(node)?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword));
        if (isReported && !isDeclaredFeature(code, node, source)) {
            ranges.push({ start: node.getStart(source), end: node.getEnd() });
        }

        node.forEachChild(visit);
    };
    visit(source);

    return ranges;
};

/**
 * Removes `/* eslint-disable … *\/` comments that nothing but an `eslint-enable` or the end of the file follows
 * any more, with that `eslint-enable`, and the `private-feature-declarations` disables, also `-next-line` ones,
 * that no longer cover a line the rule reports.
 */
export function dropEmptyLintBlocks(code: string): string {
    const found = comments(code);
    const cuts: { start: number; end: number }[] = [];
    const reported = reportedFeatureRanges(code);
    const coversReport = (from: number, to: number) => reported.some(({ start }) => start >= from && start < to);
    const disablesOnlyFeatureRule = (text: string) =>
        new RegExp(`^(\\/\\*|\\/\\/)\\s*eslint-disable(-next-line)?\\s+${FEATURE_RULE}\\s*(--[^*]*)?(\\*\\/)?$`).test(
            text.trim(),
        );

    found.forEach((comment, index) => {
        if (/^(\/\*|\/\/)\s*eslint-disable-next-line\s/.test(comment.text)) {
            const nextLineEnd = code.indexOf('\n', code.indexOf('\n', comment.end) + 1);
            if (
                disablesOnlyFeatureRule(comment.text) &&
                !coversReport(comment.end, nextLineEnd === -1 ? code.length : nextLineEnd)
            ) {
                cuts.push(lineRange(code, comment.start, comment.end));
            }

            return;
        }

        if (!/^\/\*\s*eslint-disable\s/.test(comment.text)) {
            return;
        }

        const enable = found.slice(index + 1).find((later) => /^\/\*\s*eslint-enable\b/.test(later.text));
        const closesRightAfter = enable && enable === found[index + 1] && enable.start === comment.next;
        const isUnused = disablesOnlyFeatureRule(comment.text) && !coversReport(comment.end, enable?.start ?? code.length);
        if (closesRightAfter || (isUnused && enable)) {
            cuts.push(lineRange(code, comment.start, comment.end), lineRange(code, enable.start, enable.end));
        } else if (comment.next === code.length || isUnused) {
            cuts.push(lineRange(code, comment.start, comment.end));
        }
    });

    const cut = cuts
        .sort((a, b) => b.start - a.start)
        .reduce((text, { start, end }) => {
            const blankAround = (start === 0 || text.slice(0, start).endsWith('\n\n')) && text[end] === '\n';
            return `${text.slice(0, start)}${text.slice(blankAround ? end + 1 : end)}`;
        }, code);

    return cut.endsWith('\n\n') ? cut.replace(/\n+$/, '\n') : cut;
}

/** Removes the names of `const { Component, … } = Shopware;` that nothing reads any more, and the statement when none is left. */
export function dropUnusedDestructures(code: string): string {
    const source = ts.createSourceFile('file.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const unused = scanGlobalUses(source).aliases.filter((alias) => alias.references.length === 0);
    const statements = new Set(unused.map((alias) => alias.statement));

    const cuts = [...statements].map((statement) => {
        const pattern = statement.declarationList.declarations[0].name as ts.ObjectBindingPattern;
        const kept = pattern.elements.filter((element) => !unused.some((alias) => alias.element === element));
        return kept.length === 0
            ? { ...lineRange(code, statement.getStart(source), statement.getEnd()), text: '' }
            : {
                  start: pattern.getStart(source),
                  end: pattern.getEnd(),
                  text: `{ ${kept.map((element) => element.getText(source)).join(', ')} }`,
              };
    });

    return cuts
        .sort((a, b) => b.start - a.start)
        .reduce(
            (text, { start, end, text: replacement }) => `${text.slice(0, start)}${replacement}${text.slice(end)}`,
            code,
        );
}

/**
 * Cuts a registration out of its file and tidies what it leaves behind: the file keeps a doc comment with its
 * `@sw-package` when no other one is left, an `eslint-disable` block that became empty goes, and so do the names
 * destructured from the global that nothing reads any more.
 *
 * @example
 * removeRegistration("/**\n * @sw-package checkout\n *\/\nShopware.Component.register('sw-x', () => import('./x'));\n", { start: 0, end: 77 });
 * // → "/**\n * @sw-package checkout\n *\/\n"
 */
export function removeRegistration(code: string, range: { start: number; end: number }): string {
    const removed = code.slice(range.start, range.end);
    const rest = `${code.slice(0, range.start)}${code.slice(range.end)}`;
    const packageDoc = PACKAGE_DOC.exec(removed)?.[0];
    const kept =
        packageDoc && !rest.includes('@sw-package')
            ? `${code.slice(0, range.start)}${packageDoc}\n${code.slice(range.end)}`
            : rest;

    return dropUnusedDestructures(dropEmptyLintBlocks(kept));
}
