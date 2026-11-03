/**
 * @sw-package framework
 * @private
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import { createGlobalResolver } from './global-resolver';
import type { AdminIndex, Registered, Typing } from './model';
import { findRegisterCall, REGISTER_CALLEES, type RegistryKind } from './registrations';

const parse = (file: string) => ts.createSourceFile(file, fs.readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true);

/** The script files below src/ that contain the text, except specs, as absolute paths. */
const grepFiles = (adminRoot: string, text: string) => {
    try {
        return execFileSync(
            'git',
            [
                'grep',
                '-lF',
                text,
                '--',
                'src/**/*.js',
                'src/**/*.ts',
            ],
            {
                cwd: adminRoot,
                encoding: 'utf8',
            },
        )
            .split('\n')
            .filter((file) => file && !/\.spec\.[jt]s$/.test(file) && !file.endsWith('.d.ts'))
            .map((file) => path.join(adminRoot, file));
    } catch (error) {
        if ((error as { status?: number }).status === 1) {
            return [];
        }

        throw error;
    }
};

/** Every string literal handed to a call whose callee ends with the given text, such as `addServiceProvider`. */
const calledWith = (source: ts.SourceFile, callee: string) => {
    const names: string[] = [];
    const visit = (node: ts.Node) => {
        if (ts.isCallExpression(node) && node.expression.getText(source).endsWith(callee)) {
            const [first] = node.arguments;
            if (first && ts.isStringLiteral(first)) {
                names.push(first.text);
            }
        }

        node.forEachChild(visit);
    };
    visit(source);

    return names;
};

/** The names of `this.name = 'x'` in the API services, which register under that name. */
const apiServiceNames = (source: ts.SourceFile) => {
    const names: string[] = [];
    const visit = (node: ts.Node) => {
        if (
            ts.isBinaryExpression(node) &&
            node.operatorToken.kind === ts.SyntaxKind.EqualsToken &&
            node.left.getText(source) === 'this.name' &&
            ts.isStringLiteral(node.right)
        ) {
            names.push(node.right.text);
        }

        node.forEachChild(visit);
    };
    visit(source);

    return names;
};

const UNTYPED = new Set(['$TSFixMe', 'any']);

/**
 * How the members of a global interface, such as `ServiceContainer`, are typed, by name: `typed` or `any`.
 * An index signature counts for every other name, under the key `[index]`.
 */
const interfaceTypings = (source: ts.SourceFile, name: string) => {
    const typings = new Map<string, Typing>();
    const visit = (node: ts.Node) => {
        if (ts.isInterfaceDeclaration(node) && node.name.text === name) {
            node.members.forEach((member) => {
                const type = (member as { type?: ts.TypeNode }).type;
                const typing: Typing = type && UNTYPED.has(type.getText(source)) ? 'any' : 'typed';
                if (ts.isIndexSignatureDeclaration(member)) {
                    typings.set('[index]', typing);
                } else if (member.name && (ts.isIdentifier(member.name) || ts.isStringLiteral(member.name))) {
                    typings.set(member.name.text, typing);
                }
            });
        }

        node.forEachChild(visit);
    };
    visit(source);

    return typings;
};

/** The names src/app/service-names exports through its index.ts. */
export const exportedServiceNames = (adminRoot: string) =>
    parse(path.join(adminRoot, 'src/app/service-names/index.ts')).statements.flatMap((statement) =>
        ts.isExportDeclaration(statement) && statement.exportClause && ts.isNamedExports(statement.exportClause)
            ? statement.exportClause.elements.map((element) => element.name.text)
            : [],
    );

/**
 * The helpers that `Component.getComponentHelper()` returns, from src/app/init/component-helper.init.ts:
 * the module and export each one comes from.
 */
const readComponentHelpers = (adminRoot: string, moduleOf: (fromFile: string, specifier: string) => string) => {
    const file = path.join(adminRoot, 'src/app/init/component-helper.init.ts');
    const source = parse(file);
    const imports = new Map<string, { module: string; imported: string } | { namespace: string }>();

    source.statements.forEach((statement) => {
        if (
            !ts.isImportDeclaration(statement) ||
            !ts.isStringLiteral(statement.moduleSpecifier) ||
            !statement.importClause
        ) {
            return;
        }

        const module = moduleOf(file, statement.moduleSpecifier.text);
        const { name, namedBindings } = statement.importClause;
        if (name) {
            imports.set(name.text, { module, imported: 'default' });
        }

        if (namedBindings && ts.isNamespaceImport(namedBindings)) {
            imports.set(namedBindings.name.text, { namespace: statement.moduleSpecifier.text });
        } else if (namedBindings) {
            namedBindings.elements.forEach((element) =>
                imports.set(element.name.text, { module, imported: (element.propertyName ?? element.name).text }),
            );
        }
    });

    const helpers = new Map<string, { module: string; imported: string }>();
    const visit = (node: ts.Node) => {
        if (
            ts.isVariableDeclaration(node) &&
            ts.isIdentifier(node.name) &&
            node.name.text === 'componentHelper' &&
            node.initializer &&
            ts.isObjectLiteralExpression(node.initializer)
        ) {
            node.initializer.properties.forEach((property) => {
                if (ts.isShorthandPropertyAssignment(property)) {
                    const binding = imports.get(property.name.text);
                    if (binding && 'module' in binding) {
                        helpers.set(property.name.text, binding);
                    }
                } else if (ts.isSpreadAssignment(property) && ts.isIdentifier(property.expression)) {
                    const binding = imports.get(property.expression.text);
                    if (binding && 'namespace' in binding) {
                        const target = moduleOf(file, binding.namespace);
                        exportedNames(path.join(adminRoot, `${target}.ts`)).forEach((name) =>
                            helpers.set(name, { module: target, imported: name }),
                        );
                    }
                }
            });
        }

        node.forEachChild(visit);
    };
    visit(source);

    return helpers;
};

