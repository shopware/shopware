import { findComponentRegistration, parseSource } from './ast';
import { buildCompositionApiScript } from './build-composition-api-script';
import { buildOptionsApiBackoff } from './build-options-api-backoff';
import { detectBlockers } from './extract-component-options';
import { analyzeUnsupportedInjectEntries } from './extract-inject';
import type { TransformScriptResult } from './types';

export function transformScript(jsContent: string): TransformScriptResult {
    const sourceFile = parseSource(jsContent);
    const registration = findComponentRegistration(sourceFile);
    const optionsObj = registration?.optionsObject;

    if (!optionsObj) {
        return {
            script: '',
            scriptType: 'options',
            status: 'not-migratable',
            blockers: ['no options object found'],
            publicNames: [],
        };
    }

    const blockers = detectBlockers(optionsObj, registration);
    const unsupportedInjectAnalysis = analyzeUnsupportedInjectEntries(optionsObj);

    if (blockers.includes('render function')) {
        // render() owns the component output. Combining it with the migrated
        // Twig template would either be ignored by Vue or change rendering
        // semantics, so the component must be rewritten by hand first.
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers, publicNames: [] };
    }

    if (blockers.length > 0 || unsupportedInjectAnalysis.reasons.length > 0) {
        // Unsupported inject shapes are a full backoff case: methods may depend
        // on `this.<injectName>`, and converting only the supported pieces would
        // leave unresolved instance access inside setup code.
        return {
            script: buildOptionsApiBackoff(sourceFile),
            scriptType: 'options',
            status: 'partially-migratable',
            blockers: [
                ...blockers,
                ...unsupportedInjectAnalysis.reasons,
            ],
            publicNames: [],
        };
    }

    const { script, publicNames, manualMigrationReasons } = buildCompositionApiScript(
        optionsObj,
        registration,
        sourceFile,
    );
    return {
        script,
        scriptType: 'setup',
        status: manualMigrationReasons.length > 0 ? 'partially-migratable' : 'fully-migratable',
        blockers: manualMigrationReasons,
        publicNames,
    };
}
