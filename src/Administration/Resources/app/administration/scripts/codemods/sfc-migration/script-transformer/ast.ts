import type { BindingName, CallExpression, PropertyAccessExpression, SourceFile } from 'ts-morph';
import { Node, Project, ScriptKind, SyntaxKind } from 'ts-morph';
import { UNKNOWN_COMPONENT_NAME } from '../types';
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
    const prefix = kind === 'body' ? 'function __rewrite__() {\n' : 'const __rewrite__ = (';
    const suffix = kind === 'body' ? '\n}' : ');';

    return {
        sourceFile: parseSource(`${prefix}${text}${suffix}`),
        snippetStart: prefix.length,
        snippetEnd: prefix.length + text.length,
    };
}

export function isNodeInsideSnippet(node: Node, snippetStart: number, snippetEnd: number): boolean {
    return node.getStart() >= snippetStart && node.getEnd() <= snippetEnd;
}

function isFunctionLike(node: Node): boolean {
    return (
        Node.isFunctionDeclaration(node) ||
        Node.isFunctionExpression(node) ||
        Node.isArrowFunction(node) ||
        Node.isMethodDeclaration(node)
    );
}

/**
 * Detects a `return` that belongs to the snippet itself rather than to a nested
 * function. `created()` has no Composition API hook, so its body is emitted as
 * top-level setup code — where a bare `return` is a syntax error and the body
 * has to be wrapped in a function instead.
 */
export function hasTopLevelReturn(bodyText: string): boolean {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(bodyText, 'body');
    // The snippet wrapper is the only function a top-level return is nested in.
    const wrapper = sourceFile.getFunctions()[0];

    return sourceFile
        .getDescendantsOfKind(SyntaxKind.ReturnStatement)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
        .some((node) => node.getFirstAncestor(isFunctionLike) === wrapper);
}

export function getDirectThisPropertyName(node: PropertyAccessExpression): string | null {
    // Example: `this.product` has a `ThisKeyword` expression and `product` as the property name.
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

    return (
        sourceFile
            // Example: `this.product.name` contains `this.product` and `this.product.name` property accesses.
            .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
            .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
    );
}

export function getSnippetCallExpressions(snippet: CodeSnippet): CallExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);

    return (
        sourceFile
            // Example: `this.$emit('save')`
            .getDescendantsOfKind(SyntaxKind.CallExpression)
            .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
    );
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
        // Example: `Shopware.Component.register('sw-card', { ... })`
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

    const componentNameIsLiteral = componentNameArg?.isKind(SyntaxKind.StringLiteral) ?? false;

    return {
        call,
        isExtend,
        // Non-literal names fall back to `unknown-component`; detectBlockers uses
        // componentNameIsLiteral to report this instead of renaming silently.
        componentName: componentNameIsLiteral
            ? componentNameArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
            : UNKNOWN_COMPONENT_NAME,
        componentNameIsLiteral,
        // Example: the second argument in `Shopware.Component.register('sw-card', { props: {} })`.
        optionsObject: optionsArg?.asKind(SyntaxKind.ObjectLiteralExpression),
        parentComponentName:
            isExtend && parentComponentNameArg?.isKind(SyntaxKind.StringLiteral)
                ? parentComponentNameArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
                : null,
    };
}

/**
 * Names declared at module level (before the registration) that become
 * `<script setup>` locals after migration. `defineProps`/`defineEmits` are
 * hoisted above these locals, so a props/emits definition that references one
 * cannot be emitted into a compiler macro. Imports are excluded because they
 * stay hoist-safe.
 */
export function collectModuleLocalNames(sourceFile: SourceFile, registration: ComponentRegistration): Set<string> {
    const registerPos = registration.call.getStart();
    const names = new Set<string>();

    for (const stmt of sourceFile.getStatements()) {
        if (stmt.getStart() >= registerPos) break;

        if (stmt.isKind(SyntaxKind.VariableStatement)) {
            stmt.asKindOrThrow(SyntaxKind.VariableStatement)
                .getDeclarationList()
                .getDeclarations()
                .forEach((declaration) => addBindingNames(declaration.getNameNode(), names));
        } else if (stmt.isKind(SyntaxKind.FunctionDeclaration) || stmt.isKind(SyntaxKind.ClassDeclaration)) {
            const name =
                stmt.asKind(SyntaxKind.FunctionDeclaration)?.getName() ??
                stmt.asKind(SyntaxKind.ClassDeclaration)?.getName();
            if (name) {
                names.add(name);
            }
        }
    }

    return names;
}

/** Collects the bound names of a declaration, including destructuring patterns. */
function addBindingNames(nameNode: BindingName, names: Set<string>): void {
    if (Node.isIdentifier(nameNode)) {
        names.add(nameNode.getText());
        return;
    }

    nameNode.getElements().forEach((element) => {
        if (Node.isBindingElement(element)) {
            addBindingNames(element.getNameNode(), names);
        }
    });
}

/**
 * Names the module-level code already imports from `vue` under their own name.
 *
 * Those import statements are copied into the generated block verbatim, so a
 * generated specifier for the same name would declare an identical binding a
 * second time — which the build rejects as a parse error. An aliased import
 * (`import { ref as vueRef }`) leaves the name free and is not collected, and
 * neither is a same-name import from another module: skipping the generated
 * import there would silently bind the generated code to something else.
 */
export function collectModuleVueImportNames(sourceFile: SourceFile, registration: ComponentRegistration): Set<string> {
    const registerPos = registration.call.getStart();
    const names = new Set<string>();

    for (const importDeclaration of sourceFile.getImportDeclarations()) {
        // Imports after the registration are not copied into the generated block.
        if (importDeclaration.getStart() >= registerPos || importDeclaration.getModuleSpecifierValue() !== 'vue') {
            continue;
        }

        importDeclaration.getNamedImports().forEach((namedImport) => {
            if (!namedImport.getAliasNode()) {
                names.add(namedImport.getName());
            }
        });
    }

    return names;
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
