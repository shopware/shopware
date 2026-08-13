import type { BindingName, CallExpression, PropertyAccessExpression, SourceFile } from 'ts-morph';
import { Node, Project, ScriptKind, SyntaxKind, VariableDeclarationKind } from 'ts-morph';
import { UNKNOWN_COMPONENT_NAME } from '../types';
import type { CodeSnippet, ComponentRegistration, RewriteSnippetKind } from './types';

/**
 * Parses snippets that are not complete JavaScript programs. The wrapper gives
 * ts-morph valid syntax while snippetStart/snippetEnd keep offsets translatable
 * back to the original method body or expression text.
 *
 * `paramsText` is the parameter list of the member the body belongs to. It is
 * written into the wrapper — and therefore outside the snippet range — so the
 * parameters exist as bindings for scope analysis without becoming part of the
 * code that is scanned or rewritten.
 */
export function createWrappedSnippetSource(
    text: string,
    kind: RewriteSnippetKind,
    paramsText = '',
): { sourceFile: SourceFile; snippetStart: number; snippetEnd: number } {
    const prefix = kind === 'body' ? `function __rewrite__(${paramsText}) {\n` : 'const __rewrite__ = (';
    const suffix = kind === 'body' ? '\n}' : ');';

    return {
        sourceFile: parseSource(`${prefix}${text}${suffix}`),
        snippetStart: prefix.length,
        snippetEnd: prefix.length + text.length,
    };
}

export interface LocalBindingScope {
    name: string;
    /** Source range of the scope node the binding is visible in. */
    start: number;
    end: number;
}

/**
 * Every parameter, variable, function, and class binding the parsed snippet
 * declares, paired with the source range it is visible in. A `this.<name>`
 * rewrite that produces a bare identifier is only safe where no binding of that
 * name covers the access — otherwise the generated code would read the local
 * one.
 *
 * Scopes are modelled exactly rather than approximated by "anywhere in the
 * snippet": a same-named binding in a sibling function does not shadow anything
 * and must not cost the member its migration.
 */
export function collectLocalBindingScopes(rootNode: Node): LocalBindingScope[] {
    const scopes: LocalBindingScope[] = [];
    const add = (nameNode: BindingName, scope: Node | undefined): void => {
        if (!scope) {
            return;
        }

        const names = new Set<string>();
        addBindingNames(nameNode, names);
        names.forEach((name) => scopes.push({ name, start: scope.getStart(), end: scope.getEnd() }));
    };

    for (const node of rootNode.getDescendants()) {
        if (Node.isParameterDeclaration(node)) {
            add(node.getNameNode(), node.getParent());
        } else if (Node.isVariableDeclaration(node)) {
            // `var` is function-scoped and hoists out of any block it sits in.
            // The kind is read off the declaration list, not off a variable
            // statement: a `for (var i = 0; …)` initializer has a list but no
            // statement, and a catch clause variable has neither.
            const isFunctionScoped =
                node.getParent()?.asKind(SyntaxKind.VariableDeclarationList)?.getDeclarationKind() ===
                VariableDeclarationKind.Var;

            add(node.getNameNode(), findEnclosingScope(node, rootNode, isFunctionScoped));
        } else if (Node.isFunctionDeclaration(node) || Node.isClassDeclaration(node)) {
            const nameNode = node.getNameNode();

            if (nameNode) {
                add(nameNode, findEnclosingScope(node, rootNode, false));
            }
        }
    }

    return scopes;
}

/** true when a binding of `name` is visible at the given source range. */
export function isCoveredByBindingScope(scopes: LocalBindingScope[], name: string, start: number, end: number): boolean {
    return scopes.some((scope) => scope.name === name && scope.start <= start && scope.end >= end);
}

function findEnclosingScope(node: Node, rootNode: Node, isFunctionScoped: boolean): Node | undefined {
    // `rootNode` bounds the walk: a caller analysing one option object must not
    // pick up a scope that reaches outside it.
    return node.getFirstAncestor(
        (ancestor) =>
            ancestor === rootNode || (isFunctionScoped ? isFunctionScopeBoundary(ancestor) : isBlockScopeBoundary(ancestor)),
    );
}

function isFunctionScopeBoundary(node: Node): boolean {
    return isFunctionLike(node) || Node.isSourceFile(node);
}

function isBlockScopeBoundary(node: Node): boolean {
    return (
        Node.isBlock(node) ||
        Node.isSourceFile(node) ||
        Node.isCaseBlock(node) ||
        Node.isForStatement(node) ||
        Node.isForInStatement(node) ||
        Node.isForOfStatement(node) ||
        Node.isCatchClause(node)
    );
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
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(
        snippet.text,
        snippet.kind,
        snippet.paramsText,
    );

    return (
        sourceFile
            // Example: `this.product.name` contains `this.product` and `this.product.name` property accesses.
            .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
            .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
    );
}

