/**
 * @sw-package framework
 */

/**
 * Compatibility adapter for the runner's registration shape. The source model owns all filesystem
 * scanning and Babel traversal; this module only projects the parsed index into legacy maps and
 * keeps the ambiguity/name summaries used by reports.
 */

import {
    collectComponentSourceIndex,
    type ComponentSourceIndex,
    type ComponentSourceIndexOptions,
    type RegistrationKind,
    type SourceDiagnostic,
} from './component-source-model';

type Registration = {
    kind: RegistrationKind;
    name: string;
    parent?: string;
};

type InlineOverride = {
    file: string;
    name: string;
};

type ComponentRegistry = {
    byDir: Map<string, Registration>;
    registrationsByDir: ComponentSourceIndex['registrationsByDir'];
    ambiguousDirs: Set<string>;
    inlineOverrides: InlineOverride[];
    duplicateNames: Set<string>;
    diagnostics: SourceDiagnostic[];
    sourceIndex: ComponentSourceIndex;
};

function collectComponentRegistry(scanRoot: string, options?: ComponentSourceIndexOptions): ComponentRegistry {
    const sourceIndex = collectComponentSourceIndex(scanRoot, options);
    const byDir = new Map<string, Registration>();
    const ambiguousDirs = new Set<string>();
    const dirsByName = new Map<string, Set<string>>();

    for (const [
        dir,
        registrations,
    ] of sourceIndex.registrationsByDir) {
        for (const registration of registrations) {
            const dirs = dirsByName.get(registration.name) ?? new Set<string>();

            dirs.add(dir);
            dirsByName.set(registration.name, dirs);
        }

        if (registrations.length !== 1) {
            ambiguousDirs.add(dir);
            continue;
        }

        const [registration] = registrations;

        byDir.set(dir, {
            kind: registration.kind,
            name: registration.name,
            ...(registration.parent ? { parent: registration.parent } : {}),
        });
    }

    const duplicateNames = new Set<string>();

    for (const [
        name,
        dirs,
    ] of dirsByName) {
        if (dirs.size > 1) {
            duplicateNames.add(name);
        }
    }

    const inlineOverrides = [...sourceIndex.registrationsByFile.values()].flatMap((registrations) =>
        registrations
            .filter((registration) => registration.inline && registration.kind === 'override')
            .map(({ file, name }) => ({ file, name })),
    );

    return {
        byDir,
        registrationsByDir: sourceIndex.registrationsByDir,
        ambiguousDirs,
        inlineOverrides,
        duplicateNames,
        diagnostics: sourceIndex.diagnostics,
        sourceIndex,
    };
}

export { collectComponentRegistry, type ComponentRegistry, type InlineOverride, type Registration, type RegistrationKind };
