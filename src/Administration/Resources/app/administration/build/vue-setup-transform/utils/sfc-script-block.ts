/**
 * @sw-package framework
 */

/**
 * Narrows Vue's SFC script descriptor to the shape the transform works with.
 *
 * Only the block *content* is ever rewritten, so both boundaries come straight from Vue's offsets and
 * the `<script setup ...>` / `</script>` tags are left untouched in the source. That keeps `generic`,
 * `lang` and any other attribute working without the transform having to understand them.
 */

import type { SFCScriptBlock } from '@vue/compiler-sfc';

/**
 * Describes a script block by its content boundaries in the original SFC.
 *
 * `contentStart` also anchors every analyzer range, which is relative to the script body.
 */
type ScriptBlock = {
    type: 'script' | 'scriptSetup';
    contentStart: number;
    contentEnd: number;
    content: string;
    lang: string | null;
};

/**
 * Builds the shared block shape consumed by semantic normalization and lowering.
 */
function toScriptBlock(descriptorBlock: SFCScriptBlock, type: ScriptBlock['type']): ScriptBlock {
    return {
        type,
        contentStart: descriptorBlock.loc.start.offset,
        contentEnd: descriptorBlock.loc.end.offset,
        content: descriptorBlock.content,
        lang: descriptorBlock.lang ?? null,
    };
}

/**
 * @private
 */
export { type ScriptBlock, toScriptBlock };
