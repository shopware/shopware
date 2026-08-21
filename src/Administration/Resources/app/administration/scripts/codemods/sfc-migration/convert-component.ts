/**
 * @sw-package framework
 */

/**
 * The per-component conversion pipeline: twig template + Options API script in, validated native
 * setup SFC out. Pure apart from prettier — the fixture snapshots and the batch runner both go
 * through exactly this function, so what the tests pin is what the CLI writes.
 */

import { collectTemplateIdentifiers } from './template-ast';
import { transformScript } from './transform-script';
import { transformTemplate } from './transform-template';
import { formatSfc, validateSfc } from './validate';

type ConvertInput = {
    jsSource: string;
    twigSource: string;
    componentName: string;
    vuePath: string;
    lang: 'js' | 'ts';
    templateImportRange: { start: number; end: number };
};

/** The one status vocabulary: three the conversion produces, two only the batch runner can. */
type Outcome = 'full' | 'partial' | 'skipped' | 'already-migrated' | 'error';

type ConvertResult = {
    outcome: Outcome;
    reasons: string[];
    sfc: string | null;
};

async function convertComponent(input: ConvertInput): Promise<ConvertResult> {
    const template = transformTemplate(input.twigSource);

    if (template.template === null) {
        return { outcome: 'skipped', reasons: template.blockers, sfc: null };
    }

    // The template runs first because the script transform needs to know which names the markup
    // reads: a member only the template uses still has to end up as a binding.
    const script = transformScript(input.jsSource, input.componentName, {
        templateImportRange: input.templateImportRange,
        templateIdentifiers: collectTemplateIdentifiers(template.template),
    });

    if (script.script === null) {
        return { outcome: 'skipped', reasons: script.reasons, sfc: null };
    }

    const langAttribute = input.lang === 'ts' ? ' lang="ts"' : '';
    const moduleBlock = script.moduleScript
        ? `<script data-sfc-migration-module${langAttribute}>\n${script.moduleScript}\n</script>\n\n`
        : '';
    const rawSfc = `${moduleBlock}<template>\n${template.template.trim()}\n</template>\n\n<script setup${langAttribute}>\n${script.script}\n</script>\n`;

    let formatted: string;

    try {
        formatted = await formatSfc(rawSfc);
    } catch (error) {
        return { outcome: 'skipped', reasons: [`prettier: ${(error as Error).message}`], sfc: null };
    }

    const validationError = validateSfc(formatted, input.vuePath);

    if (validationError !== null) {
        return { outcome: 'skipped', reasons: [`validation: ${validationError}`], sfc: null };
    }

    return {
        outcome: script.reasons.length > 0 ? 'partial' : 'full',
        reasons: script.reasons,
        sfc: formatted,
    };
}

export { convertComponent, type ConvertInput, type ConvertResult, type Outcome };
