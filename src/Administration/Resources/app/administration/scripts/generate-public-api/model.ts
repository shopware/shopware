/**
 * @sw-package framework
 *
 * The public API of a source tree as data. The printers turn it into the snapshot files.
 */
import { isTemplateSourceFile } from '../public-api-source-files';
import { byCodePoint } from './code-point-order';
import { componentRegistrations } from './component-registry';
import type { ComponentLanguage, Registration } from './component-registry';
import { createFlagContext, inBothStates } from './flag-states';
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
 * One registered component. `file` is the path of its snapshot files below `public-api/`, without extension,
 * and `states` are the flag states in which it is registered. `blocks` holds the blocks of its own markup.
 * The blocks an extend child inherits are not resolved yet.
 */
export interface ComponentUnit {
    name: string;
    kind: 'register' | 'extend';
    extends: string | null;
    language: ComponentLanguage;
    visibility: 'public' | 'private';
    visibilitySource: 'default' | 'export' | 'registration';
    registeredIn: string;
    source: string;
    sfc: string | null;
    template: string | null;
    file: string;
    states: FlagStates;
    blocks: TemplateBlock[];
}

/** @private */
export interface Model {
    flags: FlagContext;
    components: ComponentUnit[];
}

type ComponentRegistration = Registration & { kind: ComponentUnit['kind'] };

/**
 * @private
 *
 * The elements of a component that exist in a flag state, each with the states in which it exists. An element
 * of a component that is registered in one state only exists in that state at most.
 */
export function existingElements<Element extends { states: FlagStates }>(
    unit: ComponentUnit,
    elements: Element[],
): Element[] {
    return elements
        .map((element) => ({ ...element, states: inBothStates(unit.states, element.states) }))
        .filter(({ states }) => states.current || states.major);
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

function visibilityOf({
    privateExport,
    privateRegistration,
}: Registration): Pick<ComponentUnit, 'visibility' | 'visibilitySource'> {
    if (privateExport) {
        return { visibility: 'private', visibilitySource: 'export' };
    }

    return privateRegistration
        ? { visibility: 'private', visibilitySource: 'registration' }
        : { visibility: 'public', visibilitySource: 'default' };
}

// A core override adds no component. Its blocks arrive with the extend chains.
function addsComponent(registration: Registration): registration is ComponentRegistration {
    return registration.kind !== 'override';
}

function componentUnit(registration: ComponentRegistration, blocks: TemplateBlock[]): ComponentUnit {
    const { kind, name, extendsName, language, registeredIn, source, sfc, template, states } = registration;

    return {
        name,
        kind,
        extends: extendsName,
        language,
        ...visibilityOf(registration),
        registeredIn,
        source,
        sfc,
        template,
        file: snapshotFile(name, source),
        states,
        blocks,
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
function assertEveryBlockIsListed(
    tree: SourceTree,
    components: ComponentUnit[],
    blocksOf: (markup: string) => TemplateBlock[],
): void {
    const listed = new Set(components.flatMap(({ blocks }) => blocks.map(({ name }) => name)));
    const missing = tree.files.filter(isTemplateSourceFile).flatMap((markup) =>
        blocksOf(markup)
            .filter(({ name }) => !listed.has(name))
            .map(({ name }) => `${name} in ${markup}`),
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
    const blocksByMarkup = new Map<string, TemplateBlock[]>();
    const blocksOf = (markup: string): TemplateBlock[] => {
        let blocks = blocksByMarkup.get(markup);

        if (blocks === undefined) {
            blocks = templateBlocks(tree.read(markup), markup, flags);
            blocksByMarkup.set(markup, blocks);
        }

        return blocks;
    };

    const components = componentRegistrations(tree, flags)
        .filter(addsComponent)
        .map((registration) =>
            componentUnit(registration, registration.markup === null ? [] : blocksOf(registration.markup)),
        )
        .sort((a, b) => byCodePoint(a.name, b.name));

    assertUniqueNames(components);
    assertEveryBlockIsListed(tree, components, blocksOf);

    return { flags, components };
}
