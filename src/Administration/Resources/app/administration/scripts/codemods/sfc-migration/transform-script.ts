/**
 * @sw-package framework
 */

/**
 * Converts an Options API component script into a `<script setup>` body ending in `swDefinePublic`.
 *
 * Three phases, in this order: collect descriptors, rewrite every component-bound `this.*` in the
 * MagicString, render from the rewritten slices — a slice taken before the rewrite still carries
 * `this.`. The section order is TDZ-driven: eager consumers (data initializers, watchers, the inlined
 * created() body) come after everything they may reference.
 */

import { traverse, type NodePath } from '@babel/core';
import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import MagicString from 'magic-string';
import { isReservedBindingName } from '../../../build/vue-setup-transform/naming';
import { GENERATED_HELPER_NAMES, HELPER_SETUP_LINES, type ReportKind, type TodoEntry } from './tables';
import { type Ctx, arrowText, errorText, findExportDefault, packageName, report, snip, unwrapOptions } from './ast';
import { type PreludeSplit, type PreludeStatement, splitPrelude } from './module-prelude';
import {
    type Collected,
    type CollectedWatcher,
    type ResolvedComposable,
    classifyOptions,
    collectWatchers,
    emitsEventNames,
    renderMember,
    renderWatcher,
    resolveMixins,
} from './option-handlers';
import { rewriteMemberFn, rewriteThis } from './rewrite-this';

type ScriptResult = {
    script: string | null;
    moduleScript: string | null;
    /** The blockers that refused the script when it is null, otherwise the TODOs the draft carries. */
    reasons: string[];
};

function todoBlock(entry: TodoEntry): string {
    const lines = [
        `// TODO(sfc-migration)${entry.mode ? ` ${entry.mode}` : ''}: ${entry.reason}`,
        ...(entry.explanation ? [`// ${entry.explanation}`] : []),
    ];

    if (entry.checks) {
        return [...lines, ...entry.checks.map((check) => `// - ${check}`)].join('\n');
    }

    if (!entry.code) {
        return lines.join('\n');
    }

    const codeLines = entry.code.split('\n').map((line) => `// ${line}`);

    return [...lines.slice(0, -1), `${lines[lines.length - 1]} — original code:`, ...codeLines].join('\n');
}

function eventList(events: string[]): string {
    return `[${events.map((event) => `'${event}'`).join(', ')}]`;
}

/**
 * A mixin's events are merged into the component's list, because its composable emits them through
 * the callbacks handed to it. Otherwise `emits` is spliced verbatim, validators included.
 */
function emitsArgument(ctx: Ctx, collected: Collected, mixinEvents: string[], usesEmit: boolean): string | null {
    if (mixinEvents.length > 0) {
        // resolveMixins refused every `emits` option that is not a plain list.
        const declared = collected.emitsNode ? (emitsEventNames(collected.emitsNode) as string[]) : ctx.inferredEmits;

        return eventList([...new Set([...declared, ...mixinEvents])]);
    }

    if (collected.emitsNode) {
        return snip(ctx, collected.emitsNode);
    }

    return ctx.inferredEmits.length > 0 || usesEmit ? eventList(ctx.inferredEmits) : null;
}

/** `defineProps` is a compiler macro, so the mixins' props have to be merged into one literal. */
function propsArgument(ctx: Ctx, collected: Collected, usesProps: boolean): string | null {
    const provided = collected.providedProps.map(({ name, definition }) => `${name}: ${definition},`);
    const ownText = collected.propsNode ? snip(ctx, collected.propsNode) : null;

    if (provided.length === 0) {
        return ownText ?? (usesProps ? '{}' : null);
    }

    const ownEntries = ownText ? ownText.trim().slice(1, -1).trim() : '';
    const separator = ownEntries === '' || ownEntries.endsWith(',') ? '' : ',';

    return `{\n${ownEntries}${separator}\n${provided.join('\n')}\n}`;
}

