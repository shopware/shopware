/**
 * @sw-package framework
 */

import type { AnalysisContext } from '../analysis-context';
import { createDependencyFingerprints, type FileHashCache } from '../dependency-fingerprint';
import type { BlockEntry, ComponentEntry, ExtractionResult, SnapshotDiagnostic } from '../types';
import {
    extractComponent,
    extractOverride,
    mergeByName,
    type ComponentRef,
    type OverrideContribution,
    type OverrideRef,
} from './component';
import { discoverIncrementally, type DiscoveryState } from './incremental-discovery';

export { extractComponent, extractOverride } from './component';
export { discoverComponents } from './discovery';

/**
 * Override blocks the target does not declare are attached below their parent in the target. A block
 * without such an anchor is never rendered, so it is dropped together with its children.
 */
function mergeBlocks(target: BlockEntry[], added: BlockEntry[]): { blocks: BlockEntry[]; dropped: BlockEntry[] } {
    const result = [...target];
    const dropped: BlockEntry[] = [];
    const droppedNames = new Set<string>();
    const byName = new Map<string, BlockEntry>();

    for (const block of target) {
        if (!byName.has(block.name)) {
            byName.set(block.name, block);
        }
    }

    const namePath = (key: string): string => key.replace(/~\d+$/, '');

    for (const block of added) {
        if (byName.has(block.name)) {
            continue;
        }

        const parent = block.parent ? byName.get(block.parent) : undefined;

        if (!parent) {
            if (!block.parent || !droppedNames.has(block.parent)) {
                dropped.push(block);
            }

            droppedNames.add(block.name);
            continue;
        }

        const path = `${namePath(parent.key)}/${block.name}`;
        const occurrence = result.filter(({ key }) => namePath(key) === path).length + 1;
        const merged: BlockEntry = {
            ...block,
            key: occurrence > 1 ? `${path}~${occurrence}` : path,
            depth: parent.depth + 1,
        };

        result.push(merged);
        byName.set(block.name, merged);
    }

    return { blocks: result, dropped };
}

/** The target's own declarations win; an override only contributes what the target lacks. */
function applyOverride(entry: ComponentEntry, override: OverrideContribution): SnapshotDiagnostic[] {
    const { members, template } = override;
    const merged = mergeBlocks(entry.blocks, template?.blocks ?? []);

    entry.overriddenBy = [...new Set([...entry.overriddenBy, override.file])].sort();
    entry.props = mergeByName(entry.props, members.props);
    entry.emits = mergeByName(entry.emits, [...members.emits, ...(template?.emits ?? [])]);
    entry.data = mergeByName(entry.data, members.data);
    entry.computed = mergeByName(entry.computed, members.computed);
    entry.methods = mergeByName(entry.methods, members.methods);
    entry.inject = mergeByName(entry.inject, members.inject);
    entry.mixins = mergeByName(entry.mixins, members.mixins);
    entry.slots = mergeByName(entry.slots, template?.slots ?? []);
    entry.blocks = merged.blocks;

    return merged.dropped.map((block) => ({
        file: block.location?.file ?? override.file,
        message: `The override of ${entry.name} declares block ${block.name} (line ${block.location?.line}), which ${entry.name} does not define; it is ignored.`,
    }));
}

export interface ComponentExtractionState {
    adminRoot: string;
    discovery: DiscoveryState | null;
    fileHashes: FileHashCache;
    components: Map<string, ExtractedComponent>;
}

interface ExtractedComponent {
    fingerprint: string | null;
    entry: ComponentEntry;
    diagnostics: SnapshotDiagnostic[];
    /** One list per override of the component, in merge order. */
    overrideDiagnostics: SnapshotDiagnostic[][];
}

export interface ComponentExtraction extends ExtractionResult<ComponentEntry[]> {
    /** Pass it as `previous` to the next extraction of the same root. */
    state: ComponentExtractionState;
    /** Names of the components extracted in this run; the other entries were taken over from `previous`. */
    extracted: string[];
}

export interface ExtractComponentsOptions {
    /** Entries whose sources, templates and overrides did not change are reused from it. */
    previous?: ComponentExtractionState;
}

function compareOverrides(a: OverrideRef, b: OverrideRef): number {
    if (a.file === b.file) {
        return (a.inlineCallStart ?? 0) - (b.inlineCallStart ?? 0);
    }

    return a.file < b.file ? -1 : 1;
}

function extractWithOverrides(
    context: AnalysisContext,
    ref: ComponentRef,
    overrides: OverrideRef[],
    fingerprint: string | null,
): ExtractedComponent {
    const { entry, diagnostics } = extractComponent(context, ref);
    const overrideDiagnostics = overrides.map((override) => {
        const extracted = extractOverride(context, override);

        return extracted.contribution
            ? [...extracted.diagnostics, ...applyOverride(entry, extracted.contribution)]
            : extracted.diagnostics;
    });

    return { fingerprint, entry, diagnostics, overrideDiagnostics };
}

export function extractComponents(
    context: AnalysisContext,
    { previous: previousOption }: ExtractComponentsOptions = {},
): Promise<ComponentExtraction> {
    const previous = previousOption?.adminRoot === context.adminRoot ? previousOption : undefined;
    const fileHashes: FileHashCache = previous?.fileHashes ?? (new Map() as FileHashCache);
    const discovery = discoverIncrementally(context, previous?.discovery ?? null);
    const { discovered } = discovery;
    const fingerprints = createDependencyFingerprints(context, fileHashes);
    const overrides = [...discovered.overrides].sort(compareOverrides);
    const overridesByName = new Map<string, OverrideRef[]>();

    for (const override of overrides) {
        overridesByName.set(override.name, [...(overridesByName.get(override.name) ?? []), override]);
    }

    const components = new Map<string, ExtractedComponent>();
    const extracted: string[] = [];

    for (const ref of discovered.components) {
        const ownOverrides = overridesByName.get(ref.name) ?? [];
        const fingerprint =
            fingerprints?.of(
                [
                    ref.entryFile,
                    ...(ref.twigPath ? [ref.twigPath] : []),
                    ...ownOverrides.map(({ file }) => file),
                ],
                JSON.stringify([
                    ref,
                    ownOverrides,
                ]),
            ) ?? null;
        const cached = previous?.components.get(ref.name);

        if (fingerprint !== null && cached?.fingerprint === fingerprint) {
            components.set(ref.name, cached);
            continue;
        }

        components.set(ref.name, extractWithOverrides(context, ref, ownOverrides, fingerprint));
        extracted.push(ref.name);
    }

    const diagnostics: SnapshotDiagnostic[] = [
        ...discovered.diagnostics,
        ...[...components.values()].flatMap((component) => component.diagnostics),
    ];
    const overrideCounts = new Map<string, number>();

    for (const override of overrides) {
        const target = components.get(override.name);

        if (!target) {
            diagnostics.push({
                file: context.toRelativePath(override.file),
                message: `Component.override('${override.name}') targets a component that is not registered.`,
            });
            continue;
        }

        const position = overrideCounts.get(override.name) ?? 0;
        overrideCounts.set(override.name, position + 1);
        diagnostics.push(...target.overrideDiagnostics[position]);
    }

    return Promise.resolve({
        result: [...components.values()].map(({ entry }) => entry),
        diagnostics,
        state: { adminRoot: context.adminRoot, discovery: discovery.state, fileHashes, components },
        extracted,
    });
}
