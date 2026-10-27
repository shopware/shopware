/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import * as ts from 'typescript';
import type { AnalysisContext } from '../analysis-context';
import type { ComponentEntry, ComponentLanguage, RegistrationKind, SnapshotDiagnostic } from '../types';
import { leadingComments, parseDeprecatedOption, parseDocInfo } from './doc-comments';
import {
    extractMembers,
    lineOf,
    optionValue,
    resolveExpression,
    symbolOf,
    type ComponentMembers,
    type MemberScope,
} from './members';
import { parseTemplate, type ParsedTemplate, type TemplateSource } from './twig';

export interface ComponentRef {
    name: string;
    registration: RegistrationKind;
    parent: string | null;
    /** Absolute */
    entryFile: string;
    /** Absolute; the template the source index bound to the entry, if any. */
    twigPath: string | null;
}

export interface OverrideRef {
    name: string;
    /** Absolute; the registering file for inline overrides, else the entry file. */
    file: string;
    /** Start offset of the inline `Component.override(...)` call. */
    inlineCallStart: number | null;
}

export interface OverrideContribution {
    file: string;
    members: ComponentMembers;
    template: ParsedTemplate | null;
}

interface ResolvedOptions {
    options: ts.ObjectLiteralExpression | null;
    instanceCall: ts.CallExpression | null;
    /** Nodes whose leading comments may hold the component docblock. */
    docNodes: ts.Node[];
}

const EMPTY_MEMBERS: ComponentMembers = {
    props: [],
    emits: [],
    data: [],
    computed: [],
    methods: [],
    inject: [],
    mixins: [],
};

function languageOf(file: string): ComponentLanguage {
    if (file.endsWith('.vue')) {
        return 'vue';
    }

    return file.endsWith('.ts') ? 'ts' : 'js';
}

function unwrapOptions(context: AnalysisContext, expression: ts.Expression): ResolvedOptions {
    const docNodes: ts.Node[] = [];
    let instanceCall: ts.CallExpression | null = null;
    let current: ts.Expression = expression;

    for (let depth = 0; depth < 10; depth += 1) {
        const resolved = resolveExpression(context, current);

        if (resolved !== current && resolved.parent) {
            docNodes.push(ts.isVariableDeclaration(resolved.parent) ? resolved.parent.parent.parent : resolved.parent);
        }

        current = resolved;

        if (ts.isObjectLiteralExpression(current)) {
            return { options: current, instanceCall, docNodes };
        }

        if (!ts.isCallExpression(current)) {
            break;
        }

        docNodes.push(current);
        instanceCall ??= /(?:wrapComponentConfig|defineComponent)$/.test(current.expression.getText()) ? current : null;

        const argument = current.arguments.find((candidate) => {
            const unwrapped = resolveExpression(context, candidate);

            return ts.isObjectLiteralExpression(unwrapped) || ts.isCallExpression(unwrapped);
        });

        if (!argument) {
            break;
        }

        current = argument;
    }

    return { options: null, instanceCall, docNodes };
}

function instanceTypeOf(context: AnalysisContext, call: ts.CallExpression | null): ts.Type | null {
    if (!call) {
        return null;
    }

    const [signature] = context.checker.getTypeAtLocation(call).getConstructSignatures();

    return signature ? context.checker.getReturnTypeOfSignature(signature) : null;
}

function readTemplateFile(context: AnalysisContext, file: string): TemplateSource | null {
    try {
        return { file: context.toRelativePath(file), text: fs.readFileSync(file, 'utf8'), lineOffset: 0 };
    } catch {
        return null;
    }
}

function importedFile(context: AnalysisContext, identifier: ts.Identifier): string | null {
    const declaration = symbolOf(context, identifier)?.declarations?.[0];
    const importDeclaration = declaration?.parent && ts.isImportClause(declaration) ? declaration.parent : null;

    if (!importDeclaration || !ts.isStringLiteral(importDeclaration.moduleSpecifier)) {
        return null;
    }

    const specifier = importDeclaration.moduleSpecifier.text.replace(/\?.*$/, '');

    return specifier.startsWith('.') ? path.resolve(path.dirname(identifier.getSourceFile().fileName), specifier) : null;
}

function resolveTemplate(
    scope: MemberScope,
    options: ts.ObjectLiteralExpression,
    twigPath: string | null,
): TemplateSource | null {
    const { context } = scope;

    if (twigPath) {
        return readTemplateFile(context, twigPath);
    }

    const value = optionValue(context, options, 'template');

    if (!value) {
        return null;
    }

    if (ts.isStringLiteralLike(value)) {
        return { file: scope.file, text: value.text, lineOffset: lineOf(value) - 1 };
    }

    const file = ts.isIdentifier(value) ? importedFile(context, value) : null;
    const template = file ? readTemplateFile(context, file) : null;

    if (!template) {
        scope.diagnostics.push({
            file: scope.file,
            message: `Cannot resolve the template '${value.getText()}' (line ${lineOf(value)})`,
        });
    }

    return template;
}

