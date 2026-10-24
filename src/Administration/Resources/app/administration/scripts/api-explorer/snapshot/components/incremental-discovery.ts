/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import * as fs from 'node:fs';
import * as path from 'node:path';
import {
    collectComponentSourceIndex,
    type ComponentSource,
    type RegistrationReference,
    type SourceDiagnostic,
} from '../../../codemods/sfc-migration/component-source-model';
import type { AnalysisContext } from '../analysis-context';
import { discoverComponents, type DiscoveredComponents, type SourceIndex } from './discovery';

interface FileScan {
    identity: string;
    registrations: RegistrationReference[];
    component: ComponentSource | undefined;
    scanErrors: SourceDiagnostic[];
}

export interface DiscoveryState {
    /** Hash over the paths of the source tree; a scan resolves registrations by which files exist. */
    listing: string;
    /** Hash over the paths and contents of the source tree. */
    key: string;
    files: Map<string, FileScan>;
    discovered: DiscoveredComponents;
}

function sha1(lines: string[]): string {
    return createHash('sha1').update(lines.join('\n')).digest('hex');
}

function identityOf(context: AnalysisContext, file: string): string {
    const sourceFile = context.program.getSourceFile(file);

    if (sourceFile) {
        return context.contentHash(sourceFile);
    }

    try {
        const stat = fs.statSync(file, { throwIfNoEntry: false });

        return stat ? `${stat.mtimeMs}:${stat.ctimeMs}:${stat.size}` : 'missing';
    } catch {
        return 'unreadable';
    }
}

/** The scan checks whether files exist next to a registration's target; only those below `src` are in the listing. */
function resolvesOutside(adminSrc: string, { file, specifier }: RegistrationReference): boolean {
    if (!specifier || !(specifier.startsWith('.') || specifier.startsWith('src/'))) {
        return false;
    }

    const target = specifier.startsWith('.')
        ? path.resolve(path.dirname(file), specifier)
        : path.resolve(adminSrc, specifier.slice('src/'.length));
    const relative = path.relative(adminSrc, target);

    return relative.startsWith('..') || path.isAbsolute(relative) || relative.split(path.sep).includes('node_modules');
}

/**
 * Same result as `discoverComponents(context)`. Files whose content did not change since `previous` are not parsed
 * again, as long as no source file was added or removed.
 */
export function discoverIncrementally(
    context: AnalysisContext,
    previous: DiscoveryState | null,
): { discovered: DiscoveredComponents; state: DiscoveryState | null } {
    const adminSrc = path.join(context.adminRoot, 'src');
    const files = context.sourceTreeFiles.map((file) => path.resolve(file));
    const identities = new Map(files.map((file) => [file, identityOf(context, file)]));
    const listing = sha1(files);
    const key = sha1([
        listing,
        ...identities.values(),
    ]);

    if (previous?.key === key) {
        return { discovered: previous.discovered, state: previous };
    }

    const reusable = previous?.listing === listing ? previous.files : new Map<string, FileScan>();
    const reusableScan = (file: string): FileScan | undefined => {
        const cached = reusable.get(file);

        return cached && cached.identity === identities.get(file) ? cached : undefined;
    };
    const scanned: string[] = [];
    const index = collectComponentSourceIndex(adminSrc, {
        adminSrc,
        readFile: (file) => {
            scanned.push(file);

            // An empty text holds no component, so the scan skips parsing it.
            return reusableScan(file) ? '' : fs.readFileSync(file, 'utf8');
        },
    });
    const scanErrorsByFile = new Map<string, SourceDiagnostic[]>();

    index.diagnostics
        .filter(({ isScanError }) => isScanError)
        .forEach((diagnostic) => {
            scanErrorsByFile.set(diagnostic.file, [...(scanErrorsByFile.get(diagnostic.file) ?? []), diagnostic]);
        });

    const scans = new Map<string, FileScan>();

    for (const file of scanned) {
        scans.set(
            file,
            reusableScan(file) ?? {
                identity: identities.get(file) ?? 'unlisted',
                registrations: index.registrationsByFile.get(file) ?? [],
                component: index.components.get(file),
                scanErrors: scanErrorsByFile.get(file) ?? [],
            },
        );
    }

    const merged: SourceIndex = {
        registrationsByFile: new Map([...scans].map(([file, scan]) => [file, scan.registrations])),
        components: new Map(
            [...scans].flatMap(
                ([file, scan]): Array<[string, ComponentSource]> => (scan.component ? [[file, scan.component]] : []),
            ),
        ),
        diagnostics: [...scans.values()].flatMap(({ scanErrors }) => scanErrors),
    };
    const discovered = discoverComponents(context, merged);
    const reusableNextTime = [...scans].every(
        ([file, scan]) =>
            identities.has(file) && !scan.registrations.some((registration) => resolvesOutside(adminSrc, registration)),
    );

    return {
        discovered,
        state: reusableNextTime ? { listing, key, files: scans, discovered } : null,
    };
}
