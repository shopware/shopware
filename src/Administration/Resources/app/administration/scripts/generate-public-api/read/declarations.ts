/**
 * @sw-package framework
 *
 * Finds what a name in a script stands for, across files: a `const`, a function or a class of an enclosing
 * scope or of the file, or what an import brings in. Only the syntax is read, so a name that is bound at
 * runtime, such as a parameter or a global, stays the name it is.
 */
import ts from 'typescript';
import type { SourceTree } from '../source/source-tree';
import { findModule, parseScript } from './modules';
import { withoutTypeSyntax } from './script-nodes';

/**
 * @private
 *
 * A node with the file it stands in, which the readers need for its text and its line.
 */
export interface Located<Node extends ts.Node = ts.Node> {
    node: Node;
    source: ts.SourceFile;
}

type Scope = ts.SourceFile | ts.Block | ts.ModuleBlock;

function isScope(node: ts.Node): node is Scope {
    return ts.isSourceFile(node) || ts.isBlock(node) || ts.isModuleBlock(node);
}

function hasModifier(node: ts.Node, kind: ts.SyntaxKind): boolean {
    return ts.canHaveModifiers(node) && (ts.getModifiers(node) ?? []).some((modifier) => modifier.kind === kind);
}

function variablesOf(statement: ts.Statement): ts.VariableDeclaration[] {
    return ts.isVariableStatement(statement) ? [...statement.declarationList.declarations] : [];
}

function isNamed(node: { name?: ts.Node }, name: string): boolean {
    return node.name !== undefined && ts.isIdentifier(node.name) && node.name.text === name;
}

/** The variable, function or class that a statement of the scope declares under the name. */
function declaredIn(scope: Scope, name: string): ts.Node | undefined {
    return scope.statements
        .flatMap((statement): ts.Node[] =>
            ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement) ? [statement] : variablesOf(statement),
        )
        .find((declaration) => isNamed(declaration as { name?: ts.Node }, name));
}

/** A parameter hides every declaration of its name further out, and what it holds is decided by the caller. */
function isParameterOf(node: ts.Node, name: string): boolean {
    return ts.isFunctionLike(node) && node.parameters.some((parameter) => isNamed(parameter, name));
}

/** `name = value` somewhere in the file, for a `let` that is declared without a value and filled later. */
function assignedValue(name: string, source: ts.SourceFile): ts.Expression | undefined {
    const find = (node: ts.Node): ts.Expression | undefined => {
        const isAssignment =
            ts.isBinaryExpression(node) &&
            node.operatorToken.kind === ts.SyntaxKind.EqualsToken &&
            ts.isIdentifier(node.left) &&
            node.left.text === name;

        return isAssignment ? node.right : ts.forEachChild(node, find);
    };

    return find(source);
}

interface ImportedName {
    specifier: string;
    exported: string;
}

function importOf(name: string, source: ts.SourceFile): ImportedName | undefined {
    return source.statements.filter(ts.isImportDeclaration).flatMap((statement): ImportedName[] => {
        const { importClause, moduleSpecifier } = statement;

        if (importClause === undefined || !ts.isStringLiteral(moduleSpecifier)) {
            return [];
        }

        if (importClause.name?.text === name) {
            return [{ specifier: moduleSpecifier.text, exported: 'default' }];
        }

        const bindings = importClause.namedBindings;

        return bindings !== undefined && ts.isNamedImports(bindings)
            ? bindings.elements
                  .filter((element) => element.name.text === name)
                  .map((element) => ({
                      specifier: moduleSpecifier.text,
                      exported: (element.propertyName ?? element.name).text,
                  }))
            : [];
    })[0];
}

/** A declaration with `default` is the default export, and one without is exported under its name. */
function isExportedAs(declaration: ts.FunctionDeclaration | ts.ClassDeclaration, name: string): boolean {
    const isDefault = hasModifier(declaration, ts.SyntaxKind.DefaultKeyword);

    return name === 'default' ? isDefault : !isDefault && isNamed(declaration, name);
}

/** The local name behind `name` in `export { local as name }`. */
function localNameOf({ elements }: ts.NamedExports, name: string): ts.ModuleExportName | undefined {
    const element = elements.find((candidate) => candidate.name.text === name);

    return element && (element.propertyName ?? element.name);
}

// Deep enough for every chain of re-exports in the sources, and an end for one that loops.
const MAX_HOPS = 20;

/**
 * @private
 *
 * The declarations of one source tree. It parses every file once, however many names lead to it.
 */
export class Declarations {
    private readonly parsed = new Map<string, ts.SourceFile>();

    public constructor(private readonly tree: SourceTree) {}

