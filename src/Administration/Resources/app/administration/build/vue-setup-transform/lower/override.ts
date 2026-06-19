/**
 * @sw-package framework
 */

import {
    fromSource,
    generated,
    indent,
    type SourceChunk,
} from '../source-edits/chunks';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import {
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    getTakenNames,
    makeUniqueName,
} from './shared';

/**
 * Builds the override callback payload from declared replacements and template-used private aliases.
 */
function buildOverrideReturn(analysis: ShopwareSetupScriptAnalysis): string {
    const privateBindings = Array.from(analysis.overridePrivateBindings).map((localName) => String(localName));

    if (analysis.overrideEntries.length === 0 && privateBindings.length === 0) {
        return 'return {};';
    }

    const lines = [
        'return {',
        ...analysis.overrideEntries.map((property) => `    ${property},`),
    ];

    if (privateBindings.length > 0) {
        const privateNamespace = String(analysis.overridePrivateNamespace);

        lines.push(
            '    __swOverride: {',
            `        ${privateNamespace}: {`,
            ...privateBindings.map((localName) => `            ${localName},`),
            '        },',
            '    },',
        );
    }

    lines.push('};');

    return lines.join('\n');
}

/**
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 */
function buildOverrideScript(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): SourceChunk[] {
    const takenNames = getTakenNames(analysis);
    const previousStateName = makeUniqueName('__swPreviousState', takenNames);
    const propsName = makeUniqueName('__swProps', takenNames);
    const contextName = makeUniqueName('__swContext', takenNames);
    const passthroughAttributesSource = block.attributes.toSourceWithoutEnsuringLanguage(
        [
            'setup',
            'sw-component',
            'sw-override',
        ],
        'ts',
    );
    const callbackBody = buildCallbackBodyChunks(block, analysis, null);
    const body = [
        generated(`const useSwPreviousState = () => ${previousStateName};\n`),
        generated(`const useSwProps = () => ${propsName};\n`),
        generated(`const useSwContext = () => ${contextName};\n\n`),
        ...callbackBody,
        generated(`\n\n${buildOverrideReturn(analysis)}`),
    ];
    const chunks: SourceChunk[] = [
        generated(`<script${passthroughAttributesSource}>\n`),
    ];

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock.start, importBlock.end));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    chunks.push(
        generated([
            'export default {',
            '    setup() {',
            `        Shopware.Component.overrideComponentSetup()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        ].join('\n')),
        generated('\n'),
        indent(body, 12),
        generated('\n        });'),
    );

    if (!block.template) {
        chunks.push(generated('\n\n        return () => null;'));
    }

    chunks.push(generated('\n    },\n};\n</script>'));

    return chunks;
}

module.exports = {
    buildOverrideScript,
};

export {
    buildOverrideScript,
};