function createScope(context: AnalysisContext, sourceFile: ts.SourceFile, instanceType: ts.Type | null): MemberScope {
    return {
        context,
        sourceFile,
        file: context.toRelativePath(sourceFile.fileName),
        isTypeScript: languageOf(sourceFile.fileName) === 'ts',
        instanceType,
        diagnostics: [],
    };
}

function defaultExport(sourceFile: ts.SourceFile): ts.ExportAssignment | undefined {
    return sourceFile.statements.find(
        (statement): statement is ts.ExportAssignment => ts.isExportAssignment(statement) && !statement.isExportEquals,
    );
}

/** Pure per component: everything it reads comes from the context and the referenced files. */
export function extractComponent(
    context: AnalysisContext,
    ref: ComponentRef,
): { entry: ComponentEntry; diagnostics: SnapshotDiagnostic[] } {
    const sourceFile = context.program.getSourceFile(ref.entryFile);
    const file = context.toRelativePath(ref.entryFile);
    const entry: ComponentEntry = {
        name: ref.name,
        registration: ref.registration,
        overriddenBy: [],
        extends: ref.parent,
        packageName: null,
        language: languageOf(ref.entryFile),
        sourceFile: file,
        templateFile: null,
        ...EMPTY_MEMBERS,
        slots: [],
        blocks: [],
        visibility: 'public',
        deprecation: null,
        location: { file, line: 1 },
    };

    if (!sourceFile) {
        return { entry, diagnostics: [{ file, message: `${ref.name}: the entry file is not part of the program.` }] };
    }

    const exported = defaultExport(sourceFile);
    const resolved = exported ? unwrapOptions(context, exported.expression) : null;
    const scope = createScope(
        context,
        sourceFile,
        entry.language === 'ts' ? instanceTypeOf(context, resolved?.instanceCall ?? null) : null,
    );
    const docNodes = [
        sourceFile.statements[0],
        ...(exported ? [exported] : []),
        ...(resolved?.docNodes ?? []),
    ].filter((node): node is ts.Node => !!node);
    const comments = [...new Set(docNodes.flatMap((node) => leadingComments(node)))];
    const doc = parseDocInfo(comments);

    entry.visibility = doc.visibility;
    entry.packageName = doc.packageName;
    entry.deprecation = doc.deprecation;

    if (!exported || !resolved?.options) {
        scope.diagnostics.push({ file, message: `${ref.name}: cannot resolve the component options.` });
        return { entry, diagnostics: scope.diagnostics };
    }

    const { options } = resolved;
    const deprecatedOption = optionValue(context, options, 'deprecated');

    entry.location = { file, line: lineOf(exported) };
    entry.deprecation =
        (deprecatedOption && ts.isExpression(deprecatedOption) ? parseDeprecatedOption(deprecatedOption) : null) ??
        entry.deprecation;

    Object.assign(entry, extractMembers(scope, options));

    const templateSource = resolveTemplate(scope, options, ref.twigPath);

    if (templateSource) {
        const template = parseTemplate(templateSource);

        entry.templateFile = templateSource.file;
        entry.blocks = template.blocks;
        entry.slots = template.slots;
        entry.emits = mergeByName(entry.emits, template.emits);
        scope.diagnostics.push(...template.diagnostics);
    }

    return { entry, diagnostics: scope.diagnostics };
}

function findCallAt(sourceFile: ts.SourceFile, start: number): ts.CallExpression | null {
    let found: ts.CallExpression | null = null;
    const visit = (node: ts.Node): void => {
        if (found || node.getEnd() < start || node.getFullStart() > start) {
            return;
        }

        if (ts.isCallExpression(node) && node.getStart() === start) {
            found = node;
            return;
        }

        ts.forEachChild(node, visit);
    };

    visit(sourceFile);

    return found;
}

export function extractOverride(
    context: AnalysisContext,
    ref: OverrideRef,
): { contribution: OverrideContribution | null; diagnostics: SnapshotDiagnostic[] } {
    const file = context.toRelativePath(ref.file);
    const sourceFile = context.program.getSourceFile(ref.file);
    let options: ts.ObjectLiteralExpression | null = null;

    if (sourceFile && ref.inlineCallStart !== null) {
        const config = findCallAt(sourceFile, ref.inlineCallStart)?.arguments[1];
        const resolved = config ? resolveExpression(context, config) : null;
        options = resolved && ts.isObjectLiteralExpression(resolved) ? resolved : null;
    } else if (sourceFile) {
        const exported = defaultExport(sourceFile);
        options = exported ? unwrapOptions(context, exported.expression).options : null;
    }

    if (!sourceFile || !options) {
        return {
            contribution: null,
            diagnostics: [{ file, message: `Cannot resolve the options of the override of ${ref.name}.` }],
        };
    }

    const scope = createScope(context, sourceFile, null);
    const members = extractMembers(scope, options);
    const templateSource = resolveTemplate(scope, options, null);
    const template = templateSource ? parseTemplate(templateSource) : null;

    return {
        contribution: { file, members, template },
        diagnostics: [...scope.diagnostics, ...(template?.diagnostics ?? [])],
    };
}

export function mergeByName<T extends { name: string }>(own: T[], added: T[]): T[] {
    const names = new Set(own.map(({ name }) => name));

    return [...own, ...added.filter(({ name }) => !names.has(name))];
}