    public parse(file: string): ts.SourceFile {
        let source = this.parsed.get(file);

        if (source === undefined) {
            source = parseScript(file, this.tree);
            this.parsed.set(file, source);
        }

        return source;
    }

    /**
     * What the expression stands for: the expression without its type syntax, or for a name the class, the
     * function or the value of the variable it names. A name that the tree does not declare is returned as it is.
     */
    public valueOf(expression: ts.Expression, source: ts.SourceFile, hops = 0): Located {
        const value = withoutTypeSyntax(expression);

        if (!ts.isIdentifier(value) || hops > MAX_HOPS) {
            return { node: value, source };
        }

        const declared = this.declarationOf(value, source, hops);

        if (declared === undefined) {
            return { node: value, source };
        }

        return ts.isVariableDeclaration(declared.node)
            ? this.valueOfVariable({ node: declared.node, source: declared.source }, hops)
            : declared;
    }

    /** What the file exports under the name, `default` for its default export. */
    public exported(file: string, name: string, hops = 0): Located | undefined {
        const source = this.parse(file);

        if (hops > MAX_HOPS) {
            return undefined;
        }

        for (const statement of source.statements) {
            const found = this.exportedBy(statement, name, source, hops);

            if (found !== undefined) {
                return found;
            }
        }

        return undefined;
    }

    private declarationOf(name: ts.Identifier, source: ts.SourceFile, hops: number): Located | undefined {
        for (let node: ts.Node = name; node.parent !== undefined; node = node.parent) {
            const { parent } = node;

            if (isParameterOf(parent, name.text)) {
                return undefined;
            }

            const declared = isScope(parent) ? declaredIn(parent, name.text) : undefined;

            if (declared !== undefined) {
                return { node: declared, source };
            }
        }

        const imported = importOf(name.text, source);
        const file = imported && findModule(imported.specifier, source.fileName, this.tree);

        return imported !== undefined && file !== undefined ? this.exported(file, imported.exported, hops + 1) : undefined;
    }

    /** The value a variable is declared with or filled with later, the variable itself when the file gives it none. */
    private valueOfVariable(variable: Located<ts.VariableDeclaration>, hops: number): Located {
        const { node, source } = variable;
        const assigned =
            node.initializer ?? (ts.isIdentifier(node.name) ? assignedValue(node.name.text, source) : undefined);

        return assigned === undefined ? variable : this.valueOf(assigned, source, hops + 1);
    }

    private exportedBy(statement: ts.Statement, name: string, source: ts.SourceFile, hops: number): Located | undefined {
        if (ts.isExportAssignment(statement)) {
            return name === 'default' && !statement.isExportEquals
                ? this.valueOf(statement.expression, source, hops + 1)
                : undefined;
        }

        if (ts.isExportDeclaration(statement)) {
            return this.reExportedBy(statement, name, source, hops);
        }

        return hasModifier(statement, ts.SyntaxKind.ExportKeyword)
            ? this.declaredBy(statement, name, source, hops)
            : undefined;
    }

    /** `export class A {}`, `export default function () {}` and `export const a = ...`. */
    private declaredBy(statement: ts.Statement, name: string, source: ts.SourceFile, hops: number): Located | undefined {
        if (ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement)) {
            return isExportedAs(statement, name) ? { node: statement, source } : undefined;
        }

        const variable = variablesOf(statement).find((declaration) => isNamed(declaration, name));

        return variable && this.valueOfVariable({ node: variable, source }, hops);
    }

    private exportedFrom(file: string | undefined, name: string, hops: number): Located | undefined {
        return file === undefined ? undefined : this.exported(file, name, hops + 1);
    }

    /** `export { a as b }`, `export { a } from './a'` and `export * from './a'`. */
    private reExportedBy(
        statement: ts.ExportDeclaration,
        name: string,
        source: ts.SourceFile,
        hops: number,
    ): Located | undefined {
        const { exportClause, moduleSpecifier } = statement;
        const from =
            moduleSpecifier !== undefined && ts.isStringLiteral(moduleSpecifier)
                ? findModule(moduleSpecifier.text, source.fileName, this.tree)
                : undefined;

        if (exportClause === undefined) {
            return name === 'default' ? undefined : this.exportedFrom(from, name, hops);
        }

        const local = ts.isNamedExports(exportClause) ? localNameOf(exportClause, name) : undefined;

        if (local === undefined || moduleSpecifier !== undefined) {
            return local && this.exportedFrom(from, local.text, hops);
        }

        return ts.isIdentifier(local) ? this.valueOf(local, source, hops + 1) : undefined;
    }
}
