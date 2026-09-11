/**
 * @sw-package framework
 */

/**
 * The single structural read of the JavaScript/TypeScript files the SFC migration codemod works on.
 * Discovery is deliberately conservative: a source file that cannot be read or parsed is retained as
 * a diagnostic, while comments, strings, tests, and fixtures never become registrations.
 *
 * Only what later stages actually consume is retained. ASTs are dropped once a file is analysed, so
 * scanning the whole Administration tree does not keep thousands of them alive.
 */

import * as fs from 'fs';
import * as path from 'path';
import { parse, type ParserPlugin } from '@babel/parser';
import { traverseFast } from '@babel/types';
import type * as t from '@babel/types';
import { globSync } from 'glob';
import { keyName, unwrapExpression, unwrapOptions } from './ast';

type SourceRange = {
    start: number;
    end: number;
};

type SourceDiagnostic = {
    file: string;
    /** `<stage>/<code>`, printed verbatim in the run report — data, never branched on. */
    label: string;
    message: string;
    /** The only two things a consumer asks about: does it refuse a component, does it count as an error. */
    isTemplateBinding?: boolean;
    isScanError?: boolean;
    range?: SourceRange;
};

type RegistrationKind = 'register' | 'extend' | 'override';

type RegistrationReference = {
    kind: RegistrationKind;
    name: string;
    parent?: string;
    specifier?: string;
    resolvedDir?: string;
    file: string;
    range: SourceRange;
    inline: boolean;
};

type InlineOverride = {
    file: string;
    name: string;
};

/** Where a component's Twig template lives, and the import statement that has to be removed. */
type TemplateBinding = {
    twigPath: string;
    importRange: SourceRange;
};

type ComponentSource = {
    file: string;
    source: string;
    template: TemplateBinding | null;
    diagnostics: SourceDiagnostic[];
};

type ComponentSourceIndex = {
    /** Every scanned file, mapped to its own scan diagnostics. */
    files: Map<string, SourceDiagnostic[]>;
    components: Map<string, ComponentSource>;
    registrationsByFile: Map<string, RegistrationReference[]>;
    registrationsByDir: Map<string, RegistrationReference[]>;
    duplicateNames: Set<string>;
    inlineOverrides: InlineOverride[];
    diagnostics: SourceDiagnostic[];
};

type ComponentSourceIndexOptions = {
    adminSrc?: string;
    readFile?: (file: string) => string;
};

const DEFAULT_ADMIN_SRC = path.resolve(__dirname, '../../../src');
const SOURCE_PLUGINS: ParserPlugin[] = [
    'typescript',
    'decorators-legacy',
    'classProperties',
    'dynamicImport',
    'importMeta',
    'topLevelAwait',
];
const EXCLUDED_DIRECTORY_NAMES = new Set([
    'node_modules',
    'test',
    'tests',
    'spec',
    'specs',
    '__tests__',
    'fixture',
    'fixtures',
    '__fixtures__',
    'docs',
    'technical-docs',
]);
const SPEC_OR_TEST_FILE = /(?:\.spec|\.test)\.[jt]s$/;

function isContained(root: string, candidate: string): boolean {
    const relative = path.relative(path.resolve(root), path.resolve(candidate));

    return relative === '' || (relative !== '..' && !relative.startsWith(`..${path.sep}`) && !path.isAbsolute(relative));
}

function isExcludedSourceFile(file: string, scanRoot: string): boolean {
    const relative = path.relative(scanRoot, file);
    const parts = relative.split(path.sep);
    const basename = parts.pop() ?? '';

    return parts.some((part) => EXCLUDED_DIRECTORY_NAMES.has(part)) || SPEC_OR_TEST_FILE.test(basename);
}

/**
 * Only a registration call or a default export can make a file interesting, and both leave a literal
 * token behind. Checking for it costs a substring scan and skips parsing the vast majority of files.
 */
function mayHoldComponentSource(source: string): boolean {
    return source.includes('Component.') || source.includes('export default');
}

function rangeOf(node: t.Node): SourceRange {
    return { start: node.start as number, end: node.end as number };
}

