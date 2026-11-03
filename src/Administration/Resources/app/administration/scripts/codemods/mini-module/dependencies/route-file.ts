/**
 * @sw-package framework
 * @private
 */

import path from 'node:path';
import ts from 'typescript';
import { scanGlobalUses, type Alias } from './global-uses';
import type { AdminIndex, Dependency, FileRewrite, OutsideEdit, Rewrite, RewriteContext, UseRewrite } from './model';
import { removalRange, statementRange } from './rewrites/helpers';

/** What a dependency stands for, to compare two of them. */
const identity = (dependency: Dependency) =>
    dependency.kind === 'reexport'
        ? `${dependency.module}|${dependency.imported}|${dependency.exported}|${!!dependency.typeOnly}`
        : `${dependency.name}|${dependency.body}|${JSON.stringify(dependency.imports)}`;

/**
 * The dependencies of one module, shared by its files, so two files never give one name two meanings.
 * `accept` takes a dependency, or refuses it when its name already stands for something else.
 */
export class DependencyRegistry {
    private readonly byName = new Map<string, Dependency>();

    accept(dependency: Dependency): boolean {
        const name = dependency.kind === 'reexport' ? dependency.exported : dependency.name;
        const known = this.byName.get(name);
        if (known) {
            return identity(known) === identity(dependency);
        }

        this.byName.set(name, dependency);
        return true;
    }

    /** A copy to route one file with, which `adopt` takes back once the file is done. */
    clone(): DependencyRegistry {
        const copy = new DependencyRegistry();
        this.byName.forEach((dependency, name) => copy.byName.set(name, dependency));
        return copy;
    }

    adopt(other: DependencyRegistry) {
        other.byName.forEach((dependency, name) => this.byName.set(name, dependency));
    }

    fits(dependency: Dependency): boolean {
        const name = dependency.kind === 'reexport' ? dependency.exported : dependency.name;
        const known = this.byName.get(name);
        return !known || identity(known) === identity(dependency);
    }

    get all(): Dependency[] {
        return [...this.byName.values()];
    }
}

/** What routing a file produced: the new source, and what must change outside the module for it. */
export type RoutedFile = { code: string; outsideEdits: OutsideEdit[] };

export type RouteOptions = {
    fileName: string;
    moduleRoot: string;
    index: AdminIndex;
    registry: DependencyRegistry;
    useRewrites: UseRewrite[];
    fileRewrites: FileRewrite[];
};

const isTypeOnlyName = (registry: DependencyRegistry, exported: string) =>
    registry.all.some(
        (dependency) => dependency.kind === 'reexport' && dependency.exported === exported && dependency.typeOnly,
    );

/** Adds the names to the file's import of `../dependencies`, after its last import, or to the one it has. */
const addDependencyImport = (
    code: string,
    fileName: string,
    specifier: string,
    imports: Map<string, string>,
    registry: DependencyRegistry,
) => {
    const isTypeScript = /\.ts$/.test(fileName);
    const names = [...imports.entries()]
        .sort(([, a], [, b]) => a.localeCompare(b, 'en', { sensitivity: 'base' }))
        .map(([local, exported]) => {
            const binding = local === exported ? local : `${exported} as ${local}`;
            return isTypeScript && isTypeOnlyName(registry, exported) ? `type ${binding}` : binding;
        });
    const statement = `import { ${names.join(', ')} } from '${specifier}';\n`;
    const source = ts.createSourceFile(fileName, code, ts.ScriptTarget.Latest, true);
    const lastImport = [...source.statements].reverse().find(ts.isImportDeclaration);

    if (!lastImport) {
        const leadingComment = /^\/\*\*[\s\S]*?\*\/\n/.exec(code)?.[0] ?? '';
        const rest = code.slice(leadingComment.length);
        return `${leadingComment}${statement}${rest.startsWith('\n') ? '' : '\n'}${rest}`;
    }

    const at = statementRange(source, lastImport).end;
    return `${code.slice(0, at)}${statement}${code.slice(at)}`;
};

/** Removes the destructured names whose references were all rewritten, and the statement when none is left. */
const removeUnusedAliases = (
    source: ts.SourceFile,
    aliases: Alias[],
    isHandled: (node: ts.Node) => boolean,
): { rewrites: Rewrite[]; removed: Set<Alias> } => {
    const removed = new Set<Alias>();
    const removedStatements = new Set<ts.Node>();
    const isGone = (node: ts.Node) =>
        isHandled(node) ||
        [...removedStatements].some((statement) => node.pos >= statement.pos && node.end <= statement.end);

    // Later destructures may read earlier ones, as in `const { Data } = Shopware; const { Criteria } = Data;`.
    [...aliases].reverse().forEach((alias) => {
        if (alias.references.every(isGone)) {
            removed.add(alias);
        }

        const siblings = aliases.filter((candidate) => candidate.statement === alias.statement);
        if (siblings.every((sibling) => removed.has(sibling))) {
            removedStatements.add(alias.statement);
        }
    });

    const statements = new Set([...removed].map((alias) => alias.statement));
    const rewrites = [...statements].map((statement): Rewrite => {
        if (removedStatements.has(statement)) {
            return { ...removalRange(source, statement), text: '', imports: {}, dependencies: [], outsideEdits: [] };
        }

        const pattern = statement.declarationList.declarations[0].name as ts.ObjectBindingPattern;
        const kept = pattern.elements.filter((element) => ![...removed].some((alias) => alias.element === element));
        return {
            start: pattern.getStart(source),
            end: pattern.getEnd(),
            text: `{ ${kept.map((element) => element.getText(source)).join(', ')} }`,
            imports: {},
            dependencies: [],
            outsideEdits: [],
        };
    });

    return { rewrites, removed };
};

