/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as path from 'path';

type FileHandle = number;

/**
 * The byte-oriented filesystem boundary used by the migration writer.
 *
 * Keeping this small makes short writes, permission errors, rename failures, and cleanup failures
 * deterministic in tests without mocking the runner or the process-wide fs module.
 */
type FileOps = {
    readFile(path: string): Buffer;
    exists(path: string): boolean;
    openExclusive(path: string): FileHandle;
    write(handle: FileHandle, bytes: Buffer, offset: number, length: number): number;
    sync(handle: FileHandle): void;
    close(handle: FileHandle): void;
    rename(source: string, target: string): void;
    remove(path: string): void;
};

type MigrationWriteMode = 'draft' | 'replace-originals';

type MigrationWriteInput = {
    vuePath: string;
    indexPath: string;
    twigPath?: string;
    vueBytes: Uint8Array;
    originalIndexBytes: Uint8Array;
    replacementIndexBytes: Uint8Array;
    mode: MigrationWriteMode;
};

type MigrationWriteState = 'draft-written' | 'replacement-ready' | 'replaced' | 'conflict' | 'error';

type MigrationRecoveryState =
    | 'none'
    | 'retry-safe'
    | 'replacement-ready'
    | 'replaced'
    | 'manual-conflict'
    | 'cleanup-pending';

type MigrationWriteStage = 'preflight' | 'vue-write' | 'vue-rename' | 'index-write' | 'index-rename' | 'cleanup';

type MigrationWriteError = {
    stage: MigrationWriteStage;
    path: string;
    message: string;
    temporaryPath?: string;
};

type MigrationWriteResult = {
    state: MigrationWriteState;
    recovery: MigrationRecoveryState;
    changed: boolean;
    vuePath: string;
    indexPath: string;
    twigPath?: string;
    temporaryPaths: string[];
    cleanupFailures: string[];
    error?: MigrationWriteError;
};

type StageResult =
    | { ok: true }
    | {
          ok: false;
          error: MigrationWriteError;
          temporaryPaths: string[];
          cleanupFailures: string[];
      };

type StageFailure = Extract<StageResult, { ok: false }>;

function isStageFailure(result: StageResult): result is StageFailure {
    return result.ok === false;
}

const nodeFileOps: FileOps = {
    readFile: (filePath) => fs.readFileSync(filePath),
    exists: (filePath) => fs.existsSync(filePath),
    openExclusive: (filePath) => fs.openSync(filePath, 'wx'),
    write: (handle, bytes, offset, length) => fs.writeSync(handle, bytes, offset, length),
    sync: (handle) => fs.fsyncSync(handle),
    close: (handle) => fs.closeSync(handle),
    rename: (source, target) => fs.renameSync(source, target),
    remove: (filePath) => fs.unlinkSync(filePath),
};

function getMigrationTemporaryPath(targetPath: string): string {
    return path.join(path.dirname(targetPath), `.${path.basename(targetPath)}.sfc-migration.tmp`);
}

function bytesEqual(left: Buffer, right: Buffer): boolean {
    return left.length === right.length && Buffer.compare(left, right) === 0;
}

function errorMessage(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}

function resultBase(
    input: MigrationWriteInput,
    state: MigrationWriteState,
    recovery: MigrationRecoveryState,
): MigrationWriteResult {
    return {
        state,
        recovery,
        changed: false,
        vuePath: input.vuePath,
        indexPath: input.indexPath,
        twigPath: input.twigPath,
        temporaryPaths: [],
        cleanupFailures: [],
    };
}

function conflict(input: MigrationWriteInput, message: string): MigrationWriteResult {
    return {
        ...resultBase(input, 'conflict', 'manual-conflict'),
        error: {
            stage: 'preflight',
            path: input.vuePath,
            message,
        },
    };
}

function preflightError(input: MigrationWriteInput, filePath: string, error: unknown): MigrationWriteResult {
    return {
        ...resultBase(input, 'error', 'retry-safe'),
        error: {
            stage: 'preflight',
            path: filePath,
            message: errorMessage(error),
        },
    };
}

function stageAndRename(
    fileOps: FileOps,
    targetPath: string,
    bytes: Buffer,
    writeStage: 'vue-write' | 'index-write',
    renameStage: 'vue-rename' | 'index-rename',
): StageResult {
    const temporaryPath = getMigrationTemporaryPath(targetPath);
    let handle: FileHandle | null = null;
    let created = false;
    let stage: 'vue-write' | 'vue-rename' | 'index-write' | 'index-rename' = writeStage;

    try {
        handle = fileOps.openExclusive(temporaryPath);
        created = true;

        let offset = 0;

        while (offset < bytes.length) {
            const written = fileOps.write(handle, bytes, offset, bytes.length - offset);

            if (!Number.isInteger(written) || written <= 0 || written > bytes.length - offset) {
                throw new Error(`invalid write length ${written} at offset ${offset}`);
            }

            offset += written;
        }

        fileOps.sync(handle);
        fileOps.close(handle);
        handle = null;
        stage = renameStage;
        fileOps.rename(temporaryPath, targetPath);

        return { ok: true };
    } catch (error) {
        const cleanupFailures: string[] = [];

        if (handle !== null) {
            try {
                fileOps.close(handle);
            } catch (closeError) {
                cleanupFailures.push(`close ${temporaryPath}: ${errorMessage(closeError)}`);
            }
        }

        if (created) {
            try {
                fileOps.remove(temporaryPath);
            } catch (cleanupError) {
                cleanupFailures.push(`remove ${temporaryPath}: ${errorMessage(cleanupError)}`);
            }
        }

        return {
            ok: false,
            error: {
                stage,
                path: targetPath,
                message: errorMessage(error),
                temporaryPath,
            },
            temporaryPaths: cleanupFailures.length > 0 ? [temporaryPath] : [],
            cleanupFailures,
        };
    }
}