function registrationKind(callee: t.Node): RegistrationKind | null {
    if (callee.type !== 'MemberExpression' || callee.computed || callee.property.type !== 'Identifier') {
        return null;
    }

    const kind = callee.property.name;

    if (kind !== 'register' && kind !== 'extend' && kind !== 'override') {
        return null;
    }

    const object = callee.object;

    if (object.type === 'Identifier' && object.name === 'Component') {
        return kind;
    }

    return object.type === 'MemberExpression' &&
        !object.computed &&
        object.object.type === 'Identifier' &&
        object.object.name === 'Shopware' &&
        object.property.type === 'Identifier' &&
        object.property.name === 'Component'
        ? kind
        : null;
}

function stringValue(node: t.Node | undefined): string | null {
    return node?.type === 'StringLiteral' ? node.value : null;
}

/** The module specifier of `import('x')`, `() => import('x')` and their type-wrapped forms. */
function importSpecifier(node: t.Node | undefined): string | null {
    const expression = node ? unwrapExpression(node) : null;

    if (expression?.type === 'ImportExpression') {
        return stringValue(expression.source);
    }

    if (expression?.type === 'CallExpression' && expression.callee.type === 'Import' && expression.arguments.length === 1) {
        return stringValue(expression.arguments[0]);
    }

    if (expression?.type === 'ArrowFunctionExpression' && expression.body.type !== 'BlockStatement') {
        return importSpecifier(expression.body);
    }

    return null;
}

function resolveModuleDir(
    specifier: string,
    file: string,
    scanRoot: string,
    adminSrc: string,
): { dir?: string; outsideRoot: boolean } {
    let resolved: string;

    if (specifier.startsWith('.')) {
        resolved = path.resolve(path.dirname(file), specifier);
    } else if (specifier.startsWith('src/')) {
        resolved = path.resolve(adminSrc, specifier.slice('src/'.length));
    } else {
        return { outsideRoot: false };
    }

    const moduleFile = [
        path.join(resolved, 'index.js'),
        path.join(resolved, 'index.ts'),
        `${resolved}.js`,
        `${resolved}.ts`,
    ].find((candidate) => fs.existsSync(candidate));

    if (!moduleFile) {
        return { outsideRoot: false };
    }

    const dir = path.dirname(moduleFile);

    return isContained(scanRoot, dir) ? { dir, outsideRoot: false } : { outsideRoot: true };
}

