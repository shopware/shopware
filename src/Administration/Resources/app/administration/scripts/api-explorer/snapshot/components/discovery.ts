/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import {
    collectComponentSourceIndex,
    type ComponentSourceIndex,
} from '../../../codemods/sfc-migration/component-source-model';
import type { AnalysisContext } from '../analysis-context';
import type { SnapshotDiagnostic } from '../types';
import type { ComponentRef, OverrideRef } from './component';

export interface DiscoveredComponents {
    /** Sorted by name, one per name. */
    components: ComponentRef[];
    overrides: OverrideRef[];
    diagnostics: SnapshotDiagnostic[];
}

/** Same candidate order as the source index uses to resolve a registration import. */
function resolveEntryFile(specifier: string, registeringFile: string, adminSrc: string): string | null {
    let resolved: string;

    if (specifier.startsWith('.')) {
        resolved = path.resolve(path.dirname(registeringFile), specifier);
    } else if (specifier.startsWith('src/')) {
        resolved = path.resolve(adminSrc, specifier.slice('src/'.length));
    } else {
        return null;
    }

    return (
        [
            path.join(resolved, 'index.js'),
            path.join(resolved, 'index.ts'),
            `${resolved}.js`,
            `${resolved}.ts`,
        ].find((candidate) => fs.existsSync(candidate)) ?? null
    );
}

export type SourceIndex = Pick<ComponentSourceIndex, 'components' | 'diagnostics' | 'registrationsByFile'>;

export function discoverComponents(
    context: AnalysisContext,
    index: SourceIndex = collectComponentSourceIndex(path.join(context.adminRoot, 'src'), {
        adminSrc: path.join(context.adminRoot, 'src'),
    }),
): DiscoveredComponents {
    const adminSrc = path.join(context.adminRoot, 'src');
    // Template binding findings are not listed: the extractor resolves those templates itself.
    const diagnostics: SnapshotDiagnostic[] = index.diagnostics
        .filter(({ isScanError }) => isScanError)
        .map(({ file, label, message }) => ({ file: context.toRelativePath(file), message: `${label}: ${message}` }));
    const byName = new Map<string, ComponentRef>();
    const overrides: OverrideRef[] = [];

    for (const registration of [...index.registrationsByFile.values()].flat()) {
        const registeringFile = context.toRelativePath(registration.file);
        const label = `Component.${registration.kind}('${registration.name}')`;

        if (registration.inline) {
            overrides.push({
                name: registration.name,
                file: registration.file,
                inlineCallStart: registration.range.start,
            });
            continue;
        }

        const specifier = registration.specifier ?? '';

        if (/\.vue$/.test(specifier)) {
            diagnostics.push({
                file: registeringFile,
                message: `${label}: single file components are not supported yet, skipped '${specifier}'.`,
            });
            continue;
        }

        const entryFile = resolveEntryFile(specifier, registration.file, adminSrc);

        if (!entryFile) {
            diagnostics.push({ file: registeringFile, message: `${label}: cannot resolve '${specifier}'.` });
            continue;
        }

        if (registration.kind === 'override') {
            overrides.push({ name: registration.name, file: entryFile, inlineCallStart: null });
            continue;
        }

        const existing = byName.get(registration.name);

        if (existing) {
            diagnostics.push({
                file: registeringFile,
                message: `${label}: the name is already registered by ${context.toRelativePath(existing.entryFile)}, ignored.`,
            });
            continue;
        }

        byName.set(registration.name, {
            name: registration.name,
            registration: registration.kind,
            parent: registration.parent ?? null,
            entryFile,
            twigPath: index.components.get(entryFile)?.template?.twigPath ?? null,
        });
    }

    return {
        components: [...byName.values()].sort((a, b) => (a.name < b.name ? -1 : Number(a.name > b.name))),
        overrides,
        diagnostics,
    };
}
