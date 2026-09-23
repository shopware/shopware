/**
 * @sw-package framework
 */

/** One component through the pipeline; the snapshots and the CLI share it, so tests pin what is written. */

import { collectTemplateComponentTags, collectTemplateIdentifiers } from './template-ast';
import { transformScript } from './transform-script';
import { transformTemplate } from './transform-template';
import { formatModule, formatSfc, validateSfc } from './validate';

type ConvertInput = {
    jsSource: string;
    twigSource: string;
    componentName: string;
    vuePath: string;
    lang: 'js' | 'ts';
    templateImportRange: { start: number; end: number };
};

/** Three outcomes the conversion produces, two only the batch runner can. */
type Outcome = 'full' | 'partial' | 'skipped' | 'already-migrated' | 'error';

type ConvertResult = {
    outcome: Outcome;
    reasons: string[];
    sfc: string | null;
    /** The module-level code the SFC imports, written next to it. */
    module: { fileName: string; source: string } | null;
};

function skipped(reasons: string[]): ConvertResult {
    return { outcome: 'skipped', reasons, sfc: null, module: null };
}

async function convertComponent(input: ConvertInput): Promise<ConvertResult> {
    const template = transformTemplate(input.twigSource);

    if (template.template === null) {
        return skipped(template.blockers);
    }

    // Template first: a member only the markup reads still needs a binding.
    const moduleBasename = `${input.componentName}.module`;
    const script = transformScript(input.jsSource, input.componentName, {
        templateImportRange: input.templateImportRange,
        templateIdentifiers: collectTemplateIdentifiers(template.template),
        templateComponentTags: collectTemplateComponentTags(template.template),
        moduleSpecifier: `./${moduleBasename}`,
    });

    if (script.script === null) {
        return skipped(script.reasons);
    }

    const langAttribute = input.lang === 'ts' ? ' lang="ts"' : '';
    const commentBlock = template.sfcComments?.length ? `${template.sfcComments.join('\n')}\n` : '';
    const rawSfc = `${commentBlock}<template>\n${template.template.trim()}\n</template>\n\n<script setup${langAttribute}>\n${script.script}\n</script>\n`;

    let formatted: string;
    let sibling: ConvertResult['module'] = null;

    try {
        formatted = await formatSfc(rawSfc);

        if (script.moduleScript !== null) {
            sibling = {
                fileName: `${moduleBasename}.${input.lang}`,
                source: await formatModule(script.moduleScript, input.lang),
            };
        }
    } catch (error) {
        return skipped([`prettier: ${(error as Error).message}`]);
    }

    const validationError = validateSfc(formatted, input.vuePath, `./${moduleBasename}`);

    if (validationError !== null) {
        return skipped([`validation: ${validationError}`]);
    }

    const reasons = [...(template.warnings ?? []), ...script.reasons];

    return { outcome: reasons.length > 0 ? 'partial' : 'full', reasons, sfc: formatted, module: sibling };
}

export { convertComponent, type ConvertInput, type ConvertResult, type Outcome };
