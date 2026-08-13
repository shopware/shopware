import type { ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { collectCompositionScriptState } from './composition-script-state';
import { emitCompositionApiScript } from './emit-composition-api-script';
import type { ComposableDescriptor } from './composable-registry';
import type { ComponentRegistration } from './types';

export function buildCompositionApiScript(
    optionsObj: ObjectLiteralExpression,
    registration: ComponentRegistration,
    sourceFile: SourceFile,
    mixinDescriptors: ComposableDescriptor[] = [],
    templateReferences: Set<string> = new Set(),
): { script: string; publicNames: string[]; manualMigrationReasons: string[]; backoffReasons: string[] } {
    const state = collectCompositionScriptState(
        optionsObj,
        registration,
        sourceFile,
        mixinDescriptors,
        templateReferences,
    );

    return {
        script: emitCompositionApiScript(state),
        publicNames: state.publicNames,
        manualMigrationReasons: [...new Set(state.manualMigrationReasons)],
        backoffReasons: state.templateBindingCollisions.map(
            (member) =>
                `mixins: template reads '${member}' but its composable binding collides with an existing name and cannot be renamed in the template`,
        ),
    };
}
