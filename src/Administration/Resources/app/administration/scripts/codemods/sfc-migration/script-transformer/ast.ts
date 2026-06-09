import type { CallExpression, PropertyAccessExpression, SourceFile } from 'ts-morph';
import { Node, Project, ScriptKind, SyntaxKind } from 'ts-morph';
import type { CodeSnippet, ComponentRegistration, RewriteSnippetKind } from './types';

/**
 * Parses snippets that are not complete JavaScript programs. The wrapper gives
 * ts-morph valid syntax while snippetStart/snippetEnd keep offsets translatable
 * back to the original method body or expression text.
 */
export function createWrappedSnippetSource(
    text: string,
    kind: RewriteSnippetKind,
): { sourceFile: SourceFile; snippetStart: number; snippetEnd: number } {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });
    const prefix = kind === 'body' ? 'function __rewrite__() {\n' : 'const __rewrite__ = (';
    const suffix = kind === 'body' ? '\n}' : ');';

    return {
        sourceFile: project.createSourceFile('snippet.js', `${prefix}${text}${suffix}`, { scriptKind: ScriptKind.JS }),
        snippetStart: prefix.length,
        snippetEnd: prefix.length + text.length,
    };
}

export function isNodeInsideSnippet(node: Node, snippetStart: number, snippetEnd: number): boolean {
    return node.getStart() >= snippetStart && node.getEnd() <= snippetEnd;
}

export function getDirectThisPropertyName(node: PropertyAccessExpression): string | null {
    return node.getExpression().isKind(SyntaxKind.ThisKeyword) ? node.getName() : null;
}

export function getThisRefName(node: PropertyAccessExpression): string | null {
    const expression = node.getExpression();

    if (!Node.isPropertyAccessExpression(expression)) {
        return null;
    }

    return getDirectThisPropertyName(expression) === '$refs' ? node.getName() : null;
}

export function getSnippetPropertyAccesses(snippet: CodeSnippet): PropertyAccessExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);

    return sourceFile
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd));
}

export function getSnippetCallExpressions(snippet: CodeSnippet): CallExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);

    return sourceFile
        .getDescendantsOfKind(SyntaxKind.CallExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd));
}

export function parseSource(jsContent: string): SourceFile {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });

    return project.createSourceFile('component.js', jsContent, { scriptKind: ScriptKind.JS });
}

export function findComponentRegistration(sourceFile: SourceFile): ComponentRegistration | undefined {
    const call = sourceFile
        .getDescendantsOfKind(SyntaxKind.CallExpression)
        .find((candidate) => /Shopware\.Component\.(register|extend)/.test(candidate.getExpression().getText()));

    if (!call) {
        return undefined;
    }

    const expressionText = call.getExpression().getText();
    const isExtend = /Shopware\.Component\.extend/.test(expressionText);
    const args = call.getArguments();
    const componentNameArg = args[0];
    const parentComponentNameArg = args[1];
    const optionsArg = args[isExtend ? 2 : 1];

    return {
        call,
        isExtend,
        // TODO: Silent ignore: non-literal component names are rewritten to
        // `unknown-component`; the migration should report this edge case
        // instead of silently changing the registered component name.
        componentName: componentNameArg?.isKind(SyntaxKind.StringLiteral)
            ? componentNameArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
            : 'unknown-component',
        optionsObject: optionsArg?.asKind(SyntaxKind.ObjectLiteralExpression),
        parentComponentName:
            isExtend && parentComponentNameArg?.isKind(SyntaxKind.StringLiteral)
                ? parentComponentNameArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
                : null,
    };
}

export function extractModuleLevelCode(sourceFile: SourceFile, registration: ComponentRegistration): string {
    const registerPos = registration.call.getStart();
    const lines: string[] = [];

    for (const stmt of sourceFile.getStatements()) {
        if (stmt.getStart() >= registerPos) break;

        // Keep side-effect imports and constants before registration, but drop
        // the old Twig import because the template now lives inside the SFC.
        if (stmt.isKind(SyntaxKind.ImportDeclaration)) {
            const imp = stmt.asKindOrThrow(SyntaxKind.ImportDeclaration);
            const defaultImport = imp.getDefaultImport()?.getText();
            if (defaultImport === 'template') continue;
        }

        lines.push(stmt.getText());
    }

    return lines.join('\n');
}