function findTemplateBinding(
    file: string,
    options: t.ObjectExpression,
    ast: t.File,
): { binding: TemplateBinding | null; diagnostics: SourceDiagnostic[] } {
    const refuse = (
        code: string,
        message: string,
        range: SourceRange,
    ): { binding: null; diagnostics: SourceDiagnostic[] } => ({
        binding: null,
        diagnostics: [
            {
                file,
                label: `template-binding/${code}`,
                message,
                isTemplateBinding: true,
                range,
            },
        ],
    });

    const templateOptions = options.properties.filter(
        (property): property is t.ObjectProperty => property.type === 'ObjectProperty' && keyName(property) === 'template',
    );

    if (templateOptions.length === 0) {
        return { binding: null, diagnostics: [] };
    }

    if (templateOptions.length > 1) {
        return refuse('template-binding-ambiguous', 'multiple template options found', {
            start: templateOptions[0].start as number,
            end: templateOptions[templateOptions.length - 1].end as number,
        });
    }

    const optionRange = rangeOf(templateOptions[0]);
    const value = unwrapExpression(templateOptions[0].value);

    if (value.type !== 'Identifier') {
        return refuse(
            'template-binding-missing',
            'template option is not a statically resolvable import binding',
            optionRange,
        );
    }

    const twigImports = ast.program.body.filter(
        (statement): statement is t.ImportDeclaration =>
            statement.type === 'ImportDeclaration' && statement.source.value.endsWith('.html.twig'),
    );
    const bound = twigImports.filter((statement) =>
        statement.specifiers.some((specifier) => specifier.local.name === value.name),
    );
    const defaultBound = bound.filter((statement) =>
        statement.specifiers.some(
            (specifier) => specifier.type === 'ImportDefaultSpecifier' && specifier.local.name === value.name,
        ),
    );

    if (defaultBound.length === 0) {
        return bound.length > 0
            ? refuse(
                  'template-binding-not-default',
                  `template binding '${value.name}' is not a default Twig import`,
                  optionRange,
              )
            : refuse(
                  'template-binding-missing',
                  `template binding '${value.name}' has no unique default Twig import`,
                  optionRange,
              );
    }

    const [statement] = defaultBound;
    const specifierText = statement.source.value;
    const importRange = rangeOf(statement);

    if (!specifierText.startsWith('.')) {
        return refuse(
            'template-path-outside-component',
            `template import '${specifierText}' is not a relative component template`,
            importRange,
        );
    }

    if (defaultBound.length > 1) {
        return refuse(
            'template-binding-ambiguous',
            `template binding '${value.name}' resolves to multiple default Twig imports`,
            optionRange,
        );
    }

    const twigPath = path.resolve(path.dirname(file), specifierText);

    if (!isContained(path.dirname(file), twigPath)) {
        return refuse(
            'template-path-outside-component',
            `template import '${specifierText}' escapes the component directory`,
            importRange,
        );
    }

    return {
        binding: { twigPath, importRange },
        diagnostics: fs.existsSync(twigPath)
            ? []
            : [
                  {
                      file,
                      label: 'template-binding/template-file-not-found',
                      message: `template file not found: ${twigPath}`,
                      isTemplateBinding: true,
                      range: importRange,
                  },
              ],
    };
}

function extractRegistrations(
    ast: t.File,
    file: string,
    scanRoot: string,
    adminSrc: string,
): { registrations: RegistrationReference[]; diagnostics: SourceDiagnostic[] } {
    const registrations: RegistrationReference[] = [];
    const diagnostics: SourceDiagnostic[] = [];

    traverseFast(ast, (node) => {
        if (node.type !== 'CallExpression') {
            return;
        }

        const kind = registrationKind(node.callee);

        if (!kind || node.arguments.length < 2) {
            return;
        }

        const name = stringValue(node.arguments[0]);

        if (!name) {
            return;
        }

        const parent = kind === 'extend' ? (stringValue(node.arguments[1]) ?? undefined) : undefined;
        const loader = node.arguments[kind === 'extend' ? 2 : 1];
        const specifier = importSpecifier(loader);
        const inline = kind === 'override' && loader?.type === 'ObjectExpression';

        if (!specifier && !inline) {
            return;
        }

        const reference: RegistrationReference = {
            kind,
            name,
            ...(parent ? { parent } : {}),
            ...(specifier ? { specifier } : {}),
            file,
            range: rangeOf(node),
            inline,
        };

        if (specifier) {
            const resolved = resolveModuleDir(specifier, file, scanRoot, adminSrc);

            if (resolved.dir) {
                reference.resolvedDir = resolved.dir;
            } else if (resolved.outsideRoot) {
                diagnostics.push({
                    file,
                    label: 'registration/registration-path-outside-root',
                    message: `registration import '${specifier}' resolves outside the scan root`,
                    isScanError: true,
                    range: reference.range,
                });
            }
        }

        registrations.push(reference);
    });

    return { registrations, diagnostics };
}

/** Reads and parses one file, mapping every failure to a diagnostic rather than an exception. */
function parseSourceFile(
    file: string,
    readFile: (file: string) => string,
): { source: string; ast: t.File | null; diagnostics: SourceDiagnostic[] } {
    let source: string;

    try {
        source = readFile(file);
    } catch (error) {
        return {
            source: '',
            ast: null,
            diagnostics: [
                {
                    file,
                    label: 'scan/read-failed',
                    message: `could not read source: ${error instanceof Error ? error.message : String(error)}`,
                    isScanError: true,
                },
            ],
        };
    }

    if (!mayHoldComponentSource(source)) {
        return { source, ast: null, diagnostics: [] };
    }

    try {
        return { source, ast: parse(source, { sourceType: 'module', plugins: SOURCE_PLUGINS }), diagnostics: [] };
    } catch (error) {
        return {
            source,
            ast: null,
            diagnostics: [
                {
                    file,
                    label: 'scan/parse-failed',
                    message: `could not parse source: ${error instanceof Error ? error.message : String(error)}`,
                    isScanError: true,
                },
            ],
        };
    }
}

