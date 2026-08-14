/**
 * @sw-package framework
 */

/**
 * The source model is the single structural read of the JavaScript/TypeScript files used by the
 * SFC migration codemod. Discovery is deliberately conservative: a source file that cannot be
 * read or parsed is retained as a diagnostic, while comments, strings, tests, and fixtures never
 * become registrations.
 */

import * as fs from 'fs';
import * as path from 'path';
import { parse, type ParserPlugin } from '@babel/parser';
import { VISITOR_KEYS } from '@babel/types';
import type * as t from '@babel/types';
import { globSync } from 'glob';

type SourceRange = {
    start: number;
    end: number;
};

type SourceDiagnostic = {
    file: string;
    stage: 'scan' | 'registration' | 'template-binding';
    code:
        | 'read-failed'
        | 'parse-failed'
        | 'registration-ambiguous'
        | 'registration-path-outside-root'
        | 'template-binding-missing'
        | 'template-binding-not-default'
        | 'template-binding-ambiguous'
        | 'template-path-outside-component'
        | 'template-file-not-found';
    detail?: string;
    message: string;
    range?: SourceRange;
};

type ParsedSourceFile = {
    file: string;
    source: string;
    ast: t.File | null;
    diagnostics: SourceDiagnostic[];
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

type TemplateBinding = {
    localName: string;
    specifier: string;
    twigPath: string;
    importNode: t.ImportDeclaration;
    importSpecifier: t.ImportDefaultSpecifier;
    optionNode: t.ObjectProperty;
};

type ComponentSource = {
    file: string;
    parsed: ParsedSourceFile;
    exportDefault: t.ExportDefaultDeclaration;
    options: t.ObjectExpression | null;
    template: TemplateBinding | null;
    registrations: RegistrationReference[];
    diagnostics: SourceDiagnostic[];
};

type ComponentSourceIndex = {
    files: Map<string, ParsedSourceFile>;
    components: Map<string, ComponentSource>;
    registrationsByFile: Map<string, RegistrationReference[]>;
    registrationsByDir: Map<string, RegistrationReference[]>;
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

function childNodes(node: t.Node): t.Node[] {
    const children: t.Node[] = [];

    for (const key of VISITOR_KEYS[node.type] ?? []) {
        const value = (node as unknown as Record<string, unknown>)[key];

        if (Array.isArray(value)) {
            children.push(
                ...value.filter((child): child is t.Node => Boolean(child) && typeof child === 'object' && 'type' in child),
            );
        } else if (value && typeof value === 'object' && 'type' in value) {
            children.push(value as t.Node);
        }
    }

    return children;
}

function walk(node: t.Node, visit: (node: t.Node) => void): void {
    visit(node);

    for (const child of childNodes(node)) {
        walk(child, visit);
    }
}

function unwrapExpression(node: t.Node): t.Node {
    if (
        node.type === 'TSAsExpression' ||
        node.type === 'TSSatisfiesExpression' ||
        node.type === 'TSNonNullExpression' ||
        node.type === 'TypeCastExpression' ||
        node.type === 'ParenthesizedExpression'
    ) {
        return unwrapExpression(node.expression);
    }

    return node;
}

function unwrapOptions(node: t.Node): t.ObjectExpression | null {
    const expression = unwrapExpression(node);

    if (expression.type === 'ObjectExpression') {
        return expression;
    }

    if (expression.type === 'CallExpression' && expression.arguments.length > 0) {
        const callee = expression.callee;
        const calleeName =
            callee.type === 'Identifier'
                ? callee.name
                : callee.type === 'MemberExpression' && !callee.computed && callee.property.type === 'Identifier'
                  ? callee.property.name
                  : null;

        if (
            (calleeName === 'wrapComponentConfig' || calleeName === 'defineComponent') &&
            expression.arguments[0].type === 'ObjectExpression'
        ) {
            return expression.arguments[0];
        }
    }

    return null;
}

function propertyName(property: t.ObjectProperty): string | null {
    if (property.computed) {
        return null;
    }

    if (property.key.type === 'Identifier') {
        return property.key.name;
    }

    if (property.key.type === 'StringLiteral') {
        return property.key.value;
    }

    return null;
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

    if (
        object.type === 'MemberExpression' &&
        !object.computed &&
        object.object.type === 'Identifier' &&
        object.object.name === 'Shopware' &&
        object.property.type === 'Identifier' &&
        object.property.name === 'Component'
    ) {
        return kind;
    }

    return null;
}

function stringValue(node: t.Node | undefined): string | null {
    return node?.type === 'StringLiteral' ? node.value : null;
}

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

    const candidates = [
        path.join(resolved, 'index.js'),
        path.join(resolved, 'index.ts'),
        `${resolved}.js`,
        `${resolved}.ts`,
    ];
    const moduleFile = candidates.find((candidate) => fs.existsSync(candidate));

    if (!moduleFile) {
        return { outsideRoot: false };
    }

    const dir = path.dirname(moduleFile);

    return isContained(scanRoot, dir) ? { dir, outsideRoot: false } : { outsideRoot: true };
}

function findTemplateBinding(
    file: string,
    options: t.ObjectExpression | null,
    ast: t.File | null,
): { binding: TemplateBinding | null; diagnostics: SourceDiagnostic[] } {
    if (!options) {
        return { binding: null, diagnostics: [] };
    }

    const templateOptions = options.properties.filter(
        (property): property is t.ObjectProperty =>
            property.type === 'ObjectProperty' && propertyName(property) === 'template',
    );

    if (templateOptions.length === 0) {
        return { binding: null, diagnostics: [] };
    }

    if (templateOptions.length > 1) {
        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-binding-ambiguous',
                    message: 'multiple template options found',
                    range: {
                        start: templateOptions[0].start as number,
                        end: templateOptions[templateOptions.length - 1].end as number,
                    },
                },
            ],
        };
    }

    const optionNode = templateOptions[0];
    const value = unwrapExpression(optionNode.value);

    if (value.type !== 'Identifier') {
        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-binding-missing',
                    message: 'template option is not a statically resolvable import binding',
                    range: { start: optionNode.start as number, end: optionNode.end as number },
                },
            ],
        };
    }

    if (!ast) {
        return { binding: null, diagnostics: [] };
    }

    const matchingImports = ast.program.body.flatMap((statement) => {
        if (statement.type !== 'ImportDeclaration' || !statement.source.value.endsWith('.html.twig')) {
            return [];
        }

        return statement.specifiers
            .filter((specifier): specifier is t.ImportDefaultSpecifier => specifier.type === 'ImportDefaultSpecifier')
            .filter((specifier) => specifier.local.name === value.name)
            .map((specifier) => ({ statement, specifier }));
    });
    const nonDefaultMatches = ast.program.body.flatMap((statement) => {
        if (statement.type !== 'ImportDeclaration' || !statement.source.value.endsWith('.html.twig')) {
            return [];
        }

        return statement.specifiers.filter((specifier) => specifier.local.name === value.name);
    });

    if (matchingImports.length === 0) {
        const code = nonDefaultMatches.length > 0 ? 'template-binding-not-default' : 'template-binding-missing';

        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code,
                    message:
                        code === 'template-binding-not-default'
                            ? `template binding '${value.name}' is not a default Twig import`
                            : `template binding '${value.name}' has no unique default Twig import`,
                    range: { start: optionNode.start as number, end: optionNode.end as number },
                },
            ],
        };
    }

    const [{ statement, specifier }] = matchingImports;
    const specifierText = statement.source.value;

    if (!specifierText.startsWith('.')) {
        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-path-outside-component',
                    message: `template import '${specifierText}' is not a relative component template`,
                    range: { start: statement.start as number, end: statement.end as number },
                },
            ],
        };
    }

    if (matchingImports.length > 1) {
        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-binding-ambiguous',
                    message: `template binding '${value.name}' resolves to multiple default Twig imports`,
                    range: { start: optionNode.start as number, end: optionNode.end as number },
                },
            ],
        };
    }

    const twigPath = path.resolve(path.dirname(file), specifierText);
    const componentDir = path.dirname(file);

    if (!isContained(componentDir, twigPath)) {
        return {
            binding: null,
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-path-outside-component',
                    message: `template import '${specifierText}' escapes the component directory`,
                    range: { start: statement.start as number, end: statement.end as number },
                },
            ],
        };
    }

    if (!fs.existsSync(twigPath)) {
        return {
            binding: {
                localName: value.name,
                specifier: specifierText,
                twigPath,
                importNode: statement,
                importSpecifier: specifier,
                optionNode,
            },
            diagnostics: [
                {
                    file,
                    stage: 'template-binding',
                    code: 'template-file-not-found',
                    message: `template file not found: ${twigPath}`,
                    range: { start: statement.start as number, end: statement.end as number },
                },
            ],
        };
    }

    return {
        binding: {
            localName: value.name,
            specifier: specifierText,
            twigPath,
            importNode: statement,
            importSpecifier: specifier,
            optionNode,
        },
        diagnostics: [],
    };
}

