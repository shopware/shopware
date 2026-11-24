/**
 * @sw-package framework
 *
 * Finds the `Component.register`, `Component.extend` and `Component.override` calls and the files behind them.
 */
import ts from 'typescript';
import { isEslintDirective } from '../../../eslint-rules/deprecation-rules/utils/eslint-directive';
import { BOTH_STATES, inBothStates } from '../model/flag-states';
import type { FlagContext, FlagStates } from '../model/flag-states';
import type { Registration } from '../model/model';
import type { SourceTree } from '../source/source-tree';
import { ConditionChain, conditionTruths } from './flag-conditions';

const CALL_KINDS = [
    'register',
    'extend',
    'override',
] as const satisfies readonly (Registration['kind'] | 'override')[];

type CallKind = (typeof CALL_KINDS)[number];

/**
 * @private
 *
 * One `Component.override` call of core. The runtime applies the overrides of a component by `index`, and
 * those with the same index in the order they are registered. The generator takes those in file order.
 */
export interface Override {
    target: string;
    index: number;
    registeredIn: string;
    template: string | null;
    states: FlagStates;
}

/** @private */
export interface ComponentCalls {
    registrations: Registration[];
    overrides: Override[];
}

type ComponentFiles = Pick<Registration, 'source' | 'sfc' | 'template' | 'language'> & { privateExport: boolean };

// Only saves parsing the files without a registration. The syntax tree decides what counts as one.
const COMPONENT_CALL = new RegExp(`\\bComponent\\s*\\.\\s*(?:${CALL_KINDS.join('|')})\\s*\\(`);

const COMPONENT_FACTORY = /(?:^|\.)Component$/;

const SCRIPT_FILE = /\.(?:js|ts)$/;