const applyRewrites = (code: string, rewrites: Rewrite[]) =>
    [...rewrites]
        .sort((a, b) => b.start - a.start)
        .reduce(
            (text, { start, end, text: replacement }) => `${text.slice(0, start)}${replacement}${text.slice(end)}`,
            code,
        );

/**
 * Routes what one file of `src/` takes from outside the module through `../dependencies`: each rewrite takes
 * the uses it can rewrite without changing behaviour, everything else stays as it was for the sweep.
 * Names a destructure no longer needs are removed, and the file imports the new names from `../dependencies`.
 * A rewrite whose import would clash with a name the file keeps is dropped, and the file routed again without it.
 *
 * @example
 * routeFile("const { Mixin } = Shopware;\nexport default { mixins: [Mixin.getByName('notification')] };\n", options);
 * // → "import { notificationMixin } from '../dependencies';\nexport default { mixins: [notificationMixin] };\n"
 */
export function routeFile(code: string, options: RouteOptions, banned = new Set<string>()): RoutedFile {
    const { fileName, moduleRoot, index } = options;
    const registry = options.registry.clone();
    const source = ts.createSourceFile(fileName, code, ts.ScriptTarget.Latest, true);
    const scan = scanGlobalUses(source);
    const aliasNames = new Set(scan.aliases.map((alias) => alias.name));
    const dependenciesFile = path.join(moduleRoot, 'dependencies.ts');

    const context: RewriteContext = {
        source,
        index,
        fileName,
        moduleRoot,
        dependenciesFile,
        declared: scan.declared,
        isFree: (name) =>
            !banned.has(name) && (!scan.declared.has(name) || (aliasNames.has(name) && scan.declared.get(name) === 1)),
    };

    const imports = new Map<string, string>();
    const accepted: Rewrite[] = [];
    const take = (rewrite: Rewrite | null) => {
        if (!rewrite) {
            return false;
        }

        const clashes = Object.entries(rewrite.imports).some(
            ([local, exported]) => imports.has(local) && imports.get(local) !== exported,
        );
        if (clashes || !rewrite.dependencies.every((dependency) => registry.fits(dependency))) {
            return false;
        }

        rewrite.dependencies.forEach((dependency) => registry.accept(dependency));
        Object.entries(rewrite.imports).forEach(([local, exported]) => imports.set(local, exported));
        accepted.push(rewrite);
        return true;
    };

    scan.uses.forEach((use) => options.useRewrites.some((rewrite) => take(rewrite(use, context))));
    options.fileRewrites.forEach((rewrite) => rewrite(context).forEach(take));

    const isHandled = (node: ts.Node) =>
        accepted.some((rewrite) => node.getStart(source) >= rewrite.start && node.getEnd() <= rewrite.end);
    const { rewrites: aliasRewrites, removed } = removeUnusedAliases(source, scan.aliases, isHandled);

    const clash = scan.aliases.find((alias) => !removed.has(alias) && imports.has(alias.name));
    if (clash) {
        return routeFile(code, options, new Set([...banned, clash.name]));
    }

    if (accepted.length === 0) {
        return { code, outsideEdits: [] };
    }

    options.registry.adopt(registry);
    const specifier = path.relative(path.dirname(fileName), dependenciesFile).split(path.sep).join('/').replace(/\.ts$/, '');
    const rewritten = applyRewrites(
        code,
        [...accepted, ...aliasRewrites].map((rewrite) => shorthandAware(source, rewrite)),
    );
    return {
        code: imports.size > 0 ? addDependencyImport(rewritten, fileName, specifier, imports, registry) : rewritten,
        outsideEdits: accepted.flatMap((rewrite) => rewrite.outsideEdits),
    };
}

/** A shorthand property `{ Criteria }` keeps its key when its value is replaced: `{ Criteria: … }`. */
const shorthandAware = (source: ts.SourceFile, rewrite: Rewrite): Rewrite => {
    const node = findNodeAt(source, rewrite.start, rewrite.end);
    if (
        node &&
        ts.isShorthandPropertyAssignment(node.parent) &&
        node.parent.name === node &&
        node.getText(source) !== rewrite.text
    ) {
        return { ...rewrite, text: `${node.getText(source)}: ${rewrite.text}` };
    }

    return rewrite;
};

const findNodeAt = (source: ts.SourceFile, start: number, end: number): ts.Node | null => {
    let found: ts.Node | null = null;
    const visit = (node: ts.Node) => {
        if (node.getStart(source) === start && node.getEnd() === end && ts.isIdentifier(node)) {
            found = node;
        }

        if (node.pos <= start && node.end >= end) {
            node.forEachChild(visit);
        }
    };
    visit(source);

    return found;
};
