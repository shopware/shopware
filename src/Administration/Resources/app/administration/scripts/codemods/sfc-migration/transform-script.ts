import { findComponentRegistration, parseSource } from './script-transformer/ast';
import { collectCompositionScriptState } from './script-transformer/composition-script-state';
import { emitCompositionApiScript } from './script-transformer/emit-composition-api-script';
import { detectBlockers } from './script-transformer/extract-component-options';
import { analyzeUnsupportedInjectEntries } from './script-transformer/extract-inject';
import { UNKNOWN_COMPONENT_NAME } from './types';
import type { TransformScriptResult } from './script-transformer/types';

export type { TransformScriptResult } from './script-transformer/types';
export type { MigrationStatus } from './types';

function notMigratable(blockers: string[], componentName: string): TransformScriptResult {
    return {
        script: '',
        status: 'not-migratable',
        blockers,
        publicNames: [],
        componentName,
        rootElementRefName: null,
    };
}

export interface TransformScriptOptions {
    /**
     * true when the template has an element the generated root ref can be placed
     * on. Only then can `this.$el` be rewritten into a real template ref; the
     * name the transform picked comes back as `rootElementRefName`.
     */
    canHostRootElementRef?: boolean;
}

export function transformScript(jsContent: string, options: TransformScriptOptions = {}): TransformScriptResult {
    const sourceFile = parseSource(jsContent);
    const registration = findComponentRegistration(sourceFile);
    const optionsObj = registration?.optionsObject;

    const componentName = registration?.componentName ?? UNKNOWN_COMPONENT_NAME;

    if (!optionsObj) {
        return notMigratable(['no options object found'], componentName);
    }

    const blockers = detectBlockers(optionsObj, registration);
    const unsupportedInjectAnalysis = analyzeUnsupportedInjectEntries(optionsObj);

    // A blocker means the component cannot become a native setup component, and a
    // `.vue` file that is not one is rejected by the build. So there is nothing to
    // emit — the blockers are the result. Examples: `render()` owns the component
    // output; mixins and `extend()` keep their options in another file; an
    // unsupported `inject` shape leaves `this.<injectName>` unresolvable.
    if (blockers.length > 0 || unsupportedInjectAnalysis.reasons.length > 0) {
        return notMigratable(
            [
                ...blockers,
                ...unsupportedInjectAnalysis.reasons,
            ],
            componentName,
        );
    }

    const state = collectCompositionScriptState(optionsObj, registration, sourceFile, {
        canHostRootElementRef: options.canHostRootElementRef,
    });
    const script = emitCompositionApiScript(state);
    const manualMigrationReasons = [...new Set(state.manualMigrationReasons)];

    return {
        script,
        status: manualMigrationReasons.length > 0 ? 'partially-migrated' : 'fully-migrated',
        blockers: manualMigrationReasons,
        publicNames: state.publicNames,
        componentName,
        rootElementRefName: state.rootElementRefName,
    };
}
