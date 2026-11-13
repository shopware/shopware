/**
 * @sw-package framework
 *
 * The public API of a source tree as data. The printers turn it into the snapshot files.
 */
import { isTemplateSourceFile } from '../public-api-source-files';
import { byCodePoint } from './code-point-order';
import { componentRegistrations, markupOf } from './component-registry';
import type { Registration } from './component-registry';
import { createFlagContext, FLAG_STATES, inBothStates } from './flag-states';
import type { FlagContext, FlagStates } from './flag-states';
import type { SourceTree } from './source-tree';
import { templateBlocks } from './template-blocks';
import type { TemplateBlock } from './template-blocks';

/** @private */
export interface SnapshotOptions {
    /** The feature flags that are on in the major state, for example `v6.8.0.0`. */
    majorFlags: readonly string[];
}

/**
 * @private
 *
 * One registered component. `file` is the path of its snapshot files below `public-api/`, without extension.
 * `blocks` holds the blocks of its own markup, limited to the flag states in which the component is registered.
 * The blocks an extend child inherits are not resolved yet.
 */
export type ComponentUnit = Registration & {
    file: string;
    blocks: TemplateBlock[];
};

/** @private */
export interface Model {
    flags: FlagContext;
    components: ComponentUnit[];
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

function componentUnit(registration: Registration, blocks: TemplateBlock[]): ComponentUnit {
    return {
        ...registration,
        file: snapshotFile(registration.name, registration.source),
        blocks: existingIn(registration.states, blocks),
    };
}

/**
 * The snapshot has one entry per component name. A name that is registered twice, for example once per branch
 * of a feature check, would lose one of its registrations without anyone noticing.
 */
function assertUniqueNames(components: ComponentUnit[]): void {
    const registeredIn = new Map<string, string>();

    components.forEach(({ name, registeredIn: file }) => {
        const first = registeredIn.get(name);

        if (first !== undefined) {
            throw new Error(
                `The component ${name} is registered twice, in ${first} and in ${file}. ` +
                    'The snapshot cannot tell two registrations of one name apart yet.',
            );
        }

        registeredIn.set(name, file);
    });
}

/**
 * The snapshot lists blocks per component, so the blocks of a template that no component uses would be missing
 * from it without anyone noticing.
 */
function assertEveryBlockIsListed(blocksByTemplate: Map<string, TemplateBlock[]>, listedBlocks: TemplateBlock[]): void {
    const listed = new Set(listedBlocks.map(({ name }) => name));
    const missing = [...blocksByTemplate].flatMap(([template, blocks]) =>
        blocks.filter(({ name }) => !listed.has(name)).map(({ name }) => `${name} in ${template}`),
    );

    if (missing.length > 0) {
        throw new Error(
            `These blocks belong to no registered component, so the snapshot would not list them:\n${missing.join('\n')}`,
        );
    }
}

/** @private */
export function buildModel(tree: SourceTree, options: SnapshotOptions): Model {
    const flags = createFlagContext(options.majorFlags);
    const blocksByTemplate = new Map(
        tree.files
            .filter(isTemplateSourceFile)
            .map((file): [string, TemplateBlock[]] => [file, templateBlocks(tree.read(file), file, flags)]),
    );
    const registrations = componentRegistrations(tree, flags);
    const blocksOf = (registration: Registration): TemplateBlock[] =>
        blocksByTemplate.get(markupOf(registration) ?? '') ?? [];

    assertEveryBlockIsListed(blocksByTemplate, registrations.flatMap(blocksOf));

    const components = registrations
        .map((registration) => componentUnit(registration, blocksOf(registration)))
        .sort((a, b) => byCodePoint(a.name, b.name));

    assertUniqueNames(components);

    return { flags, components };
}
