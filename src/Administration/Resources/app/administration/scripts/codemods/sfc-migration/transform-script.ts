/**
 * @sw-package framework
 */

/**
 * Converts an Options API component script into a native setup `<script setup>` body ending in
 * `swDefinePublic({ ... })`.
 *
 * Strategy: parse once with @babel/parser for positions, then run three phases in order.
 *
 * 1. collect — `classifyOptions()` / `collectWatchers()` turn the options object into plain
 *    descriptors (option-handlers.ts). Nothing is rendered yet.
 * 2. rewrite — every component-bound `this.*` reference is rewritten in place in the MagicString
 *    (rewrite-this.ts).
 * 3. render — `renderScript()` reads the rewritten text back out and assembles the new script from
 *    verbatim source slices. It must not run earlier: a slice taken before the rewrite would still
 *    carry `this.`.
 *
 * Conversion policy lives in tables.ts (tier table, rewrite map) and option-handlers.ts (one handler
 * per supported option); indentation is left to prettier and correctness to the validation gate
 * (validate.ts). This module owns the orchestration and the section ordering of the assembled
 * script — the order is TDZ-driven: eager consumers (data initializers, watchers, the inlined
 * created() body) come after everything they may reference.
 */

import { traverse } from '@babel/core';
import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import { getBindingIdentifiers } from '@babel/types';
import MagicString from 'magic-string';
import { GENERATED_HELPER_NAMES, HELPER_SETUP_LINES, RESERVED_BINDING, type ReportKind, type TodoEntry } from './tables';
import { type Ctx, arrowText, report, snip, unwrapOptions } from './ast';
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
        return [
            ...lines,
            ...entry.checks.map((check) => `// - ${check}`),
        ].join('\n');
    }

    if (!entry.code) {
        return lines.join('\n');
    }

    const codeLines = entry.code.split('\n').map((line) => `// ${line}`);

    return [
        ...lines.slice(0, -1),
        `${lines[lines.length - 1]} — original code:`,
        ...codeLines,
    ].join('\n');
}

/**
 * Names the module body binds outside the component options. The module `<script>` block shares its
 * scope with `<script setup>`, so a generated binding of the same name would either be a duplicate
 * declaration or quietly take over the references meant for the prelude's.
 */
function collectTopLevelBindings(body: t.Statement[], exportDefault: t.Statement): Set<string> {
    const names = new Set<string>();

    for (const statement of body) {
        if (statement === exportDefault) {
            continue;
        }

        for (const name of Object.keys(getBindingIdentifiers(statement))) {
            names.add(name);
        }
    }

    return names;
}

function eventList(events: string[]): string {
    return `[${events.map((event) => `'${event}'`).join(', ')}]`;
}

/**
 * The `defineEmits` argument. A mixin's events have to be merged into the component's own list,
 * because its composable emits them through the callbacks the codemod hands it. With no mixin event in
 * play the `emits` option is spliced verbatim instead, which keeps the object form and its validators.
 */
function emitsArgument(ctx: Ctx, collected: Collected, mixinEvents: string[], usesEmit: boolean): string | null {
    if (mixinEvents.length > 0) {
        // resolveMixins refuses a component whose `emits` option is not a plain list, so a declaration
        // that reaches here always parses.
        const declared = collected.emitsNode ? (emitsEventNames(collected.emitsNode) as string[]) : ctx.inferredEmits;

        return eventList([
            ...new Set([
                ...declared,
                ...mixinEvents,
            ]),
        ]);
    }

    if (collected.emitsNode) {
        return snip(ctx, collected.emitsNode);
    }

    return ctx.inferredEmits.length > 0 || usesEmit ? eventList(ctx.inferredEmits) : null;
}

/**
 * The `defineProps` argument, with the props the mixins declared merged into the component's own
 * literal. `defineProps` is a compiler macro, so both have to end up in one literal — which is why
 * resolveMixins refuses a component whose `props` option is not one.
 */
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

/**
 * The render phase: collected descriptors plus the rewritten MagicString become the `<script setup>`
 * body. Every `snip()` below reads text the rewrite pass already touched, so this must run last.
 */
