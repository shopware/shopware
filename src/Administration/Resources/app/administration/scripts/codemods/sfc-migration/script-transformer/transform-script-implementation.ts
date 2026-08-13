import { findComponentRegistration, parseSource } from './ast';
import { buildCompositionApiScript } from './build-composition-api-script';
import { detectBlockers } from './extract-component-options';
import { analyzeUnsupportedInjectEntries } from './extract-inject';
import { UNKNOWN_COMPONENT_NAME } from '../types';
import type { TransformScriptResult } from './types';

function notMigratable(blockers: string[], componentName: string): TransformScriptResult {
    return { script: '', status: 'not-migratable', blockers, publicNames: [], componentName };
}

export function transformScript(jsContent: string): TransformScriptResult {
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

    const { script, publicNames, manualMigrationReasons } = buildCompositionApiScript(optionsObj, registration, sourceFile);

    return {
        script,
        status: manualMigrationReasons.length > 0 ? 'partially-migrated' : 'fully-migrated',
        blockers: manualMigrationReasons,
        publicNames,
        componentName,
    };
}
