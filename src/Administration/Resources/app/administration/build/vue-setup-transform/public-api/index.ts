/**
 * @sw-package framework
 */

/**
 * Keeps the `public` lists in a mini-module's `index.ts` equal to what its native-setup component declares.
 *
 * Runs inside the transform, so every build and every Jest run checks it with the current `index.ts`.
 * A lint rule could not: its cache only sees the file it lints.
 */

import fs from 'node:fs';
import type { BaseSetupScriptAnalysis } from '../script-analyzer';
import { parseScript } from '../script-analyzer/utils';
import type { TemplateAnalysis } from '../template-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { collectComponentApi } from './component-api';
import {
    type NativeSetupApiKind,
    type PublicApiLists,
    type ReadFile,
    NATIVE_SETUP_API_KINDS,
    findPublicComponentDeclaration,
} from './declaration';

/** Where the component side of each kind is declared, for the error message. */
const COMPONENT_SOURCE: Record<NativeSetupApiKind, string> = {
    props: 'defineProps() or defineModel()',
    emits: 'defineEmits() or defineModel()',
    slots: 'a <slot> or defineSlots()',
    blocks: 'an <sw-block name>',
    setup: 'swDefinePublic()',
};

/**
 * Reads a file from disk, or returns null when it does not exist.
 */
const readFileFromDisk: ReadFile = (file) => (fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : null);

/**
 * One line per name that only one side has, e.g. `- props "label": declared by ..., missing in index.ts`.
 */
function describeDrift(declared: PublicApiLists, listed: PublicApiLists): string[] {
    return NATIVE_SETUP_API_KINDS.flatMap((kind) => {
        const declaredNames = new Set(declared[kind]);
        const listedNames = new Set(listed[kind]);
        const notListed = [...declaredNames]
            .filter((name) => !listedNames.has(name))
            .map((name) => `- ${kind} "${name}": declared by ${COMPONENT_SOURCE[kind]}, missing in index.ts public.${kind}`);
        const notDeclared = [...listedNames]
            .filter((name) => !declaredNames.has(name))
            .map((name) => `- ${kind} "${name}": listed in index.ts public.${kind}, missing in ${COMPONENT_SOURCE[kind]}`);

        return [
            ...notListed,
            ...notDeclared,
        ];
    });
}

/**
 * Fails when the base SFC is the declared component of a mini-module and its `index.ts` lists a different
 * public API. Returns the `index.ts` it compared against, so a watcher can re-run the check when it changes.
 *
 * @example
 * // index.ts lists public.setup ['reset'], the SFC has swDefinePublic({ reset, save })
 * assertPublicApiMatchesIndex(...);
 * // throws: - setup "save": declared by swDefinePublic(), missing in index.ts public.setup
 */
function assertPublicApiMatchesIndex(input: {
    block: ShopwareSetupBlock;
    analysis: BaseSetupScriptAnalysis;
    templateAnalysis: TemplateAnalysis;
    filename: string;
    readFile?: ReadFile;
}): string | null {
    const { block, analysis, templateAnalysis, filename } = input;
    const declaration = findPublicComponentDeclaration(filename, input.readFile ?? readFileFromDisk);

    if (!declaration) {
        return null;
    }

    const declared = collectComponentApi({
        ast: parseScript(block.content, block.lang ?? 'js', block.contentStart),
        script: block.content,
        filename,
        scriptOffset: block.contentStart,
        publicEntries: analysis.publicEntries,
        blockNames: templateAnalysis.ownedBlockNames,
        slotNames: templateAnalysis.slotNames,
    });
    const drift = describeDrift(declared, declaration.lists);

    if (drift.length === 0) {
        return declaration.indexFile;
    }

    const marker = analysis.markerStatements[0];

    throw new ShopwareSetupTransformError(
        [
            `The public API in index.ts does not match "${block.componentName}":`,
            ...drift,
            '',
            `index.ts:  ${declaration.indexFile}`,
            `component: ${filename}`,
        ].join('\n'),
        marker ? { index: block.contentStart + marker.start, endIndex: block.contentStart + marker.end } : null,
    );
}

/**
 * @private
 */
export { type ReadFile, assertPublicApiMatchesIndex };