/** Every `snip()` below reads rewritten text, so this runs after the rewrite pass. */
function renderScript(
    ctx: Ctx,
    collected: Collected,
    watchers: CollectedWatcher[],
    composables: ResolvedComposable[],
    prelude: Pick<PreludeSplit, 'declarations' | 'namedImports'> = { declarations: '', namedImports: new Set() },
): string {
    const usesEmit = ctx.helpers.has('emit');
    const usesProps = ctx.helpers.has('props');
    const vueImports = [
        ...(collected.dataEntries.length > 0 || ctx.templateRefs.size > 0 ? ['ref'] : []),
        ...(collected.computeds.length > 0 ? ['computed'] : []),
        ...(watchers.length > 0 ? ['watch'] : []),
        ...(collected.injects.length > 0 ? ['inject'] : []),
        ...(ctx.helpers.has('nextTick') ? ['nextTick'] : []),
        ...(ctx.helpers.has('slots') ? ['useSlots'] : []),
        ...(ctx.helpers.has('attrs') ? ['useAttrs'] : []),
        ...[...new Set(collected.hooks.map((hook) => hook.hook))],
    ];
    const routerImports = [
        ...(ctx.helpers.has('router') ? ['useRouter'] : []),
        ...(ctx.helpers.has('route') ? ['useRoute'] : []),
    ];

    const mixinEvents = [...new Set(composables.flatMap(({ descriptor }) => Object.values(descriptor.emits ?? {})))];
    const emitsText = emitsArgument(ctx, collected, mixinEvents, usesEmit);
    const propsText = propsArgument(ctx, collected, usesProps);

    // An author import of the same name from the same module already provides the binding.
    const importLine = (names: string[], source: string): string | null => {
        const missing = names.filter((name) => !prelude.namedImports.has(`${source}:${name}`));

        return missing.length > 0 ? `import { ${missing.join(', ')} } from '${source}';` : null;
    };
    const importBlock = [
        importLine(vueImports, 'vue'),
        importLine(ctx.helpers.has('t') ? ['useI18n'] : [], 'vue-i18n'),
        importLine(routerImports, 'vue-router'),
        ...composables.map(({ descriptor }) => `import ${descriptor.import.name} from '${descriptor.import.source}';`),
        prelude.declarations,
    ]
        .filter(Boolean)
        .join('\n');
    const helperBlock = (
        [
            't',
            'router',
            'route',
            'slots',
            'attrs',
        ] as const
    )
        .filter((helper) => ctx.helpers.has(helper))
        .map((helper) => HELPER_SETUP_LINES[helper])
        .join('\n');
    const injectBlock = collected.injects.map((injectName) => `const ${injectName} = inject('${injectName}');`).join('\n');
    const composableBlock = composables
        .map(({ descriptor, entries, args, config }) => {
            const callArgs = [...args, ...config.map((entry) => `${entry.key}: ${snip(ctx, entry.valueNode)}`)];
            const call = `${descriptor.import.name}(${callArgs.length > 0 ? `{ ${callArgs.join(', ')} }` : ''});`;
            const destructured = entries
                .map((entry) =>
                    entry.binding === entry.sourceKey ? entry.sourceKey : `${entry.sourceKey}: ${entry.binding}`,
                )
                .join(', ');

            // A scaffold runs a lifecycle, so its call stands alone when nothing is read from it.
            const declaration = entries.length > 0 ? `const { ${destructured} } = ${call}` : call;

            return [
                ...entries.flatMap((entry) => (entry.renameTodo ? [todoBlock(entry.renameTodo)] : [])),
                declaration,
            ].join('\n');
        })
        .join('\n');
    const dataBlock = collected.dataEntries
        .map((entry) => `const ${entry.name} = ref(${snip(ctx, entry.valueNode)});`)
        .join('\n');
    const refBlock = [...ctx.templateRefs].map((refName) => `const ${refName} = ref(null);`).join('\n');

    const publicNames = [
        ...collected.injects,
        // Mixin members stay public, except a renamed one: exposing it would publish the generated name.
        ...composables.flatMap(({ entries }) =>
            entries.filter((entry) => entry.binding === entry.member).map((entry) => entry.binding),
        ),
        ...collected.dataEntries.map((entry) => entry.name),
        ...ctx.templateRefs,
        ...collected.computeds.map((computedEntry) => computedEntry.name),
        ...collected.methods.map((method) => method.name),
    ];

    // A review TODO is about the whole draft, so it leads the file; an anchored one is already placed.
    const fileTodos = ctx.reports.filter((entry) => entry.kind === 'todo' && !entry.anchored);
    const reviewTodos = fileTodos.filter((entry) => entry.checks !== undefined);
    const siteTodos = fileTodos.filter((entry) => entry.checks === undefined);

    const sections: (string | null)[] = [
        ...reviewTodos.map(todoBlock),
        importBlock || null,
        helperBlock || null,
        injectBlock || null,
        collected.inheritAttrs !== null ? `defineOptions({ inheritAttrs: ${collected.inheritAttrs} });` : null,
        propsText !== null
            ? usesProps
                ? `const props = defineProps(${propsText});`
                : `defineProps(${propsText});`
            : null,
        emitsText !== null
            ? usesEmit
                ? `const emit = defineEmits(${emitsText});`
                : `defineEmits(${emitsText});`
            : null,
        // After the macros it may be handed, before the members that read what it returns.
        composableBlock || null,
        ...collected.methods.map((method) => renderMember(ctx, method)),
        ...collected.computeds.map((computedEntry) => renderMember(ctx, computedEntry)),
        dataBlock || null,
        refBlock || null,
        ...watchers.map((watcher) => renderWatcher(ctx, watcher)),
        ...collected.hooks.map(({ hook, fn }) => `${hook}(${arrowText(ctx, fn)});`),
        collected.createdFn ? `void (${arrowText(ctx, collected.createdFn)})();` : null,
        ...siteTodos.map(todoBlock),
        publicNames.length > 0
            ? `swDefinePublic({\n${publicNames.map((publicName) => `${publicName},`).join('\n')}\n});`
            : 'swDefinePublic({});',
    ];

    return sections.filter((section): section is string => Boolean(section)).join('\n\n');
}

