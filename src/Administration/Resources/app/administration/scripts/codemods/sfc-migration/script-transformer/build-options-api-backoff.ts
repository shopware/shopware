import type { SourceFile } from 'ts-morph';
import { Project, ScriptKind } from 'ts-morph';
import { findComponentRegistration } from './ast';

export function buildOptionsApiBackoff(sourceFile: SourceFile): string {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });
    // Mutate a clone so callers can still inspect the original AST when they
    // build reports or blockers.
    const clone = project.createSourceFile('component.js', sourceFile.getFullText(), { scriptKind: ScriptKind.JS });

    const templateImport = clone.getImportDeclarations().find((imp) => imp.getDefaultImport()?.getText() === 'template');

    // Backoff SFCs still receive the transformed <template>, so the old Twig
    // import and `template` option would point to stale duplicate markup.
    templateImport?.remove();

    const registration = findComponentRegistration(clone);
    registration?.optionsObject?.getProperty('template')?.remove();

    return clone.getFullText().trim();
}