function collectComponentSourceIndex(scanRoot: string, options: ComponentSourceIndexOptions = {}): ComponentSourceIndex {
    const absoluteScanRoot = path.resolve(scanRoot);
    const adminSrc = path.resolve(options.adminSrc ?? DEFAULT_ADMIN_SRC);
    const readFile = options.readFile ?? ((file: string): string => fs.readFileSync(file, 'utf8'));
    const index: ComponentSourceIndex = {
        files: new Map(),
        components: new Map(),
        registrationsByFile: new Map(),
        registrationsByDir: new Map(),
        duplicateNames: new Set(),
        inlineOverrides: [],
        diagnostics: [],
    };
    const dirsByName = new Map<string, Set<string>>();
    const sourceFiles = globSync('**/*.{js,ts}', {
        cwd: absoluteScanRoot,
        absolute: true,
        nodir: true,
        ignore: '**/node_modules/**',
    })
        .map((file) => path.resolve(file))
        .filter((file) => !isExcludedSourceFile(file, absoluteScanRoot))
        .sort();

    for (const file of sourceFiles) {
        const parsed = parseSourceFile(file, readFile);

        index.files.set(file, parsed.diagnostics);
        index.diagnostics.push(...parsed.diagnostics);

        if (!parsed.ast) {
            index.registrationsByFile.set(file, []);
            continue;
        }

        const extracted = extractRegistrations(parsed.ast, file, absoluteScanRoot, adminSrc);
        const exportDefault = parsed.ast.program.body.find(
            (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
        );
        // Files without a default export are registries or barrels, not components.
        const componentOptions = exportDefault ? unwrapOptions(exportDefault.declaration) : null;
        const template = componentOptions
            ? findTemplateBinding(file, componentOptions, parsed.ast)
            : { binding: null, diagnostics: [] };
        const diagnostics = [
            ...extracted.diagnostics,
            ...template.diagnostics,
        ];

        index.diagnostics.push(...diagnostics);
        index.registrationsByFile.set(file, extracted.registrations);

        if (exportDefault) {
            index.components.set(file, {
                file,
                source: parsed.source,
                template: template.binding,
                diagnostics,
            });
        }

        for (const registration of extracted.registrations) {
            if (registration.inline && registration.kind === 'override') {
                index.inlineOverrides.push({ file: registration.file, name: registration.name });
            }

            if (!registration.resolvedDir) {
                continue;
            }

            index.registrationsByDir.set(registration.resolvedDir, [
                ...(index.registrationsByDir.get(registration.resolvedDir) ?? []),
                registration,
            ]);
            dirsByName.set(
                registration.name,
                (dirsByName.get(registration.name) ?? new Set()).add(registration.resolvedDir),
            );
        }
    }

    for (const [
        dir,
        registrations,
    ] of index.registrationsByDir) {
        if (registrations.length > 1) {
            index.diagnostics.push({
                file: registrations[0].file,
                label: 'registration/registration-ambiguous',
                message: `multiple registrations resolve to '${dir}'`,
                range: registrations[0].range,
            });
        }
    }

    for (const [
        name,
        dirs,
    ] of dirsByName) {
        if (dirs.size > 1) {
            index.duplicateNames.add(name);
        }
    }

    return index;
}

export {
    collectComponentSourceIndex,
    isContained,
    type ComponentSource,
    type ComponentSourceIndex,
    type ComponentSourceIndexOptions,
    type InlineOverride,
    type RegistrationKind,
    type RegistrationReference,
    type SourceDiagnostic,
    type SourceRange,
    type TemplateBinding,
};
