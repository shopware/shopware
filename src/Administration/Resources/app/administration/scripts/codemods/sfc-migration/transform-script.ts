/**
 * @sw-package framework
 */

/**
 * Converts an Options API component script into a native setup `<script setup>` body ending in
 * `swDefinePublic({ ... })`.
 *
 * Strategy: parse once with @babel/parser for positions, rewrite every component-bound `this.*`
 * reference in place via magic-string (rewrite-this.ts), then assemble the new script from
 * verbatim source slices. Conversion policy lives in tables.ts (tier tables, rewrite map) and
 * option-handlers.ts (one handler per supported option); indentation is left to prettier and
 * correctness to the validation gate (validate.ts). This module owns the orchestration and the
 * section ordering of the assembled script — the order is TDZ-driven: eager consumers (data
 * initializers, watchers, the inlined created() body) come after everything they may reference.
 */

import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import MagicString from 'magic-string';
import { GENERATED_HELPER_NAMES, HELPER_SETUP_LINES, RESERVED_BINDING, type TodoEntry } from './tables';
import { type Ctx, arrowText, collectPatternNames, snip, unwrapOptions } from './ast';
import { type Collected, buildWatchers, classifyOptions, emitsEventNames, resolveMixins } from './option-handlers';
import { rewriteMemberFn, rewriteThis } from './rewrite-this';

type ScriptResult = {
    script: string | null;
    moduleScript: string | null;
    todos: string[];
    blockers: string[];
};

function todoBlock(entry: TodoEntry): string {
    const header = `// TODO(sfc-migration): ${entry.reason}`;

    if (entry.checks) {
        return [
            header,
            ...entry.checks.map((check) => `// - ${check}`),
        ].join('\n');
    }

    if (!entry.code) {
        return header;
    }

    const codeLines = entry.code.split('\n').map((line) => `// ${line}`);

    return [
        `${header} — original code:`,
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

        const declaration =
            statement.type === 'ExportNamedDeclaration' && statement.declaration ? statement.declaration : statement;

        if (declaration.type === 'ImportDeclaration') {
            for (const specifier of declaration.specifiers) {
                names.add(specifier.local.name);
            }
        } else if (declaration.type === 'VariableDeclaration') {
            for (const declarator of declaration.declarations) {
                collectPatternNames(declarator.id, names);
            }
        } else if (declaration.type === 'FunctionDeclaration' || declaration.type === 'ClassDeclaration') {
            collectPatternNames(declaration.id, names);
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

function transformScript(
    source: string,
    componentName: string,
    transformOptions: {
        templateImportRange: { start: number; end: number };
        templateIdentifiers: ReadonlySet<string>;
    },
): ScriptResult {
    const ctx: Ctx = {
        source,
        ms: new MagicString(source),
        componentName,
        bindings: new Map(),
        renamedBindings: new Map(),
        templateIdentifiers: transformOptions.templateIdentifiers,
        templateRefs: new Set(),
        helpers: new Set(),
        inferredEmits: [],
        todos: [],
        blockers: new Set(),
    };

    let ast: t.File;

    try {
        ast = parse(source, { sourceType: 'module', plugins: ['typescript'] });
    } catch (error) {
        return {
            script: null,
            moduleScript: null,
            todos: [],
            blockers: [`script parse error: ${(error as Error).message}`],
        };
    }

    const body = ast.program.body;
    const exportDefault = body.find(
        (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
    );

    if (!exportDefault) {
        return { script: null, moduleScript: null, todos: [], blockers: ['no default export'] };
    }

    const options = unwrapOptions(exportDefault.declaration);

    if (!options) {
        return { script: null, moduleScript: null, todos: [], blockers: ['unsupported default export shape'] };
    }

    const collected = classifyOptions(ctx, options);
    const composables = resolveMixins(ctx, collected, options, collectTopLevelBindings(body, exportDefault));
    const watchers = buildWatchers(ctx, collected);

    // --- name safety checks --------------------------------------------------------------------

    const setupBindingNames = [
        ...collected.injects,
        ...collected.dataEntries.map((entry) => entry.name),
        ...collected.computeds.map((computedEntry) => computedEntry.name),
        ...collected.methods.map((method) => method.name),
    ];

    for (const bindingName of setupBindingNames) {
        if (RESERVED_BINDING.test(bindingName)) {
            ctx.blockers.add(`binding '${bindingName}' uses a reserved name`);
        }

        if (GENERATED_HELPER_NAMES.has(bindingName)) {
            ctx.blockers.add(`binding '${bindingName}' collides with a generated helper`);
        }

        // The runtime strips declared prop keys from returned setup state, so such a binding would
        // silently render as `undefined`.
        if (collected.propNames.has(bindingName)) {
            ctx.blockers.add(`'${bindingName}' is declared as both a prop and a component member`);
        }
    }

    if (ctx.blockers.size > 0) {
        return { script: null, moduleScript: null, todos: [], blockers: [...ctx.blockers] };
    }

    // --- rewrite pass ---------------------------------------------------------------------------

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
    for (const entry of collected.dataEntries) {
        rewriteThis(ctx, entry.valueNode, true, null);
    }

    for (const composable of composables) {
        for (const entry of composable.config) {
            rewriteThis(ctx, entry.valueNode, true, null);
        }
    }

    for (const node of collected.foreignNodes) {
        rewriteThis(ctx, node, false, null);
    }

    if (ctx.blockers.size > 0) {
        return { script: null, moduleScript: null, todos: [], blockers: [...ctx.blockers] };
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

    // --- assembly --------------------------------------------------------------------------------

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
            return entries.length > 0 ? `const { ${destructured} } = ${call}` : call;
        })
        .join('\n');
    const dataBlock = collected.dataEntries
        .map((entry) => `const ${entry.name} = ref(${snip(ctx, entry.valueNode)});`)
        .join('\n');
    const refBlock = [...ctx.templateRefs].map((refName) => `const ${refName} = ref(null);`).join('\n');

    // A review TODO is about the whole draft, so it leads the file instead of trailing the code its
    // checks are about.
    const reviewTodos = ctx.todos.filter((entry) => entry.checks !== undefined);
    const siteTodos = ctx.todos.filter((entry) => entry.checks === undefined);

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
        ...collected.methods.map((method) => method.text()),
        ...collected.computeds.map((computedEntry) => computedEntry.text()),
        dataBlock || null,
        refBlock || null,
        ...watchers.map((watcher) => watcher()),
        ...collected.hooks.map(({ hook, fn }) => `${hook}(${arrowText(ctx, fn)});`),
        collected.createdFn ? `void (${arrowText(ctx, collected.createdFn)})();` : null,
        ...siteTodos.map(todoBlock),
    ];

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
    const publicText =
        publicNames.length > 0
            ? `swDefinePublic({\n${publicNames.map((publicName) => `${publicName},`).join('\n')}\n});`
            : 'swDefinePublic({});';

    sections.push(publicText);

    return {
        script: sections.filter((section): section is string => Boolean(section)).join('\n\n'),
        moduleScript,
        todos: ctx.todos.map((entry) => entry.reason),
        blockers: [],
    };
}

export { transformScript, type ScriptResult };
