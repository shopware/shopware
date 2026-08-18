/**
 * @sw-package framework
 */

/**
 * The Administration's twig layer only ever uses `{% block %}`, `{% parent %}` and `{# comments #}`
 * (verified over all 994 templates), so the conversion is a handful of text replacements; anything
 * else is reported as a blocker instead of being guessed at. The `:data` binding of `<sw-block>` is
 * owned by the Shopware setup transform and must never be authored here.
 *
 * Turning transparent twig blocks into real elements breaks `v-if` chains that span a block
 * boundary, so the twig-free markup goes through `normalize-cross-block-conditionals.ts` last.
 */

import { assertBlockSlots } from './assert-block-slots';
import { hoistBlockSlots } from './hoist-block-slots';
import { normalizeCrossBlockConditionals } from './normalize-cross-block-conditionals';

type TemplateResult = {
    template: string | null;
    blockers: string[];
};

const ESLINT_BLOCK_DISABLE =
    /[^\S\n]*<!--\s*eslint-disable(?:-next-line)?\s+sw-deprecation-rules\/no-twigjs-blocks\s*-->\n?/g;
const TWIG_COMMENT = /\{#([\s\S]*?)#\}/g;
const TWIG_BLOCK_START = /\{%-?\s*block\s+([\w-]+)\s*-?%\}/g;
const TWIG_BLOCK_END = /\{%-?\s*endblock\s*-?%\}/g;
const TWIG_PARENT = /\{\{\s*parent\(\)\s*\}\}|\{%-?\s*parent\s*-?%\}/g;
const TWIG_PARENT_BLOCKER = '{% parent %} needs override output (the codemod only writes base components)';

function transformTemplate(twig: string): TemplateResult {
    // `{% parent %}` only means something in an override template. The codemod always writes
    // `<name>.vue`, which the setup transform reads as a base component, where `<sw-block-parent />`
    // renders nothing and the block name would be claimed from its real owner. `.match()` rather
    // than `.test()`, because the regex is global and `.test()` carries `lastIndex` between calls.
    if (twig.match(TWIG_PARENT)) {
        return { template: null, blockers: [TWIG_PARENT_BLOCKER] };
    }

    const template = twig
        .replace(ESLINT_BLOCK_DISABLE, '')
        .replace(TWIG_COMMENT, '<!--$1-->')
        .replace(TWIG_BLOCK_START, '<sw-block name="$1">')
        .replace(TWIG_BLOCK_END, '</sw-block>');

    const leftoverTwig = template.match(/\{[%#][^\n]*/);

    if (leftoverTwig) {
        return {
            template: null,
            blockers: [`unsupported twig syntax: ${leftoverTwig[0].trim()}`],
        };
    }

    // Runs before the gate below, so a slot the conversion re-parented is repaired rather than refused.
    const hoisted = hoistBlockSlots(template);

    if (hoisted.blockers.length > 0) {
        return { template: null, blockers: hoisted.blockers };
    }

    // Checked before the guard insertion below, so the blocker describes the authored shape.
    const slotBlockers = assertBlockSlots(hoisted.template);

    if (slotBlockers.length > 0) {
        return { template: null, blockers: slotBlockers };
    }

    return normalizeCrossBlockConditionals(hoisted.template);
}

export { transformTemplate, TWIG_PARENT_BLOCKER, type TemplateResult };