const PRIVATE_TAG = /^[\s/*]*@private\b/m;

const MODULE_SUFFIXES = [
    '',
    '.ts',
    '.js',
    '/index.ts',
    '/index.js',
];

function parseScript(file: string, tree: SourceTree): ts.SourceFile {
    return ts.createSourceFile(file, tree.read(file), ts.ScriptTarget.Latest, true);
}

function directory(file: string): string {
    return file.slice(0, file.lastIndexOf('/'));
}

function resolveModule(specifier: string, importer: string, tree: SourceTree): string {
    const base = specifier.startsWith('src/')
        ? specifier
        : new URL(specifier, `file:///${directory(importer)}/`).pathname.slice(1);
    const resolved = MODULE_SUFFIXES.map((suffix) => `${base}${suffix}`).find((file) => tree.has(file));

    if (resolved === undefined) {
        throw new Error(`${importer}: the import '${specifier}' matches no source file.`);
    }

    return resolved;
}

function commentBody({ pos, end, kind }: ts.CommentRange, source: ts.SourceFile): string {
    const closerLength = kind === ts.SyntaxKind.MultiLineCommentTrivia ? 2 : 0;

    return source.text.slice(pos + 2, end - closerLength);
}

/**
 * Only the comment directly above a statement counts, as in the ESLint rule `private-feature-declarations`.
 * The file header of a registration function says nothing about a single component.
 * ESLint directives between that comment and the statement are skipped, as the rule skips them.
 */
function hasPrivateTag(statement: ts.Node, source: ts.SourceFile): boolean {
    const comment = (ts.getLeadingCommentRanges(source.text, statement.getFullStart()) ?? [])
        .filter((range) => !isEslintDirective(commentBody(range, source)))
        .at(-1);

    return comment !== undefined && PRIVATE_TAG.test(source.text.slice(comment.pos, comment.end));
}

function importedFile(source: ts.SourceFile, suffix: string, tree: SourceTree): string | null {
    const specifiers = source.statements
        .filter(ts.isImportDeclaration)
        .map((statement) => statement.moduleSpecifier)
        .filter(ts.isStringLiteral)
        .map(({ text }) => text)
        .filter((specifier) => specifier.endsWith(suffix));

    if (specifiers.length > 1) {
        throw new Error(`${source.fileName}: more than one ${suffix} import, so the template of the component is unclear.`);
    }

    return specifiers.length === 1 ? resolveModule(specifiers[0], source.fileName, tree) : null;
}

/** The `.vue` file of `export { default } from './sw-example.vue'`, the shim the SFC migration writes. */
function reExportedSfc(source: ts.SourceFile): ts.ExportDeclaration | undefined {
    return source.statements
        .filter(ts.isExportDeclaration)
        .find(
            ({ moduleSpecifier }) =>
                moduleSpecifier && ts.isStringLiteral(moduleSpecifier) && moduleSpecifier.text.endsWith('.vue'),
        );
}

function componentFiles(file: string, tree: SourceTree): ComponentFiles {
    if (file.endsWith('.vue')) {
        return { source: file, sfc: null, template: null, language: 'vue', privateExport: false };
    }

    const source = parseScript(file, tree);
    const shim = reExportedSfc(source);

    if (shim) {
        return {
            source: file,
            sfc: resolveModule((shim.moduleSpecifier as ts.StringLiteral).text, file, tree),
            template: null,
            language: 'vue',
            privateExport: hasPrivateTag(shim, source),
        };
    }

    const defaultExport = source.statements.find(ts.isExportAssignment);

    return {
        source: file,
        sfc: null,
        template: importedFile(source, '.html.twig', tree),
        language: file.endsWith('.ts') ? 'ts' : 'js',
        privateExport: defaultExport !== undefined && hasPrivateTag(defaultExport, source),
    };
}

/**
 * @private
 *
 * The file that holds the blocks of a component, if any.
 */
export function markupOf({ language, source, sfc, template }: Registration): string | null {
    return sfc ?? template ?? (language === 'vue' ? source : null);
}

function literalText(node: ts.Node | undefined): string | undefined {
    return node && ts.isStringLiteralLike(node) ? node.text : undefined;
}

/** The module of `() => import('./sw-example')`, or of `import('./sw-example')` as older commits pass it. */
function lazyImport(config: ts.Expression): string | undefined {
    const call = ts.isArrowFunction(config) ? config.body : config;

    return ts.isCallExpression(call) && call.expression.kind === ts.SyntaxKind.ImportKeyword
        ? literalText(call.arguments[0])
        : undefined;
}

function visibilityOf(
    privateExport: boolean,
    privateRegistration: boolean,
): Pick<Registration, 'visibility' | 'visibilitySource'> {
    if (privateExport) {
        return { visibility: 'private', visibilitySource: 'export' };
    }

    return privateRegistration
        ? { visibility: 'private', visibilitySource: 'registration' }
        : { visibility: 'public', visibilitySource: 'default' };
}

/** The flag states in which the `if` statements around `node` let it run. */
function guardStates(node: ts.Node, flags: FlagContext): FlagStates {
    let states = BOTH_STATES;

    for (let child = node; child.parent !== undefined; child = child.parent) {
        const { parent } = child;

        if (ts.isIfStatement(parent) && child !== parent.expression) {
            const chain = new ConditionChain();
            const thenStates = chain.branch(conditionTruths(parent.expression, flags));

            states = inBothStates(states, child === parent.thenStatement ? thenStates : chain.elseBranch());
        }
    }

    return states;
}

function statementOf(node: ts.Node): ts.Node {
    return ts.isStatement(node) || node.parent === undefined ? node : statementOf(node.parent);
}

interface ComponentCall {
    kind: CallKind;
    call: ts.CallExpression;
}

const CALL_ARGUMENTS = {
    register: ['name', 'config'],
    extend: ['name', 'parent', 'config'],
    override: ['name', 'config', 'index'],
} as const;

type CallArguments = Partial<Record<(typeof CALL_ARGUMENTS)[CallKind][number], ts.Expression>>;

function callArguments({ kind, call }: ComponentCall): CallArguments {
    return Object.fromEntries(CALL_ARGUMENTS[kind].map((role, position) => [role, call.arguments[position]]));
}

interface NamedComponent {
    name: string;
    where: string;
    files: ComponentFiles;
}

function namedComponent(componentCall: ComponentCall, source: ts.SourceFile, tree: SourceTree): NamedComponent {
    const where = `${source.fileName}: Component.${componentCall.kind}`;
    const { name: nameArgument, config } = callArguments(componentCall);
    const name = literalText(nameArgument);

    if (name === undefined || !config) {
        throw new Error(`${where} needs a literal component name and a component, so the snapshot can name it.`);
    }

    const specifier = lazyImport(config);

    if (specifier === undefined && !ts.isObjectLiteralExpression(config)) {
        throw new Error(`${where}('${name}') has to pass an object literal or () => import('...').`);
    }

    return {
        name,
        where: `${where}('${name}')`,
        files: componentFiles(
            specifier === undefined ? source.fileName : resolveModule(specifier, source.fileName, tree),
            tree,
        ),
    };
}

function registrationOf(
    componentCall: ComponentCall & { kind: Registration['kind'] },
    source: ts.SourceFile,
    tree: SourceTree,
    flags: FlagContext,
): Registration {
    const { kind, call } = componentCall;
    const { name, where, files } = namedComponent(componentCall, source, tree);
    const parent = kind === 'extend' ? literalText(callArguments(componentCall).parent) : null;

    if (parent === undefined) {
        throw new Error(`${where} needs the name of the extended component as a literal.`);
    }

    const { privateExport, ...componentSource } = files;

    return {
        ...componentSource,
        ...visibilityOf(privateExport, hasPrivateTag(statementOf(call), source)),
        kind,
        name,
        extends: parent,
        registeredIn: source.fileName,
        states: guardStates(call, flags),
    };
}

function overrideIndex(index: ts.Expression | undefined, where: string): number {
    if (index === undefined || index.kind === ts.SyntaxKind.NullKeyword) {
        return 0;
    }

    const value = Number(index.getText());

    if (Number.isNaN(value)) {
        throw new Error(`${where} needs its index as a number, so the snapshot knows the order of the overrides.`);
    }

    return value;
}

function overrideOf(componentCall: ComponentCall, source: ts.SourceFile, tree: SourceTree, flags: FlagContext): Override {
    const { name, where, files } = namedComponent(componentCall, source, tree);

    return {
        target: name,
        index: overrideIndex(callArguments(componentCall).index, where),
        registeredIn: source.fileName,
        template: files.template,
        states: guardStates(componentCall.call, flags),
    };
}

function callKind(node: ts.Node): CallKind | undefined {
    if (!ts.isCallExpression(node) || !ts.isPropertyAccessExpression(node.expression)) {
        return undefined;
    }

    const { name, expression } = node.expression;

    return COMPONENT_FACTORY.test(expression.getText()) ? CALL_KINDS.find((kind) => kind === name.text) : undefined;
}

function componentCallsIn(node: ts.Node): ComponentCall[] {
    const kind = callKind(node);
    const calls: ComponentCall[] = kind && ts.isCallExpression(node) ? [{ kind, call: node }] : [];

    ts.forEachChild(node, (child) => {
        calls.push(...componentCallsIn(child));
    });

    return calls;
}

function isRegistration(call: ComponentCall): call is ComponentCall & { kind: Registration['kind'] } {
    return call.kind !== 'override';
}

/**
 * @private
 *
 * Every component registration and core override of the tree, in the order of the files and of the calls in them.
 */
export function componentCalls(tree: SourceTree, flags: FlagContext): ComponentCalls {
    const registrations: Registration[] = [];
    const overrides: Override[] = [];

    tree.files
        .filter((file) => SCRIPT_FILE.test(file) && COMPONENT_CALL.test(tree.read(file)))
        .forEach((file) => {
            const source = parseScript(file, tree);

            componentCallsIn(source).forEach((call) => {
                if (isRegistration(call)) {
                    registrations.push(registrationOf(call, source, tree, flags));
                } else {
                    overrides.push(overrideOf(call, source, tree, flags));
                }
            });
        });

    return { registrations, overrides };
}