function stageFailure(
    input: MigrationWriteInput,
    stageResult: StageFailure,
    recovery: MigrationRecoveryState,
    changed: boolean,
): MigrationWriteResult {
    return {
        ...resultBase(input, 'error', stageResult.temporaryPaths.length > 0 ? 'cleanup-pending' : recovery),
        changed,
        temporaryPaths: stageResult.temporaryPaths,
        cleanupFailures: stageResult.cleanupFailures,
        error: stageResult.error,
    };
}

/**
 * Writes a validated Vue draft and, only in explicit replacement mode, atomically replaces the
 * legacy entry point. Twig is intentionally never touched.
 */
function writeMigration(input: MigrationWriteInput, fileOps: FileOps = nodeFileOps): MigrationWriteResult {
    const vueBytes = Buffer.from(input.vueBytes);
    const originalIndexBytes = Buffer.from(input.originalIndexBytes);
    const replacementIndexBytes = Buffer.from(input.replacementIndexBytes);

    if (path.resolve(input.vuePath) === path.resolve(input.indexPath)) {
        return conflict(input, 'Vue and index paths must be different.');
    }

    let indexBytes: Buffer;

    if (!fileOps.exists(input.indexPath)) {
        return conflict(input, 'The original index file is missing.');
    }

    try {
        indexBytes = fileOps.readFile(input.indexPath);
    } catch (error) {
        return preflightError(input, input.indexPath, error);
    }

    const indexIsOriginal = bytesEqual(indexBytes, originalIndexBytes);
    const indexIsReplacement = bytesEqual(indexBytes, replacementIndexBytes);

    if (!indexIsOriginal && !indexIsReplacement) {
        return conflict(input, 'The index file differs from both the original and replacement bytes.');
    }

    let vueExists = false;
    let vueIsMatching = false;

    try {
        vueExists = fileOps.exists(input.vuePath);
        vueIsMatching = vueExists && bytesEqual(fileOps.readFile(input.vuePath), vueBytes);
    } catch (error) {
        return preflightError(input, input.vuePath, error);
    }

    if (vueExists && !vueIsMatching) {
        return conflict(input, 'An existing Vue file differs from the validated generated bytes.');
    }

    if (vueIsMatching && indexIsReplacement) {
        return {
            ...resultBase(input, 'replaced', 'replaced'),
        };
    }

    if (vueIsMatching && indexIsOriginal && input.mode === 'draft') {
        return {
            ...resultBase(input, 'replacement-ready', 'replacement-ready'),
        };
    }

    if (!vueExists) {
        const vueStage = stageAndRename(fileOps, input.vuePath, vueBytes, 'vue-write', 'vue-rename');

        if (isStageFailure(vueStage)) {
            return stageFailure(input, vueStage, 'retry-safe', false);
        }

        vueExists = true;
        vueIsMatching = true;

        if (indexIsReplacement || input.mode === 'draft') {
            return {
                ...resultBase(
                    input,
                    indexIsReplacement ? 'replaced' : 'draft-written',
                    indexIsReplacement ? 'replaced' : 'none',
                ),
                changed: true,
            };
        }
    }

    if (input.mode !== 'replace-originals' || !indexIsOriginal) {
        return {
            ...resultBase(input, 'replacement-ready', 'replacement-ready'),
            changed: vueExists && vueIsMatching,
        };
    }

    // Re-read immediately before staging the replacement so a concurrent edit cannot be replaced
    // based only on the initial preflight snapshot.
    try {
        if (!fileOps.exists(input.indexPath) || !bytesEqual(fileOps.readFile(input.indexPath), originalIndexBytes)) {
            return conflict(input, 'The index file changed before replacement started.');
        }
    } catch (error) {
        return preflightError(input, input.indexPath, error);
    }

    const indexStage = stageAndRename(fileOps, input.indexPath, replacementIndexBytes, 'index-write', 'index-rename');

    if (isStageFailure(indexStage)) {
        return stageFailure(input, indexStage, 'replacement-ready', true);
    }

    return {
        ...resultBase(input, 'replaced', 'replaced'),
        changed: true,
    };
}

export {
    getMigrationTemporaryPath,
    nodeFileOps,
    writeMigration,
    type FileOps,
    type MigrationRecoveryState,
    type MigrationWriteError,
    type MigrationWriteInput,
    type MigrationWriteMode,
    type MigrationWriteResult,
    type MigrationWriteStage,
    type MigrationWriteState,
};
