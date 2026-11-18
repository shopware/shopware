/**
 * @sw-package framework
 *
 * The public API of a source tree as data. The printers turn it into the snapshot files.
 */
import { isTemplateSourceFile } from '../public-api-source-files';
import { listBlocks } from './block-tree';
import type { BlockContent, TemplateBlock } from './block-tree';
import { byCodePoint } from './code-point-order';
import { componentCalls, markupOf } from './component-registry';
import type { Override, Registration } from './component-registry';
import { resolveTemplates } from './extend-chain';
import type { ComponentTemplate, DroppedBlock, ResolvedTemplate, TemplateSource } from './extend-chain';
import { createFlagContext, FLAG_STATES, inBothStates } from './flag-states';
import type { FlagContext, FlagStates } from './flag-states';
import type { SourceTree } from './source-tree';
import { templateTree } from './template-blocks';

/** @private */
export interface SnapshotOptions {
    /** The feature flags that are on in the major state, for example `v6.8.0.0`. */
    majorFlags: readonly string[];
}

/**
 * @private
 *
 * One registered component. `file` is the path of its snapshot files below `public-api/`, without extension.
 * `blocks` holds the blocks of the template it renders, with those it inherits along its extend chain and the
 * core overrides applied, limited to the flag states in which the whole chain is registered.
 */
export type ComponentUnit = Registration & {
    file: string;
    blocks: TemplateBlock[];
};

/**
 * @private
 *
 * `droppedBlocks` are blocks that an extend or override template declares and the runtime never renders. They
 * are in no component, although their template declares them.
 */
export interface Model {
    flags: FlagContext;
    components: ComponentUnit[];
    droppedBlocks: DroppedBlock[];
}

/**
 * A component file is named after the component and sits beside the folder of the component:
 * `src/module/sw-order/page/sw-order-detail/index.js` gives `module/sw-order/page/sw-order-detail`.
 * A source that has no folder of its own, such as `component/sw-card.vue`, stays in its folder.
 */
function snapshotFile(name: string, source: string): string {
    const folders = source.split('/').slice(1, -1);
    const [sourceName] = (source.split('/').at(-1) ?? '').split('.');

    if (sourceName === 'index' || sourceName === folders.at(-1)) {
        folders.pop();
    }

    return [
        ...folders,
        name,
    ].join('/');
}

/** An element of a component that is registered in one flag state only exists in that state at most. */
function existingIn<Element extends { states: FlagStates }>(states: FlagStates, elements: Element[]): Element[] {
    return elements
        .map((element) => ({ ...element, states: inBothStates(states, element.states) }))
        .filter((element) => FLAG_STATES.some((state) => element.states[state]));
}

/**
 * The snapshot has one entry per component name. A name that is registered twice, for example once per branch
 * of a feature check, would lose one of its registrations without anyone noticing.
 */
function assertUniqueNames(registrations: Registration[], flags: FlagContext): void {
    const registeredIn = new Map<string, string>();

    registrations.forEach(({ name, registeredIn: file }) => {
        const first = registeredIn.get(name);

        if (first !== undefined) {
            throw new Error(
                `The component ${name} is registered twice, in ${first} and in ${file}. ` +
                    `Register it once and branch its template with v-if="feature.isActive('${flags.majorFlag}')", ` +
                    `or register the variant for ${flags.majorVersion} under a new name.`,
            );
        }

        registeredIn.set(name, file);
    });
}

/**
 * The snapshot lists blocks per component, so the blocks of a template that no component uses would be missing
 * from it without anyone noticing.
 */
function assertEveryTemplateIsUsed(blockTrees: ReadonlyMap<string, BlockContent[]>, used: ReadonlySet<string>): void {
    const missing = [...blockTrees]
        .filter(([template]) => !used.has(template))
        .flatMap(([template, tree]) => listBlocks(tree).map(({ name }) => `${name} in ${template}`));

    if (missing.length > 0) {
        throw new Error(
            `These blocks belong to no registered component, so the snapshot would not list them:\n${missing.join('\n')}`,
        );
    }
}

function assertEveryOverrideHasItsComponent(overrides: Override[], registrations: Registration[]): void {
    const unknown = overrides.find(({ target }) => registrations.every(({ name }) => name !== target));

    if (unknown) {
        throw new Error(
            `${unknown.registeredIn}: Component.override('${unknown.target}') overrides a component that core does not register.`,
        );
    }
}

function assertEveryOverrideCoversItsComponent(overrides: Override[], registrations: Registration[]): void {
    registrations.forEach((target) => {
        const partial = overrides.find(
            ({ target: name, states }) =>
                name === target.name && FLAG_STATES.some((state) => target.states[state] && !states[state]),
        );

        if (partial) {
            throw new Error(
                `${partial.registeredIn}: the override of ${target.name} is registered in one flag state only. ` +
                    'The snapshot cannot apply an override to one state of a template so far.',
            );
        }
    });
}

/** The templates of the overrides of one component by index. Overrides with the same index keep their file order. */
function overrideTemplatesOf(
    target: Registration,
    overrides: Override[],
    source: (file: string) => TemplateSource,
): TemplateSource[] {
    return overrides
        .filter((override) => override.target === target.name)
        .sort((a, b) => a.index - b.index)
        .flatMap(({ template }) => (template === null ? [] : [source(template)]));
}

function componentTemplates(
    registrations: Registration[],
    overrides: Override[],
    source: (file: string) => TemplateSource,
): ComponentTemplate[] {
    return registrations.map((registration) => {
        const markup = markupOf(registration);

        return {
            name: registration.name,
            extends: registration.extends,
            native: registration.language === 'vue',
            states: registration.states,
            template: markup === null ? null : source(markup),
            overrides: overrideTemplatesOf(registration, overrides, source),
        };
    });
}

function componentUnit(registration: Registration, { tree, states }: ResolvedTemplate): ComponentUnit {
    return {
        ...registration,
        file: snapshotFile(registration.name, registration.source),
        blocks: existingIn(states, listBlocks(tree)),
    };
}

/** @private */
export function buildModel(tree: SourceTree, options: SnapshotOptions): Model {
    const flags = createFlagContext(options.majorFlags);
    const blockTrees = new Map(
        tree.files
            .filter(isTemplateSourceFile)
            .map((file): [string, BlockContent[]] => [file, templateTree(tree.read(file), file, flags)]),
    );
    const { registrations, overrides } = componentCalls(tree, flags);

    assertUniqueNames(registrations, flags);
    assertEveryOverrideHasItsComponent(overrides, registrations);
    assertEveryOverrideCoversItsComponent(overrides, registrations);
    assertEveryTemplateIsUsed(
        blockTrees,
        new Set(
            [
                ...registrations.map(markupOf),
                ...overrides.map(({ template }) => template),
            ].filter((file) => file !== null),
        ),
    );

    const resolved = resolveTemplates(
        componentTemplates(registrations, overrides, (file) => ({ file, tree: blockTrees.get(file) ?? [] })),
    );
    const components = registrations
        .map((registration, index) => componentUnit(registration, resolved[index]))
        .sort((a, b) => byCodePoint(a.name, b.name));
    const droppedBlocks = resolved
        .flatMap(({ dropped }) => dropped)
        .sort((a, b) => byCodePoint(a.file, b.file) || byCodePoint(a.block, b.block));

    return { flags, components, droppedBlocks };
}
