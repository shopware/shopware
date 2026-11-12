/**
 * @sw-package framework
 *
 * Finds the `Component.register`, `Component.extend` and `Component.override` calls and the files behind them.
 */
import ts from 'typescript';
import { BOTH_STATES, ConditionChain, conditionTruths, inBothStates } from './flag-states';
import type { FlagContext, FlagStates } from './flag-states';
import type { SourceTree } from './source-tree';

const REGISTRATION_KINDS = [
    'register',
    'extend',
    'override',
] as const;

/** @private */
export type RegistrationKind = (typeof REGISTRATION_KINDS)[number];

/** @private */
export type ComponentLanguage = 'js' | 'ts' | 'vue';

/**
 * @private
 *
 * The files of a component. `source` is the file the registration imports, `sfc` the `.vue` file that it only
 * re-exports, and `template` its Twig template. `markup` is the one of them that holds the blocks, if any.
 */
export interface ComponentSource {
    source: string;
    sfc: string | null;
    template: string | null;
    markup: string | null;
    language: ComponentLanguage;
    privateExport: boolean;
}

/** @private */
export interface Registration extends ComponentSource {
    kind: RegistrationKind;
    name: string;
    extendsName: string | null;
    registeredIn: string;
    privateRegistration: boolean;
    states: FlagStates;
}

const REGISTRATION_CALL = new RegExp(`\\bComponent\\s*\\.\\s*(?:${REGISTRATION_KINDS.join('|')})\\s*\\(`);

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

/**
 * Only the comment directly above a statement counts, as in the ESLint rule `private-feature-declarations`.
 * The file header of a registration function says nothing about a single component.
 */
function hasPrivateTag(statement: ts.Node, source: ts.SourceFile): boolean {
    const comment = ts.getLeadingCommentRanges(source.text, statement.getFullStart())?.at(-1);

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

function componentSource(file: string, tree: SourceTree): ComponentSource {
    if (file.endsWith('.vue')) {
        return { source: file, sfc: null, template: null, markup: file, language: 'vue', privateExport: false };
    }

    const source = parseScript(file, tree);
    const shim = reExportedSfc(source);

    if (shim) {
        const sfc = resolveModule((shim.moduleSpecifier as ts.StringLiteral).text, file, tree);

        return {
            source: file,
            sfc,
            template: null,
            markup: sfc,
            language: 'vue',
            privateExport: hasPrivateTag(shim, source),
        };
    }

    const defaultExport = source.statements.find(ts.isExportAssignment);

    const template = importedFile(source, '.html.twig', tree);

    return {
        source: file,
        sfc: null,
        template,
        markup: template,
        language: file.endsWith('.ts') ? 'ts' : 'js',
        privateExport: defaultExport !== undefined && hasPrivateTag(defaultExport, source),
    };
}

function lazyImport(config: ts.Node): string | undefined {
    if (ts.isCallExpression(config) && config.expression.kind === ts.SyntaxKind.ImportKeyword) {
        const [specifier] = config.arguments;

        return ts.isStringLiteralLike(specifier) ? specifier.text : undefined;
    }

    return ts.forEachChild(config, lazyImport);
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

interface RegistrationCall {
    kind: RegistrationKind;
    call: ts.CallExpression;
}

function registrationOf(
    { kind, call }: RegistrationCall,
    source: ts.SourceFile,
    tree: SourceTree,
    flags: FlagContext,
): Registration {
    const where = `${source.fileName}: Component.${kind}`;
    const [name, ...rest] = call.arguments;
    const config = rest.at(-1);

    if (!name || !ts.isStringLiteralLike(name) || !config) {
        throw new Error(`${where} needs a literal component name and a component, so the snapshot can name it.`);
    }

    const extendsName = kind === 'extend' ? rest[0] : undefined;

    if (kind === 'extend' && (!extendsName || !ts.isStringLiteralLike(extendsName))) {
        throw new Error(`${where}('${name.text}') needs the name of the extended component as a literal.`);
    }

    const specifier = lazyImport(config);

    if (specifier === undefined && !ts.isObjectLiteralExpression(config)) {
        throw new Error(`${where}('${name.text}') has to pass an object literal or () => import('...').`);
    }

    return {
        ...componentSource(
            specifier === undefined ? source.fileName : resolveModule(specifier, source.fileName, tree),
            tree,
        ),
        kind,
        name: name.text,
        extendsName: extendsName && ts.isStringLiteralLike(extendsName) ? extendsName.text : null,
        registeredIn: source.fileName,
        privateRegistration: hasPrivateTag(statementOf(call), source),
        states: guardStates(call, flags),
    };
}

function registrationKind(node: ts.Node): RegistrationKind | undefined {
    if (!ts.isCallExpression(node) || !ts.isPropertyAccessExpression(node.expression)) {
        return undefined;
    }

    const { name, expression } = node.expression;

    return COMPONENT_FACTORY.test(expression.getText()) ? REGISTRATION_KINDS.find((kind) => kind === name.text) : undefined;
}

function registrationCalls(node: ts.Node): RegistrationCall[] {
    const kind = registrationKind(node);
    const calls: RegistrationCall[] = kind && ts.isCallExpression(node) ? [{ kind, call: node }] : [];

    ts.forEachChild(node, (child) => {
        calls.push(...registrationCalls(child));
    });

    return calls;
}

/**
 * @private
 *
 * Every component registration of the tree, in the order of the files and of the calls in them.
 */
export function componentRegistrations(tree: SourceTree, flags: FlagContext): Registration[] {
    return tree.files
        .filter((file) => SCRIPT_FILE.test(file) && REGISTRATION_CALL.test(tree.read(file)))
        .flatMap((file) => {
            const source = parseScript(file, tree);

            return registrationCalls(source).map((call) => registrationOf(call, source, tree, flags));
        });
}
