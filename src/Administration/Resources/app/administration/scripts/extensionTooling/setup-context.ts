/**
 * @sw-package framework
 *
 * Shared generator context for the extension-tooling setup pipeline. Every
 * generation step receives the same mutable {@link GeneratorContext} and books
 * its writes through {@link record}, so the orchestrator can render one
 * portable, project-root-relative view of everything that was (or would be)
 * created, updated, skipped, or deleted.
 */

import path from 'path';
import { relativePosix } from './shared';
import type { ManagedFileState, ManifestFileState, ToolingCommands, WriteResult } from './shared';

export interface GeneratorContext {
    projectRoot: string;
    administrationRoot: string;
    toolingRoot: string;
    dryRun: boolean;
    /** Layout-aware invocations used in generated-file comments and printed guidance. */
    commands: ToolingCommands;
    writes: WriteResult[];
    staleFiles: string[];
    warnings: string[];
    instructions: string[];
}

export function record(context: GeneratorContext, result: WriteResult): ManagedFileState {
    // Reported project-root-relative so every consumer renders portable paths.
    context.writes.push({
        ...result,
        file: path.isAbsolute(result.file) ? relativePosix(context.projectRoot, result.file) : result.file,
    });

    return result.state;
}

export function toManifestState(state: ManagedFileState): ManifestFileState {
    return state === 'conflict' || state === 'skipped' ? state : 'managed';
}