function isWithin(node: t.Node, container: t.Node): boolean {
    return (node.start as number) >= (container.start as number) && (node.end as number) <= (container.end as number);
}

/**
 * Names the options reach outside themselves (module bindings, globals), which a setup binding of
 * the same name would take over. Also fills `ctx.paths` for the rewrite pass.
 */
function collectOuterReferences(ctx: Ctx, ast: t.File, exportDefault: t.Node): Set<string> {
    const names = new Set<string>();

    traverse(ast, {
        enter(path) {
            ctx.paths.set(path.node, path);

            if (!path.isReferencedIdentifier() || path.node.type !== 'Identifier' || !isWithin(path.node, exportDefault)) {
                return;
            }

            const binding = path.scope.getBinding(path.node.name);

            if (!binding || binding.scope.block.type === 'Program') {
                names.add(path.node.name);
            }
        },
    });

    return names;
}

type ModuleBindings = NodePath['scope']['bindings'];

/** An import from the sibling module is read-only, so the component must not assign to its source. */
function reassignedModuleBindings(moduleBindings: ModuleBindings, exportDefault: t.Node): string[] {
    return Object.entries(moduleBindings)
        .filter(([, binding]) => binding.kind !== 'module')
        .filter(([, binding]) => binding.constantViolations.some((violation) => isWithin(violation.node, exportDefault)))
        .map(([name]) => name);
}

const DISABLE_NEXT_LINE = /^\s*eslint-disable-next-line\b/;

/**
 * The statements around the options in source order, each with the comments in front of it. The
 * `@sw-package` docblock and the comments no statement claims (those above the Twig import and the
 * options) are returned as the header instead — minus lint directives for the removed export.
 */
function preludeStatements(
    ast: t.File,
    source: string,
    exportDefault: t.Statement,
    templateImportStart: number,
): { statements: PreludeStatement[]; header: string[] } {
    const statements: PreludeStatement[] = [];
    const header: string[] = [];
    let cursor = 0;

    const leadingText = (end: number, dropDirectives = false): string => {
        let text = source.slice(cursor, end);

        for (const comment of ast.comments ?? []) {
            const commentText = source.slice(comment.start as number, comment.end as number);

            if ((comment.start as number) < cursor || (comment.end as number) > end) {
                continue;
            }

            if (packageName(comment.value)) {
                header.push(commentText);
                text = text.replace(commentText, '');
            } else if (dropDirectives && DISABLE_NEXT_LINE.test(comment.value)) {
                text = text.replace(commentText, '');
            }
        }

        return text;
    };

    for (const node of ast.program.body) {
        const leading = leadingText(node.start as number, node === exportDefault);

        cursor = node.end as number;

        if (node === exportDefault || node.start === templateImportStart) {
            header.push(leading);
        } else {
            statements.push({ node, leading });
        }
    }

    header.push(leadingText(source.length));

    return { statements, header: header.map((part) => part.trim()).filter(Boolean) };
}

