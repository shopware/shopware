/**
 * @sw-package framework
 *
 * Finds what a name in a script stands for, across files: a `const`, a function or a class of an enclosing
 * scope or of the file, what an import brings in, or a member of one of these, as in `factory.register` and,
 * for a namespace import, `utils.format`. Only the syntax is read, so a name that is bound at runtime, such as
 * a parameter, stays the name it is. `Shopware` is the one name on `window` that is followed.
 */
import ts from 'typescript';
import type { SourceTree } from '../source/source-tree';
import { findModule, parseScript } from './modules';
import { memberName, namedClassElements, passedThrough, withoutTypeSyntax } from './script-nodes';

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

/** A variable with the names it binds: `const { a, b: c } = value` binds each element of its pattern. */
function bindingsOf(variable: ts.VariableDeclaration): (ts.VariableDeclaration | ts.BindingElement)[] {
    return ts.isObjectBindingPattern(variable.name) ? [...variable.name.elements] : [variable];
}

type Declaration = ts.FunctionDeclaration | ts.ClassDeclaration | ts.VariableDeclaration | ts.BindingElement;

/** The variable, function or class that a statement of the scope declares under the name. */
function declaredIn(scope: Scope, name: string): Declaration | undefined {
    return scope.statements
        .flatMap((statement): Declaration[] =>
            ts.isFunctionDeclaration(statement) || ts.isClassDeclaration(statement)
                ? [statement]
                : variablesOf(statement).flatMap(bindingsOf),
        )
        .find((declaration) => isNamed(declaration, name));
}

/** The member of a class that is written under the name, with or without `static`. A constructor parameter is none. */
function classMemberNamed(
    declaration: ts.ClassLikeDeclaration,
    name: string,
    isStatic: boolean,
): ts.ClassElement | undefined {
    return namedClassElements(declaration)
        .filter((candidate) => candidate.name === name && candidate.isStatic === isStatic)
        .map(({ element }) => element)
        .find((element): element is ts.ClassElement => !ts.isParameter(element));
}

/**
 * The names that the runtime puts on `window`, with the export that holds each one. A script uses them without
 * an import.
 */
const GLOBALS: Partial<Record<string, { file: string; exported: string }>> = {
    Shopware: { file: 'src/core/shopware.ts', exported: 'ShopwareInstance' },
};

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