/** The value names a file exports, for a module that a helper object spreads. */
const exportedNames = (file: string) =>
    fs.existsSync(file)
        ? parse(file).statements.flatMap((statement) => {
              const isExported =
                  ts.canHaveModifiers(statement) &&
                  ts.getModifiers(statement)?.some((modifier) => modifier.kind === ts.SyntaxKind.ExportKeyword);
              if (!isExported) {
                  return [];
              }

              if (ts.isFunctionDeclaration(statement) && statement.name) {
                  return [statement.name.text];
              }

              return ts.isVariableStatement(statement)
                  ? statement.declarationList.declarations.flatMap((declaration) =>
                        ts.isIdentifier(declaration.name) ? [declaration.name.text] : [],
                    )
                  : [];
          })
        : [];

/**
 * Builds what the rewrites know about the Administration, for the module at `moduleRoot`.
 * Module paths for dependencies.ts are relative when the file is in the same `src/module/<name>`, `src/…` otherwise.
 */
export function createAdminIndex(adminRoot: string, moduleRoot: string): AdminIndex {
    const dependenciesDirectory = moduleRoot;
    const topModule = /^(src\/module\/[^/]+)\//.exec(
        `${path.relative(adminRoot, moduleRoot).split(path.sep).join('/')}/`,
    )?.[1];

    const modulePath = (file: string) => {
        const id = path
            .relative(adminRoot, file)
            .split(path.sep)
            .join('/')
            .replace(/\.[jt]s$/, '')
            .replace(/\/index$/, '');

        if (topModule && id.startsWith(`${topModule}/`)) {
            const relativePath = path.relative(dependenciesDirectory, path.join(adminRoot, id)).split(path.sep).join('/');
            return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
        }

        return id;
    };

    const moduleOf = (fromFile: string, specifier: string) =>
        specifier.startsWith('.') ? modulePath(path.resolve(path.dirname(fromFile), specifier)) : specifier;

    const findRegistered = (kind: RegistryKind) => {
        const cache = new Map<string, { file: string; call: NonNullable<ReturnType<typeof findRegisterCall>> } | null>();

        return (name: string) => {
            if (!cache.has(name)) {
                const files = [
                    ...new Set(REGISTER_CALLEES[kind].flatMap((callee) => grepFiles(adminRoot, `${callee}(`))),
                ];
                const found = files
                    .map((file) => ({ file, call: findRegisterCall(parse(file), kind, name) }))
                    .filter(
                        (candidate): candidate is { file: string; call: NonNullable<typeof candidate.call> } =>
                            !!candidate.call,
                    );
                cache.set(name, found.length === 1 ? found[0] : null);
            }

            return cache.get(name) ?? null;
        };
    };

    const registeredStore = findRegistered('Store');
    const registeredFilter = findRegistered('Filter');
    const registeredMixin = findRegistered('Mixin');
    const toRegistered = (found: ReturnType<typeof registeredStore>): Registered | null =>
        found ? { module: modulePath(found.file), file: found.file, exportName: found.call.exportName } : null;

    const globalTypes = parse(path.join(adminRoot, 'src/global.types.ts'));
    const typings = new Map<string, Map<string, Typing>>();
    const typing = (container: string, name: string): Typing => {
        if (!typings.has(container)) {
            typings.set(container, interfaceTypings(globalTypes, container));
        }

        const members = typings.get(container)!;
        return members.get(name) ?? members.get('[index]') ?? null;
    };

    let services: Set<string> | null = null;
    const serviceNames = () => {
        services ??= new Set([
            ...interfaceTypings(globalTypes, 'ServiceContainer').keys(),
            ...grepFiles(adminRoot, 'this.name = ').flatMap((file) => apiServiceNames(parse(file))),
            ...grepFiles(adminRoot, 'addServiceProvider(').flatMap((file) => calledWith(parse(file), 'addServiceProvider')),
            ...grepFiles(adminRoot, 'Service().register(').flatMap((file) => calledWith(parse(file), 'Service().register')),
        ]);

        return services;
    };

    let helpers: ReturnType<typeof readComponentHelpers> | null = null;
    const resolver = createGlobalResolver(adminRoot);
    const exported = new Set(exportedServiceNames(adminRoot));

    return {
        isService: (name) => serviceNames().has(name),
        hasServiceName: (name) => exported.has(name),
        typing,
        findMixin: (name) => {
            const found = registeredMixin(name);
            return found?.call.isDefaultExport ? modulePath(found.file) : null;
        },
        findStore: (id) => toRegistered(registeredStore(id)),
        findFilter: (name) => toRegistered(registeredFilter(name)),
        findComponentHelper: (name) => {
            helpers ??= readComponentHelpers(adminRoot, moduleOf);
            return helpers.get(name) ?? null;
        },
        resolveGlobal: (globalPath, isCallee) => {
            const source = resolver(globalPath, isCallee);
            if (!source) {
                return null;
            }

            const isFile = source.module.startsWith('src/');
            return { ...source, module: isFile ? modulePath(path.join(adminRoot, source.module)) : source.module };
        },
    };
}
