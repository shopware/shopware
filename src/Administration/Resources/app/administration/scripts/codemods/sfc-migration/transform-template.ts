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
 * boundary, so the twig-free markup goes through `move-root-comments.ts` and then
 * `normalize-cross-block-conditionals.ts`, and `assert-single-root.ts` reads the finished markup
 * last — the guards that normalization inserts are roots of their own, so the root tally is only
 * correct once they exist.
 */

import { assertBlockSlots } from './assert-block-slots';
import { assertSingleRoot } from './assert-single-root';
import { hoistBlockSlots } from './hoist-block-slots';
import { moveRootCommentsIntoBlock } from './move-root-comments';
import { normalizeCrossBlockConditionals } from './normalize-cross-block-conditionals';

type TemplateResult = {
    template: string | null;
    blockers: string[];
    /** Reasons the template still converts but the draft needs a look; they downgrade it to partial. */
    warnings?: string[];
};

const ESLINT_BLOCK_DISABLE =
    /[^\S\n]*<!--\s*eslint-disable(?:-next-line)?\s+sw-deprecation-rules\/no-twigjs-blocks\s*-->\n?/g;
const TWIG_COMMENT = /\{#([\s\S]*?)#\}/g;
// `-->` and `--!>` both close an HTML comment, so a twig comment carrying either would end early
// and spill its tail into rendered markup — output Vue parses without complaint, which puts it past
// the validation gate. Separating the dashes from the `>` is the smallest edit that cannot form a
// terminator; comments carry no behaviour, so altering one beats refusing the component over it.
const HTML_COMMENT_END = /--!?>/g;
const TWIG_BLOCK_START = /\{%-?\s*block\s+([\w-]+)\s*-?%\}/g;
const TWIG_BLOCK_END = /\{%-?\s*endblock\s*-?%\}/g;
const TWIG_PARENT = /\{\{\s*parent\(\)\s*\}\}|\{%-?\s*parent\s*-?%\}/g;
const TWIG_PARENT_BLOCKER = '{% parent %} needs override output (the codemod only writes base components)';

/** A twig comment body as HTML comment text that cannot terminate the comment before its end. */
function commentText(body: string): string {
    return body.replace(HTML_COMMENT_END, (terminator) => `-- ${terminator.slice(2)}`);
}

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
        .replace(TWIG_COMMENT, (_match, body: string) => `<!--${commentText(body)}-->`)
        .replace(TWIG_BLOCK_START, '<sw-block name="$1">')
        .replace(TWIG_BLOCK_END, '</sw-block>');

    const leftoverTwig = template.match(/\{[%#][^\n]*/);

    if (leftoverTwig) {
        return { template: null, blockers: [`unsupported twig syntax: ${leftoverTwig[0].trim()}`] };
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

    // A twig comment rendered nothing; the HTML comment it becomes is a root node in a development
    // build, which costs the component its element root.
    const rooted = moveRootCommentsIntoBlock(hoisted.template);
    const normalized = normalizeCrossBlockConditionals(rooted);

    if (normalized.template === null) {
        return normalized;
    }

    // Last, so the guards the normalization inserts count towards the root tally like any other node.
    const warnings = assertSingleRoot(rooted, normalized.template);

    return {
        template: [
            ...warnings.map(templateTodo),
            normalized.template,
        ].join('\n'),
        blockers: normalized.blockers,
        warnings,
    };
}

/** A template-level note, in the same shape the script transform uses for its own TODOs. */
function templateTodo(warning: string): string {
    return `<!-- TODO(sfc-migration) VERIFY: ${warning}. Give the twig a single top-level block to restore it. -->`;
}

export { transformTemplate, TWIG_PARENT_BLOCKER, type TemplateResult };