/** The module of `import * as name from '<module>'`. */
function namespaceImportOf(name: string, source: ts.SourceFile): string | undefined {
    return source.statements
        .filter(ts.isImportDeclaration)
        .filter(({ importClause }) => {
            const bindings = importClause?.namedBindings;

            return bindings !== undefined && ts.isNamespaceImport(bindings) && bindings.name.text === name;
        })
        .map(({ moduleSpecifier }) => (ts.isStringLiteral(moduleSpecifier) ? moduleSpecifier.text : undefined))[0];
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
        const followed = hops > MAX_HOPS ? undefined : this.followed(value, source, hops);

        return followed ?? { node: value, source };
    }

    /** What an access, a call that passes its argument through or a name leads to, `undefined` when it leads nowhere. */
    private followed(value: ts.Expression, source: ts.SourceFile, hops: number): Located | undefined {
        if (ts.isPropertyAccessExpression(value)) {
            return this.accessed(value, source, hops);
        }

        const passed = passedThrough(value);

        if (passed !== undefined) {
            return this.valueOf(passed, source, hops + 1);
        }

        return ts.isIdentifier(value) ? this.named(value, source, hops) : undefined;
    }

    /** What a name stands for: its declaration, the value of the variable it names, or the member a destructuring binds. */
    private named(name: ts.Identifier, source: ts.SourceFile, hops: number): Located | undefined {
        const declared = this.declarationOf(name, source, hops) ?? this.globalNamed(name.text, hops);

        if (declared === undefined) {
            return undefined;
        }

        if (ts.isBindingElement(declared.node)) {
            return this.valueOfBinding({ node: declared.node, source: declared.source }, hops);
        }

        return ts.isVariableDeclaration(declared.node)
            ? this.valueOfVariable({ node: declared.node, source: declared.source }, hops)
            : declared;
    }

    /**
     * What `object.name` stands for, where `object` is an object literal, a class with its static members, or
     * `new Example()` with the members of an instance. A method and a getter stand for themselves. `undefined`
     * for every other object, and for a member the object does not write.
     */
    public memberOf(object: Located, name: string, hops = 0): Located | undefined {
        const { node, source } = object;

        if (ts.isObjectLiteralExpression(node)) {
            return this.valueOfMember(
                node.properties.find((property) => memberName(property) === name),
                source,
                hops,
            );
        }

        if (ts.isClassLike(node)) {
            return this.valueOfMember(classMemberNamed(node, name, true), source, hops);
        }

        const instantiated = ts.isNewExpression(node) ? this.valueOf(node.expression, source, hops + 1) : undefined;

        return instantiated !== undefined && ts.isClassLike(instantiated.node)
            ? this.instanceMemberOf({ node: instantiated.node, source: instantiated.source }, name, hops)
            : undefined;
    }

    /** What the member of an instance of the class stands for, `undefined` for one the class does not write itself. */
    public instanceMemberOf(
        { node, source }: Located<ts.ClassLikeDeclaration>,
        name: string,
        hops = 0,
    ): Located | undefined {
        return this.valueOfMember(classMemberNamed(node, name, false), source, hops);
    }

    /** `object.name`, and `this.name` inside a class, which is a member of its instances. */
    private accessed(access: ts.PropertyAccessExpression, source: ts.SourceFile, hops: number): Located | undefined {
        const name = access.name.text;

        if (access.expression.kind !== ts.SyntaxKind.ThisKeyword) {
            return (
                this.exportOfNamespace(access.expression, name, source, hops) ??
                this.memberOf(this.valueOf(access.expression, source, hops + 1), name, hops)
            );
        }

        const enclosing = ts.findAncestor(access, ts.isClassLike);

        return enclosing && this.instanceMemberOf({ node: enclosing, source }, name, hops);
    }

    /** `utils.format` for `import * as utils from './utils'` is what that module exports as `format`. */
    private exportOfNamespace(
        namespace: ts.Expression,
        name: string,
        source: ts.SourceFile,
        hops: number,
    ): Located | undefined {
        const specifier = ts.isIdentifier(namespace) ? namespaceImportOf(namespace.text, source) : undefined;
        const file = specifier === undefined ? undefined : findModule(specifier, source.fileName, this.tree);

        return file === undefined ? undefined : this.exported(file, name, hops + 1);
    }

    private valueOfMember(
        member: ts.ObjectLiteralElementLike | ts.ClassElement | undefined,
        source: ts.SourceFile,
        hops: number,
    ): Located | undefined {
        if (member === undefined) {
            return undefined;
        }

        if (ts.isShorthandPropertyAssignment(member)) {
            return this.valueOf(member.name, source, hops + 1);
        }

        const written = ts.isPropertyAssignment(member) || ts.isPropertyDeclaration(member) ? member.initializer : undefined;

        return written === undefined ? { node: member, source } : this.valueOf(written, source, hops + 1);
    }

    /** What a name stands for that the runtime puts on `window`, `undefined` when the tree does not hold its file. */
    public globalNamed(name: string, hops = 0): Located | undefined {
        const global = GLOBALS[name];

        return global !== undefined && this.tree.has(global.file)
            ? this.exported(global.file, global.exported, hops + 1)
            : undefined;
    }

    /** `b` of `const { a: b } = value` stands for `value.a`. */
    private valueOfBinding({ node, source }: Located<ts.BindingElement>, hops: number): Located | undefined {
        const variable = node.parent.parent;
        const key = node.propertyName ?? node.name;

        return ts.isVariableDeclaration(variable) && variable.initializer !== undefined && ts.isIdentifier(key)
            ? this.memberOf(this.valueOf(variable.initializer, source, hops + 1), key.text, hops)
            : undefined;
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
