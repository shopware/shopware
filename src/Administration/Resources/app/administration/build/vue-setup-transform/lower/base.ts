/**
 * @sw-package framework
 */

import { ShopwareSetupTransformError } from '../utils/transform-error';
import { fromSource, generated, indent, type SourceChunk } from '../source-edits/chunks';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import {
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    formatObjectProperties,
    getTakenNames,
    makeUniqueName,
} from './shared';

/**
 * Builds the base callback return object split into public and private state.
 */
function buildBaseReturn(analysis: ShopwareSetupScriptAnalysis): string {
    const publicLocalNames = new Set(analysis.publicEntries);
    const privateProperties = analysis.runtimeBindings
        .filter((binding) => !publicLocalNames.has(binding.name))
        .map((binding) => binding.name);

    if (analysis.runtimeBindings.length === 0) {
        throw new ShopwareSetupTransformError(
            'A base Shopware setup block must declare at least one top-level runtime binding.',
            0,
        );
    }

    return [
        'return {',
        `    public: ${formatObjectProperties(analysis.publicEntries, 8)},`,
        `    private: ${formatObjectProperties(privateProperties, 8)},`,
        '};',
    ].join('\n');
}

/**
 * Lowers base mode into the existing extendable setup runtime bridge.
 */
function buildBaseScript(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): SourceChunk[] {
    const takenNames = getTakenNames(analysis);
    const setupPropsName = makeUniqueName('__shopwareProps', takenNames);
    const setupContextName = makeUniqueName('__shopwareContext', takenNames);
    const propsName = analysis.propsMacro ? makeUniqueName('props', takenNames) : null;
    const emitName = analysis.emitsMacro ? makeUniqueName('emit', takenNames) : null;
    const slotsName = analysis.slotsMacro ? makeUniqueName('slots', takenNames) : null;
    const destructureEntries = [
        ...analysis.runtimeBindings.map((binding) => binding.name),
        '__swOverride',
    ];
    const callbackBody = buildCallbackBodyChunks(block, analysis, {
        props: setupPropsName,
        context: setupContextName,
    });
    const body = [
        generated(`const useSwContext = () => ${setupContextName};\n\n`),
        ...callbackBody,
        generated(`\n\n${buildBaseReturn(analysis)}`),
    ];
    const chunks: SourceChunk[] = [
        generated(`<script${block.generatedPassthroughAttributesSource}>\n`),
    ];

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    analysis.typeDeclarations.forEach((typeDeclaration) => {
        chunks.push(fromSource(block, typeDeclaration));
        chunks.push(generated('\n'));
    });

    if (analysis.typeDeclarations.length > 0) {
        chunks.push(generated('\n'));
    }

    if (analysis.propsMacro) {
        chunks.push(generated(`const ${propsName} = `));
        chunks.push(fromSource(block, analysis.propsMacro.ranges[0]));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.emitsMacro) {
        chunks.push(generated(`const ${emitName} = `));
        chunks.push(fromSource(block, analysis.emitsMacro.ranges[0]));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.slotsMacro) {
        chunks.push(generated(`const ${slotsName} = `));
        chunks.push(fromSource(block, analysis.slotsMacro.ranges[0]));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.optionsMacro) {
        chunks.push(fromSource(block, analysis.optionsMacro.ranges[0]));
        chunks.push(generated(';\n\n'));
    }

    chunks.push(
        generated(
            [
                'const {',
                ...destructureEntries.map((entry) => `    ${entry},`),
                '} = Shopware.Component.createExtendableSetup(',
                '    {',
                `        name: '${escapeSingleQuoted(block.componentName)}',`,
                `        props: ${propsName ?? '{}'},`,
                '    },',
                `    (${setupPropsName}, ${setupContextName}) => {`,
            ].join('\n'),
        ),
        generated('\n'),
        indent(body, 8),
        generated('\n    },\n);\n</script>'),
    );

    return chunks;
}

module.exports = {
    buildBaseScript,
};

export { buildBaseScript };
