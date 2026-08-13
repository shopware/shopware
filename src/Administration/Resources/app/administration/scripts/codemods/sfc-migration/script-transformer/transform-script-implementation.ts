import { findComponentRegistration, parseSource } from './ast';
import { buildCompositionApiScript } from './build-composition-api-script';
import { buildOptionsApiBackoff } from './build-options-api-backoff';
import { detectBlockers } from './extract-component-options';
import { analyzeUnsupportedInjectEntries } from './extract-inject';
import { resolveComponentMixins } from './extract-mixins';
import type { TransformScriptResult } from './types';

export function transformScript(jsContent: string, templateReferences: Set<string> = new Set()): TransformScriptResult {
    const sourceFile = parseSource(jsContent);
    const registration = findComponentRegistration(sourceFile);
    const optionsObj = registration?.optionsObject;

    const componentName = registration?.componentName ?? 'unknown-component';

    if (!optionsObj) {
        return {
            script: '',
            scriptType: 'options',
            status: 'not-migratable',
            blockers: ['no options object found'],
            publicNames: [],
            componentName,
        };
    }

    const blockers = detectBlockers(optionsObj, registration);
    const mixinResolution = resolveComponentMixins(optionsObj, sourceFile, templateReferences);
    if (mixinResolution.unresolved.length > 0) {
        // All-or-nothing: any mixin the registry can't resolve keeps the whole
        // component on the safe Options-API backoff.
        blockers.push(...mixinResolution.unresolved);
    }
    const unsupportedInjectAnalysis = analyzeUnsupportedInjectEntries(optionsObj);

    if (blockers.includes('render function')) {
        // render() owns the component output. Combining it with the migrated
        // Twig template would either be ignored by Vue or change rendering
        // semantics, so the component must be rewritten by hand first.
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers, publicNames: [], componentName };
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
            componentName,
        };
    }

    const { script, publicNames, manualMigrationReasons, backoffReasons } = buildCompositionApiScript(
        optionsObj,
        registration,
        sourceFile,
        mixinResolution.descriptors,
        templateReferences,
    );

    if (backoffReasons.length > 0) {
        // The generated setup would reference a renamed composable binding that
        // the template still calls by its original name. The codemod cannot
        // rewrite the template, so keep the safe Options-API backoff.
        return {
            script: buildOptionsApiBackoff(sourceFile),
            scriptType: 'options',
            status: 'partially-migratable',
            blockers: backoffReasons,
            publicNames: [],
            componentName,
        };
    }

    return {
        script,
        scriptType: 'setup',
        status: manualMigrationReasons.length > 0 ? 'partially-migratable' : 'fully-migratable',
        blockers: manualMigrationReasons,
        publicNames,
        componentName,
    };
}
