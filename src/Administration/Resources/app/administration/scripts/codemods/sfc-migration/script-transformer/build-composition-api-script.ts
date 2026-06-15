import type { ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { collectCompositionScriptState } from './composition-script-state';
import { emitCompositionApiScript } from './emit-composition-api-script';
import type { ComponentRegistration } from './types';

export function buildCompositionApiScript(
    optionsObj: ObjectLiteralExpression,
    registration: ComponentRegistration,
    sourceFile: SourceFile,
): { script: string; publicNames: string[]; manualMigrationReasons: string[] } {
    const state = collectCompositionScriptState(optionsObj, registration, sourceFile);

    return {
        script: emitCompositionApiScript(state),
        publicNames: state.publicNames,
        manualMigrationReasons: [...new Set(state.manualMigrationReasons)],
    };
}