export function getSnippetCallExpressions(snippet: CodeSnippet): CallExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(
        snippet.text,
        snippet.kind,
        snippet.paramsText,
    );

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

/**
 * Every value binding the generated block inherits from the module: the imports
 * and the locals declared before the registration, which `extractModuleLevelCode`
 * copies in front of the setup body.
 *
 * An options entry whose value is one of these names reads the module binding,
 * not the component instance, so it survives the move into setup unchanged.
 */
export function collectModuleBindingNames(sourceFile: SourceFile, registration: ComponentRegistration): Set<string> {
    const registerPos = registration.call.getStart();
    const names = collectModuleLocalNames(sourceFile, registration);

    for (const importDeclaration of sourceFile.getImportDeclarations()) {
        if (importDeclaration.getStart() >= registerPos) {
            continue;
        }

        const defaultImport = importDeclaration.getDefaultImport()?.getText();

        // The Twig import is dropped on the way out — the template lives in the
        // SFC now — so its name is not available to the generated block.
        if (defaultImport && defaultImport !== 'template') {
            names.add(defaultImport);
        }

        const namespaceImport = importDeclaration.getNamespaceImport()?.getText();
        if (namespaceImport) {
            names.add(namespaceImport);
        }

        importDeclaration.getNamedImports().forEach((namedImport) => {
            names.add(namedImport.getAliasNode()?.getText() ?? namedImport.getName());
        });
    }

    return names;
}

/**
 * Objects that exist before any module code runs, so a property path rooted in
 * one of them can be written wherever the alias was readable — including inside
 * a hoisted compiler macro.
 */
const GLOBAL_ALIAS_ROOTS = new Set([
    'Shopware',
    'window',
    'document',
]);

/**
 * Module-level `const` aliases of a global property path, mapped to that path —
 * `const { Criteria } = Shopware.Data;` yields `Criteria → Shopware.Data.Criteria`.
 *
 * `defineProps`/`defineEmits` are hoisted above every module local, so a props
 * definition naming one cannot be emitted into the macro. Most of those names
 * are only a shorthand for a global, though, and writing the global path back
 * out is the same value read at the same time. Declarations are walked in source
 * order, so an alias of an alias resolves too.
 *
 * `let`/`var` are excluded because they can be reassigned between the
 * declaration and the read, and array destructuring is excluded because reading
 * by index is not a property path.
 */
export function collectGlobalAliasPaths(sourceFile: SourceFile, registration: ComponentRegistration): Map<string, string> {
    const registerPos = registration.call.getStart();
    const aliases = new Map<string, string>();

    for (const stmt of sourceFile.getStatements()) {
        if (stmt.getStart() >= registerPos) break;

        if (!stmt.isKind(SyntaxKind.VariableStatement)) {
            continue;
        }

        const declarationList = stmt.asKindOrThrow(SyntaxKind.VariableStatement).getDeclarationList();
        if (declarationList.getDeclarationKind() !== VariableDeclarationKind.Const) {
            continue;
        }

        for (const declaration of declarationList.getDeclarations()) {
            const path = resolveGlobalPath(declaration.getInitializer(), aliases);

            if (path) {
                addGlobalAliasBindings(declaration.getNameNode(), path, aliases);
            }
        }
    }

    return aliases;
}

function addGlobalAliasBindings(nameNode: BindingName, path: string, aliases: Map<string, string>): void {
    if (Node.isIdentifier(nameNode)) {
        aliases.set(nameNode.getText(), path);

        return;
    }

    if (!Node.isObjectBindingPattern(nameNode)) {
        return;
    }

    for (const element of nameNode.getElements()) {
        const localName = element.getNameNode();
        const propertyNameNode = element.getPropertyNameNode();

        // A default value makes the binding differ from the property whenever
        // the property is missing, a nested pattern is not a single path, and a
        // string-literal or computed key is not writable as `a.b`.
        if (
            element.getInitializer() ||
            element.getDotDotDotToken() ||
            !Node.isIdentifier(localName) ||
            (propertyNameNode && !Node.isIdentifier(propertyNameNode))
        ) {
            continue;
        }

        aliases.set(localName.getText(), `${path}.${(propertyNameNode ?? localName).getText()}`);
    }
}

function resolveGlobalPath(node: Node | undefined, aliases: Map<string, string>): string | null {
    if (!node) {
        return null;
    }

    if (Node.isIdentifier(node)) {
        const name = node.getText();

        return GLOBAL_ALIAS_ROOTS.has(name) ? name : (aliases.get(name) ?? null);
    }

    // `a?.b` cannot be re-emitted as `a.b`, so an optional chain is not a path.
    if (!Node.isPropertyAccessExpression(node) || node.hasQuestionDotToken()) {
        return null;
    }

    const base = resolveGlobalPath(node.getExpression(), aliases);

    return base === null ? null : `${base}.${node.getName()}`;
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