function findDefaultExport(ast: t.File): t.ExportDefaultDeclaration | null {
    return (
        ast.program.body.find(
            (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
        ) ?? null
    );
}

function extractRegistrations(
    ast: t.File,
    file: string,
    scanRoot: string,
    adminSrc: string,
): { registrations: RegistrationReference[]; diagnostics: SourceDiagnostic[] } {
    const registrations: RegistrationReference[] = [];
    const diagnostics: SourceDiagnostic[] = [];

    walk(ast, (node) => {
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
        const loaderIndex = kind === 'extend' ? 2 : 1;
        const loader = node.arguments[loaderIndex];
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
            range: { start: node.start as number, end: node.end as number },
            inline,
        };

        if (specifier) {
            const resolved = resolveModuleDir(specifier, file, scanRoot, adminSrc);

            if (resolved.dir) {
                reference.resolvedDir = resolved.dir;
            } else if (resolved.outsideRoot) {
                diagnostics.push({
                    file,
                    stage: 'registration',
                    code: 'registration-path-outside-root',
                    message: `registration import '${specifier}' resolves outside the scan root`,
                    range: reference.range,
                });
            }
        }

        registrations.push(reference);
    });

    return { registrations, diagnostics };
}

function analyzeSourceFile(
    parsed: ParsedSourceFile,
    scanRoot: string,
    adminSrc: string,
): { component: ComponentSource | null; registrations: RegistrationReference[]; diagnostics: SourceDiagnostic[] } {
    if (!parsed.ast) {
        return { component: null, registrations: [], diagnostics: [] };
    }

    const extracted = extractRegistrations(parsed.ast, parsed.file, scanRoot, adminSrc);
    const exportDefault = findDefaultExport(parsed.ast);

    if (!exportDefault) {
        return {
            component: null,
            registrations: extracted.registrations,
            diagnostics: extracted.diagnostics,
        };
    }

    const options = unwrapOptions(exportDefault.declaration);

    const template = findTemplateBinding(parsed.file, options, parsed.ast);
    const diagnostics = [
        ...extracted.diagnostics,
        ...template.diagnostics,
    ];

    return {
        component: {
            file: parsed.file,
            parsed,
            exportDefault,
            options,
            template: template.binding,
            registrations: extracted.registrations,
            diagnostics,
        },
        registrations: extracted.registrations,
        diagnostics,
    };
}

