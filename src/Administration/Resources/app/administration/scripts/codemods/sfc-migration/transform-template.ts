/**
 * @sw-package framework
 */

/**
 * The Administration's twig only uses `{% block %}`, `{% parent %}` and `{# comments #}` (all 994
 * templates), so the conversion is a few text replacements; anything else is a blocker. The `:data`
 * binding of `<sw-block>` belongs to the setup transform and is never authored here.
 */

import { assertBlockSlots } from './assert-block-slots';
import { assertSingleRoot } from './assert-single-root';
import { hoistBlockSlots } from './hoist-block-slots';
import { moveRootTwigCommentsOutOfTemplate, TWIG_COMMENT_MARKER } from './move-root-comments';
import { normalizeCrossBlockConditionals } from './normalize-cross-block-conditionals';

type TemplateResult = {
    template: string | null;
    blockers: string[];
    /** Reasons the draft needs a look; they make it partial. */
    warnings?: string[];
    sfcComments?: string[];
};

const ESLINT_BLOCK_DISABLE =
    /[^\S\n]*<!--\s*eslint-disable(?:-next-line)?\s+sw-deprecation-rules\/no-twigjs-blocks\s*-->\n?/g;
const TWIG_COMMENT = /\{#([\s\S]*?)#\}/g;
// `-->` and `--!>` would end the comment early and spill its tail into markup Vue parses happily;
// splitting the dashes from `>` is the smallest edit that cannot form a terminator.
const HTML_COMMENT_END = /--!?>/g;
const TWIG_BLOCK_START = /\{%-?\s*block\s+([\w-]+)\s*-?%\}/g;
const TWIG_BLOCK_END = /\{%-?\s*endblock\s*-?%\}/g;
const TWIG_PARENT = /\{\{\s*parent\(\)\s*\}\}|\{%-?\s*parent\s*-?%\}/g;
const TWIG_PARENT_BLOCKER = '{% parent %} needs override output (the codemod only writes base components)';

function commentText(body: string): string {
    return body.replace(HTML_COMMENT_END, (terminator) => `-- ${terminator.slice(2)}`);
}

function transformTemplate(twig: string): TemplateResult {
    // `{% parent %}` only means something in an override, and the codemod writes base components.
    // `.match()`, because `.test()` on a global regex carries `lastIndex` between calls.
    if (twig.match(TWIG_PARENT)) {
        return { template: null, blockers: [TWIG_PARENT_BLOCKER] };
    }

    const template = twig
        .replace(ESLINT_BLOCK_DISABLE, '')
        .replace(TWIG_COMMENT, (_match, body: string) => `<!--${TWIG_COMMENT_MARKER}${commentText(body)}-->`)
        .replace(TWIG_BLOCK_START, '<sw-block name="$1">')
        .replace(TWIG_BLOCK_END, '</sw-block>');

    const leftoverTwig = template.match(/\{[%#][^\n]*/);

    if (leftoverTwig) {
        return { template: null, blockers: [`unsupported twig syntax: ${leftoverTwig[0].trim()}`] };
    }

    // Before the slot gate, so a re-parented slot is repaired rather than refused.
    const hoisted = hoistBlockSlots(template);

    if (hoisted.blockers.length > 0) {
        return { template: null, blockers: hoisted.blockers };
    }

    // Before the guard insertion, so the blocker describes the authored shape.
    const slotBlockers = assertBlockSlots(hoisted.template);

    if (slotBlockers.length > 0) {
        return { template: null, blockers: slotBlockers };
    }

    const rooted = moveRootTwigCommentsOutOfTemplate(hoisted.template);
    const normalized = normalizeCrossBlockConditionals(rooted.template);

    if (normalized.template === null) {
        return normalized;
    }

    // Last: the guards the normalization inserts are roots of their own.
    const warnings = assertSingleRoot(rooted.template, normalized.template);

    return {
        template: [...warnings.map(templateTodo), normalized.template].join('\n'),
        blockers: normalized.blockers,
        warnings,
        sfcComments: rooted.sfcComments,
    };
}

function templateTodo(warning: string): string {
    return `<!-- TODO(sfc-migration) VERIFY: ${warning}. Give the twig a single top-level block to restore it. -->`;
}

export { transformTemplate, TWIG_PARENT_BLOCKER, type TemplateResult };