function renderScript(
    ctx: Ctx,
    collected: Collected,
    watchers: CollectedWatcher[],
    composables: ResolvedComposable[],
): string {
    const usesEmit = ctx.helpers.has('emit');
    const usesProps = ctx.helpers.has('props');
    const vueImports = [
        ...(collected.dataEntries.length > 0 || ctx.templateRefs.size > 0 ? ['ref'] : []),
        ...(collected.computeds.length > 0 ? ['computed'] : []),
        ...(watchers.length > 0 ? ['watch'] : []),
        ...(collected.injects.length > 0 ? ['inject'] : []),
        ...(collected.provided.length > 0 ? ['provide'] : []),
        ...(ctx.helpers.has('nextTick') ? ['nextTick'] : []),
        ...(ctx.helpers.has('slots') ? ['useSlots'] : []),
        ...(ctx.helpers.has('attrs') ? ['useAttrs'] : []),
        ...[...new Set(collected.hooks.map((hook) => hook.hook))],
    ];
    const routerImports = [
        ...(ctx.helpers.has('router') ? ['useRouter'] : []),
        ...(ctx.helpers.has('route') ? ['useRoute'] : []),
    ];

    const mixinEvents = [
        ...new Set(composables.flatMap(({ descriptor }) => Object.values(descriptor.emits ?? {}))),
    ];
    const emitsText = emitsArgument(ctx, collected, mixinEvents, usesEmit);
    const propsText = propsArgument(ctx, collected, usesProps);

    // One-line declarations are grouped into contiguous blocks so the output reads hand-written;
    // multi-line members keep a blank line between them.
    const importBlock = [
        vueImports.length > 0 ? `import { ${vueImports.join(', ')} } from 'vue';` : null,
        ctx.helpers.has('t') ? "import { useI18n } from 'vue-i18n';" : null,
        routerImports.length > 0 ? `import { ${routerImports.join(', ')} } from 'vue-router';` : null,
        ...composables.map(({ descriptor }) => `import ${descriptor.import.name} from '${descriptor.import.source}';`),
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
            const callArgs = [
                ...args,
                ...config.map((entry) => `${entry.key}: ${snip(ctx, entry.valueNode)}`),
            ];
            const call = `${descriptor.import.name}(${callArgs.length > 0 ? `{ ${callArgs.join(', ')} }` : ''});`;
            const destructured = entries
                .map((entry) =>
                    entry.binding === entry.sourceKey ? entry.sourceKey : `${entry.sourceKey}: ${entry.binding}`,
                )
                .join(', ');

            // A scaffold runs the lifecycle, so its call stands on its own when nothing is read from it.
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
    const provideBlock = collected.provided
        .map((entry) => `provide('${entry.key}', ${snip(ctx, entry.valueNode)});`)
        .join('\n');

    const publicNames = [
        ...collected.injects,
        // The mixin's members were part of the instance surface an override could reach, so they stay
        // public. A renamed one cannot: swDefinePublic only takes shorthand bindings, so exposing it
        // would publish the generated name instead of the one the mixin had.
        ...composables.flatMap(({ entries }) =>
            entries.filter((entry) => entry.binding === entry.member).map((entry) => entry.binding),
        ),
        ...collected.dataEntries.map((entry) => entry.name),
        ...ctx.templateRefs,
        ...collected.computeds.map((computedEntry) => computedEntry.name),
        ...collected.methods.map((method) => method.name),
    ];

    // A review TODO is about the whole draft, so it leads the file instead of trailing the code its
    // checks are about. An anchored one was already emitted above the declaration it names.
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
        // After the macro bindings a composable call can be handed, before the member sections that
        // read what it returns.
        composableBlock || null,
        ...collected.methods.map((method) => renderMember(ctx, method)),
        ...collected.computeds.map((computedEntry) => renderMember(ctx, computedEntry)),
        dataBlock || null,
        refBlock || null,
        provideBlock || null,
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

function transformScript(
    source: string,
    componentName: string,
    transformOptions: {
        templateImportRange: { start: number; end: number };
        templateIdentifiers: ReadonlySet<string>;
        templateComponentTags: ReadonlySet<string>;
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

    const reasonsOf = (kind: ReportKind): string[] =>
        ctx.reports.filter((entry) => entry.kind === kind).map((entry) => entry.reason);

    let ast: t.File;

    try {
        ast = parse(source, { sourceType: 'module', plugins: ['typescript'] });
    } catch (error) {
        return { script: null, moduleScript: null, reasons: [`script parse error: ${(error as Error).message}`] };
    }

    const body = ast.program.body;
    const exportDefault = body.find(
        (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
    );

    if (!exportDefault) {
        return { script: null, moduleScript: null, reasons: ['no default export'] };
    }

    const options = unwrapOptions(exportDefault.declaration);

    if (!options) {
        return { script: null, moduleScript: null, reasons: ['unsupported default export shape'] };
    }

    // --- collect ----------------------------------------------------------------------------------

    const collected = classifyOptions(ctx, options);
    const composables = resolveMixins(ctx, collected, options, collectTopLevelBindings(body, exportDefault));
    const watchers = collectWatchers(ctx, collected);

    // --- name safety checks --------------------------------------------------------------------

    const setupBindingNames = [
        ...collected.injects,
        ...collected.dataEntries.map((entry) => entry.name),
        ...collected.computeds.map((computedEntry) => computedEntry.name),
        ...collected.methods.map((method) => method.name),
    ];

    for (const bindingName of setupBindingNames) {
        if (RESERVED_BINDING.test(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' uses a reserved name`);
        }

        if (GENERATED_HELPER_NAMES.has(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' collides with a generated helper`);
        }

        // The runtime strips declared prop keys from returned setup state, so such a binding would
        // silently render as `undefined`.
        if (collected.propNames.has(bindingName)) {
            report(ctx, 'skip', `'${bindingName}' is declared as both a prop and a component member`);
        }
    }

    // A template resolves a component tag against setup bindings first, so a binding named after a
    // tag the template renders replaces that component with the binding's value. Props are included
    // because they become setup bindings too, and are where this shows up in practice.
    for (const bindingName of [
        ...setupBindingNames,
        ...collected.propNames,
    ]) {
        if (ctx.templateComponentTags.has(bindingName)) {
            report(ctx, 'skip', `binding '${bindingName}' shadows a component tag the template renders`);
        }
    }

    if (ctx.reports.some((entry) => entry.kind === 'skip')) {
        return { script: null, moduleScript: null, reasons: reasonsOf('skip') };
    }

    // --- rewrite pass ---------------------------------------------------------------------------

    // Classification collects bare AST nodes; the rewrite needs their paths to read Babel's scope.
    traverse(ast, {
        enter(path) {
            ctx.paths.set(path.node, path);
        },
    });

    if (collected.createdFn) {
        collected.rewriteFns.push(collected.createdFn);
    }

    for (const { fn } of collected.hooks) {
        collected.rewriteFns.push(fn);
    }

    for (const fn of collected.rewriteFns) {
        rewriteMemberFn(ctx, fn);
    }

    // Data initializers and foreign nodes are spliced in at the top level, so no function frame
    // encloses them.
    for (const entry of [
        ...collected.dataEntries,
        ...collected.provided,
    ]) {
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

    // Template refs are collected by the rewrite pass, so their tag collisions are only visible
    // here. A ref cannot be renamed around one either: the `ref` attribute in the template names it.
    for (const refName of ctx.templateRefs) {
        if (ctx.templateComponentTags.has(refName)) {
            report(ctx, 'skip', `template ref '${refName}' shadows a component tag the template renders`);
        }
    }

    if (ctx.reports.some((entry) => entry.kind === 'skip')) {
        return { script: null, moduleScript: null, reasons: reasonsOf('skip') };
    }

    // --- prelude (module-level code outside the component options) ------------------------------

    const end = ctx.source.indexOf('\n', transformOptions.templateImportRange.end);
    ctx.ms.remove(
        transformOptions.templateImportRange.start,
        end === -1 ? transformOptions.templateImportRange.end : end + 1,
    );

    // Keep the module prelude in a normal `<script>` block. `<script setup>` runs once per
    // component instance, so even a getter, regex, live import, or apparently pure member read can
    // change identity or evaluation timing when moved there.
    const moduleMagicString = ctx.ms.clone();
    moduleMagicString.remove(exportDefault.start as number, exportDefault.end as number);
    const moduleScript = moduleMagicString.toString().trim() || null;

    // --- render ------------------------------------------------------------------------------------

    return {
        script: renderScript(ctx, collected, watchers, composables),
        moduleScript,
        reasons: reasonsOf('todo'),
    };
}

export { transformScript, type ScriptResult };