function collectComponentSourceIndex(scanRoot: string, options: ComponentSourceIndexOptions = {}): ComponentSourceIndex {
    const absoluteScanRoot = path.resolve(scanRoot);
    const adminSrc = path.resolve(options.adminSrc ?? DEFAULT_ADMIN_SRC);
    const readFile = options.readFile ?? ((file: string): string => fs.readFileSync(file, 'utf8'));
    const files = new Map<string, ParsedSourceFile>();
    const components = new Map<string, ComponentSource>();
    const registrationsByFile = new Map<string, RegistrationReference[]>();
    const registrationsByDir = new Map<string, RegistrationReference[]>();
    const diagnostics: SourceDiagnostic[] = [];
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
        let source = '';
        let ast: t.File | null = null;
        const fileDiagnostics: SourceDiagnostic[] = [];

        try {
            source = readFile(file);
        } catch (error) {
            fileDiagnostics.push({
                file,
                stage: 'scan',
                code: 'read-failed',
                message: `could not read source: ${error instanceof Error ? error.message : String(error)}`,
            });
        }

        if (fileDiagnostics.length === 0) {
            try {
                ast = parse(source, { sourceType: 'module', plugins: SOURCE_PLUGINS });
            } catch (error) {
                fileDiagnostics.push({
                    file,
                    stage: 'scan',
                    code: 'parse-failed',
                    message: `could not parse source: ${error instanceof Error ? error.message : String(error)}`,
                });
            }
        }

        const parsed: ParsedSourceFile = {
            file,
            source,
            ast,
            diagnostics: fileDiagnostics,
        };

        files.set(file, parsed);
        diagnostics.push(...parsed.diagnostics);

        const analyzed = analyzeSourceFile(parsed, absoluteScanRoot, adminSrc);
        const analyzedDiagnostics = analyzed.diagnostics;

        if (analyzed.component) {
            analyzed.component.diagnostics = analyzedDiagnostics;
        }

        diagnostics.push(...analyzedDiagnostics);
        registrationsByFile.set(file, analyzed.registrations);

        if (analyzed.component) {
            components.set(file, analyzed.component);
        }

        for (const registration of analyzed.registrations) {
            if (!registration.resolvedDir) {
                continue;
            }

            const registrations = registrationsByDir.get(registration.resolvedDir) ?? [];

            registrations.push(registration);
            registrationsByDir.set(registration.resolvedDir, registrations);
        }
    }

    for (const [
        dir,
        registrations,
    ] of registrationsByDir) {
        if (registrations.length > 1) {
            diagnostics.push({
                file: registrations[0].file,
                stage: 'registration',
                code: 'registration-ambiguous',
                message: `multiple registrations resolve to '${dir}'`,
                range: registrations[0].range,
            });
        }
    }

    return { files, components, registrationsByFile, registrationsByDir, diagnostics };
}

export {
    collectComponentSourceIndex,
    isContained,
    type ComponentSource,
    type ComponentSourceIndex,
    type ComponentSourceIndexOptions,
    type ParsedSourceFile,
    type RegistrationKind,
    type RegistrationReference,
    type SourceDiagnostic,
    type SourceRange,
    type TemplateBinding,
};
