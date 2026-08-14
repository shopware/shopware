/**
 * @sw-package framework
 */

/**
 * The per-component conversion pipeline: twig template + Options API script in, validated native
 * setup SFC out. Pure apart from prettier — the fixture snapshots and the batch runner both go
 * through exactly this function, so what the tests pin is what the CLI writes.
 */

import { transformScript } from './transform-script';
import { transformTemplate } from './transform-template';
import { formatSfc, validateSfc } from './validate';

type ConvertInput = {
    jsSource: string;
    twigSource: string;
    componentName: string;
    vuePath: string;
    lang: 'js' | 'ts';
};

type ConvertResult = {
    outcome: 'full' | 'partial' | 'skipped';
    reasons: string[];
    sfc: string | null;
};

async function convertComponent(input: ConvertInput): Promise<ConvertResult> {
    const template = transformTemplate(input.twigSource);

    if (template.template === null) {
        return { outcome: 'skipped', reasons: template.blockers, sfc: null };
    }

    const script = transformScript(input.jsSource, input.componentName);

    if (script.script === null) {
        return { outcome: 'skipped', reasons: script.blockers, sfc: null };
    }

    const langAttribute = input.lang === 'ts' ? ' lang="ts"' : '';
    const rawSfc = `<template>\n${template.template.trim()}\n</template>\n\n<script setup${langAttribute}>\n${script.script}\n</script>\n`;

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
        outcome: script.todos.length > 0 ? 'partial' : 'full',
        reasons: script.todos,
        sfc: formatted,
    };
}

export { convertComponent, type ConvertInput, type ConvertResult };
