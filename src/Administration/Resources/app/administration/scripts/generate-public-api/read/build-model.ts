/**
 * @sw-package framework
 *
 * Builds the model of a source tree: finds the registered components, merges their templates along the extend
 * chains, and reads the blocks and members of each one. The services come from `services/service-units.ts`
 * and the stores from `stores/store-units.ts`.
 */
import { isTemplateSourceFile } from '../../public-api-source-files';
import { byCodePoint } from '../helpers/code-point-order';
import { createFlagContext, existingIn, FLAG_STATES } from '../model/flag-states';
import type { FlagContext } from '../model/flag-states';
import type { UnitMembers } from '../model/members';
import type { ComponentUnit, Model, Registration, UnusedBlock } from '../model/model';
import type { SourceTree } from '../source/source-tree';
import { componentCalls, markupOf } from './component-registry';
import { Declarations } from './declarations';
import type { ComponentCalls, Override } from './component-registry';
import { componentOptions } from './options/component-options';
import { readOptionMembers } from './options/option-members';
import { readStructure } from './template/block-structure';
import { listBlocks } from './template/block-tree';
import { serviceUnits } from './services/service-units';
import { storeUnits } from './stores/store-units';
import { resolveTemplates } from './template/extend-chain';
import type { ComponentTemplate, OverrideTemplate, ResolvedTemplate, TemplateSource } from './template/extend-chain';
import { renderTemplate } from './template/rendered-template';
import { templateTree } from './template/template-blocks';
import { readTemplateMembers } from './template/template-members';

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

function flagBranch(flags: FlagContext): string {
    return `v-if="feature.isActive('${flags.majorFlag}')"`;
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
                    `Register it once and branch its template with ${flagBranch(flags)}, ` +
                    `or register the variant for ${flags.majorVersion} under a new name.`,
            );
        }

        registeredIn.set(name, file);
    });
}

function unusedBlocks(
    { registrations, overrides }: ComponentCalls,
    templates: ReadonlyMap<string, TemplateSource>,
): UnusedBlock[] {
    const used = new Set([...registrations.map(markupOf), ...overrides.map(({ template }) => template)]);

    return [...templates.values()]
        .filter(({ file }) => !used.has(file))
        .flatMap(({ file, tree }) => listBlocks(tree).map(({ name }) => ({ block: name, file })));
}

function assertEveryOverrideHasItsComponent({ registrations, overrides }: ComponentCalls): void {
    const unknown = overrides.find(({ target }) => registrations.every(({ name }) => name !== target));

    if (unknown) {
        throw new Error(
            `${unknown.registeredIn}: Component.override('${unknown.target}') overrides a component that core does not register.`,
        );
    }
}

function assertEveryOverrideCoversItsComponent({ registrations, overrides }: ComponentCalls, flags: FlagContext): void {
    registrations.forEach((component) => {
        const partial = overrides
            .filter((override) => override.target === component.name)
            .find((override) => FLAG_STATES.some((state) => component.states[state] && !override.states[state]));

        if (partial) {
            throw new Error(
                `${partial.registeredIn}: the override of ${component.name} is registered in one flag state only. ` +
                    'The snapshot cannot apply an override to one state of a template so far. ' +
                    `Register the override in both states and branch its template with ${flagBranch(flags)}.`,
            );
        }
    });
}

function componentTemplates(
    { registrations, overrides }: ComponentCalls,
    templates: ReadonlyMap<string, TemplateSource>,
): ComponentTemplate[] {
    const templateIn = (file: string): TemplateSource => templates.get(file) ?? { file, tree: [] };
    const indexedTemplate = ({ index, template }: Override): OverrideTemplate[] =>
        template === null ? [] : [{ index, template: templateIn(template) }];

    return registrations.map((registration) => {
        const markup = markupOf(registration);

        return {
            name: registration.name,
            extends: registration.extends,
            native: registration.language === 'vue',
            states: registration.states,
            template: markup === null ? null : templateIn(markup),
            overrides: overrides.filter((override) => override.target === registration.name).flatMap(indexedTemplate),
        };
    });
}

const NO_MEMBERS: UnitMembers = { members: [], notes: [], sections: {} };

/** What several readers found about one component, as one result. */
function mergeMembers(...parts: UnitMembers[]): UnitMembers {
    return {
        members: parts.flatMap(({ members }) => members),
        notes: parts.flatMap(({ notes }) => notes),
        sections: Object.assign({}, ...parts.map(({ sections }) => sections)) as UnitMembers['sections'],
    };
}

/** A source without an options object that can be read declares members the snapshot does not know. */
function declaredMembers(registration: Registration, sources: SourceTree): UnitMembers {
    const options = componentOptions(registration, sources);

    return options === undefined
        ? { ...NO_MEMBERS, notes: [`unresolved options ${registration.sfc ?? registration.source}`] }
        : readOptionMembers(options);
}

function componentUnit(
    registration: Registration,
    { tree, states }: ResolvedTemplate,
    sources: SourceTree,
    flags: FlagContext,
): ComponentUnit {
    const template = renderTemplate(tree);
    const { contexts, crossings } = readStructure(template);
    const members = mergeMembers(declaredMembers(registration, sources), readTemplateMembers(template, flags));

    return {
        ...registration,
        ...members,
        file: snapshotFile(registration.name, registration.source),
        blocks: existingIn(states, listBlocks(tree, contexts)),
        crossings: existingIn(states, crossings),
        members: existingIn(states, members.members),
    };
}

/** @private */
export interface SnapshotOptions {
    /** The feature flags that are on in the major state, for example `v6.8.0.0`. */
    majorFlags: readonly string[];
}

/** @private */
export function buildModel(tree: SourceTree, options: SnapshotOptions): Model {
    const flags = createFlagContext(options.majorFlags);
    const templates = new Map(
        tree.files
            .filter(isTemplateSourceFile)
            .map((file): [string, TemplateSource] => [file, { file, tree: templateTree(tree.read(file), file, flags) }]),
    );
    const calls = componentCalls(tree, flags);
    const declarations = new Declarations(tree);
    const { registrations } = calls;

    assertUniqueNames(registrations, flags);
    assertEveryOverrideHasItsComponent(calls);
    assertEveryOverrideCoversItsComponent(calls, flags);

    const resolved = resolveTemplates(componentTemplates(calls, templates));
    const components = registrations
        .map((registration, index) => componentUnit(registration, resolved[index], tree, flags))
        .sort((a, b) => byCodePoint(a.name, b.name));
    const droppedBlocks = resolved
        .flatMap(({ dropped }) => dropped)
        .sort((a, b) => byCodePoint(a.file, b.file) || byCodePoint(a.block, b.block));

    return {
        flags,
        components,
        ...serviceUnits(tree, declarations, flags),
        stores: storeUnits(tree, declarations, flags),
        droppedBlocks,
        unusedBlocks: unusedBlocks(calls, templates),
    };
}