function transformScript(
    source: string,
    componentName: string,
    transformOptions: {
        templateImportRange: { start: number; end: number };
        templateIdentifiers: ReadonlySet<string>;
        templateComponentTags: ReadonlySet<string>;
        moduleSpecifier: string;
    },
): ScriptResult {
    const ctx: Ctx = {
        source,
        ms: new MagicString(source),
        paths: new Map(),
        componentName,
        bindings: new Map(),
        renamedBindings: new Map(),
        templateIdentifiers: transformOptions.templateIdentifiers,
        templateComponentTags: transformOptions.templateComponentTags,
        templateRefs: new Set(),
        helpers: new Set(),
        inferredEmits: [],
        reports: [],
    };
    const refused = (reasons: string[]): ScriptResult => ({ script: null, moduleScript: null, reasons });
    const reasonsOf = (kind: ReportKind): string[] =>
        ctx.reports.filter((entry) => entry.kind === kind).map((entry) => entry.reason);

    let ast: t.File;

    try {
        ast = parse(source, { sourceType: 'module', plugins: ['typescript'] });
    } catch (error) {
        return refused([`script parse error: ${errorText(error)}`]);
    }

    const exportDefault = findExportDefault(ast.program);

    if (!exportDefault) {
        return refused(['no default export']);
    }

    const options = unwrapOptions(exportDefault.declaration);

    if (!options) {
        return refused(['unsupported default export shape']);
    }

    const outerReferences = collectOuterReferences(ctx, ast, exportDefault);
    // The SFC imports the module bindings the setup body reads, so a generated binding must not take one.
    const moduleBindings: ModuleBindings = ctx.paths.get(ast.program)?.scope.bindings ?? {};

    const collected = classifyOptions(ctx, options);
    const composables = resolveMixins(
        ctx,
        collected,
        options,
        new Set([...Object.keys(moduleBindings), ...outerReferences]),
    );
    const watchers = collectWatchers(ctx, collected);

    const setupBindingNames = [
        ...collected.injects,
        ...collected.dataEntries.map((entry) => entry.name),
        ...collected.computeds.map((computedEntry) => computedEntry.name),
        ...collected.methods.map((method) => method.name),
    ];

    for (const bindingName of setupBindingNames) {
        if (isReservedBindingName(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' uses a reserved name`);
        }

        if (GENERATED_HELPER_NAMES.has(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' collides with a generated helper`);
        }

        // The runtime strips declared prop keys from the setup state, so it would render `undefined`.
        if (collected.propNames.has(bindingName)) {
            report(ctx, 'skip', `'${bindingName}' is declared as both a prop and a component member`);
        }
    }

    // A template resolves a tag against setup bindings first; props are setup bindings too.
    for (const bindingName of [...setupBindingNames, ...collected.propNames]) {
        if (ctx.templateComponentTags.has(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' shadows a component tag the template renders`);
        }
    }

    for (const name of reassignedModuleBindings(moduleBindings, exportDefault)) {
        report(
            ctx,
            'skip',
            `module-level '${name}' is reassigned by the component, but the sibling module exports it read-only`,
        );
    }

    if (ctx.reports.some((entry) => entry.kind === 'skip')) {
        return refused(reasonsOf('skip'));
    }

    if (collected.createdFn) {
        collected.rewriteFns.push(collected.createdFn);
    }

    for (const { fn } of collected.hooks) {
        collected.rewriteFns.push(fn);
    }

    for (const fn of collected.rewriteFns) {
        rewriteMemberFn(ctx, fn);
    }

    // Spliced in at the top level, so no function frame encloses them.
    for (const entry of collected.dataEntries) {
        rewriteThis(ctx, entry.valueNode, true);
    }

    for (const composable of composables) {
        for (const entry of composable.config) {
            rewriteThis(ctx, entry.valueNode, true);
        }
    }

    for (const node of collected.foreignNodes) {
        rewriteThis(ctx, node, false);
    }

    // Template refs and helpers are only known after the rewrite. A ref cannot be renamed: the
    // template's `ref` attribute names it.
    for (const refName of ctx.templateRefs) {
        if (ctx.templateComponentTags.has(refName)) {
            report(ctx, 'skip', `template ref '${refName}' shadows a component tag the template renders`);
        }
    }

    for (const name of [...setupBindingNames, ...ctx.templateRefs, ...ctx.helpers]) {
        if (outerReferences.has(name)) {
            report(ctx, 'skip', `binding '${name}' would shadow the module-level or global '${name}' the component reads`);
        }
    }

    if (ctx.reports.some((entry) => entry.kind === 'skip')) {
        return refused(reasonsOf('skip'));
    }

    let prelude: PreludeSplit;

    // The split reads which module bindings the setup body references, so it renders twice.
    try {
        prelude = splitPrelude({
            source,
            ...preludeStatements(ast, source, exportDefault, transformOptions.templateImportRange.start),
            setupScript: renderScript(ctx, collected, watchers, composables),
            moduleSpecifier: transformOptions.moduleSpecifier,
        });
    } catch (error) {
        return refused([`generated script does not parse: ${errorText(error)}`]);
    }

    return {
        script: [prelude.header, renderScript(ctx, collected, watchers, composables, prelude)].filter(Boolean).join('\n\n'),
        moduleScript: prelude.moduleScript,
        reasons: reasonsOf('todo'),
    };
}

export { transformScript, type ScriptResult };
