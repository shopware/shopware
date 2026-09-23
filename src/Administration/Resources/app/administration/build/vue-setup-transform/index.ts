/**
 * @sw-package framework
 */

import MagicString, { type SourceMap } from 'magic-string';
import { lowerBase } from './lower/base';
import { lowerOverride } from './lower/override';
import { analyzeShopwareSetupScript, type ShopwareSetupScriptAnalysis } from './script-analyzer';
import { parseShopwareSetupSfc, type ShopwareSetupBlock } from './sfc-parser';
import { buildSourceMap } from './sourcemap';
import { analyzeBaseTemplate, analyzeOverrideTemplate, type TemplateAnalysis } from './template-analyzer';
import { ShopwareSetupTransformError } from './utils/transform-error';

type ShopwareSetupTransformResult = {
    code: string;
    map: SourceMap;
    mode: 'base' | 'override';
    /** The build integration rejects two SFCs resolving to the same name; this transform sees one file. */
    componentName: string;
    filename: string;
    ownedBlockNames: string[];
    extendedBlockNames: string[];
};

type AnalyzedSfc = {
    block: ShopwareSetupBlock;
    script: ShopwareSetupScriptAnalysis;
    template: TemplateAnalysis;
};

function analyze(source: string, filename: string): AnalyzedSfc | null {
    const block = parseShopwareSetupSfc(source, filename);

    if (!block) {
        return null;
    }

    const script = analyzeShopwareSetupScript(block.content, {
        mode: block.mode,
        lang: block.lang,
        offset: block.contentStart,
    });
    const template = script.mode === 'base' ? analyzeBaseTemplate(block) : analyzeOverrideTemplate(block, script);

    return { block, script, template };
}

/**
 * Lowers a native setup SFC into plain Vue SFC source. Returns `null` when Vue cannot parse the SFC.
 */
function transformShopwareSetupSfc(source: string, filename = 'anonymous.vue'): ShopwareSetupTransformResult | null {
    const analyzed = analyze(source, filename);

    if (!analyzed) {
        return null;
    }

    const { block, script, template } = analyzed;
    const s = new MagicString(source);

    if (script.mode === 'base') {
        lowerBase(s, block, script, template);
    } else {
        lowerOverride(s, block, script, template);
    }

    return {
        code: s.toString(),
        map: buildSourceMap(s, filename),
        mode: block.mode,
        componentName: block.componentName,
        filename,
        ownedBlockNames: template.ownedBlockNames,
        extendedBlockNames: template.extendedBlockNames,
    };
}

/**
 * Throws the errors `transformShopwareSetupSfc` would throw, without generating code or a sourcemap.
 */
function analyzeShopwareSetupSfc(source: string, filename = 'anonymous.vue'): void {
    analyze(source, filename);
}

const validateShopwareSetupSfc = analyzeShopwareSetupSfc;

export {
    COMPONENT_NAME_PATTERN,
    OVERRIDE_LOCAL_STATE_KEY,
    RESERVED_BINDING_PREFIX,
    inferShopwareSetupFromFilename,
    isDependencyFile,
    isReservedBindingName,
} from './naming';

export type { InferredShopwareSetup, ShopwareSetupMode } from './naming';

/**
 * @private
 */
export {
    type ShopwareSetupTransformResult,
    ShopwareSetupTransformError,
    analyzeShopwareSetupSfc,
    transformShopwareSetupSfc,
    validateShopwareSetupSfc,
};
