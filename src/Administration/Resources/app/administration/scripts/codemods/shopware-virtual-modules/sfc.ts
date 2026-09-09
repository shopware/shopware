/**
 * @sw-package framework
 *
 * Applies a source transform to the `<script>` blocks of a single-file component and leaves everything
 * else byte-identical.
 *
 * Each block is transformed on its own, so an import lands in the block that needs it. Templates are
 * never touched: an Options API template has no access to module imports.
 */

import { parse } from '@vue/compiler-sfc';
import MagicString from 'magic-string';
import type { TransformResult } from './transform';

type BlockTransform = (code: string, fileName: string) => TransformResult;

/** The virtual file name a block's content is parsed under, which is what selects TS or JS parsing. */
function blockFileName(fileName: string, lang: string | undefined, index: number): string {
    return `${fileName}.block${index}.${lang === 'ts' ? 'ts' : 'js'}`;
}

export function transformSfc(code: string, fileName: string, transform: BlockTransform): TransformResult {
    const { descriptor, errors } = parse(code, { filename: fileName });

    if (errors.length > 0) {
        return {
            code,
            rewrites: [],
            skips: [{ expression: fileName, reason: 'the SFC does not parse', specifier: 'shopware:*' }],
        };
    }

    const blocks = [
        descriptor.script,
        descriptor.scriptSetup,
    ].filter((block): block is NonNullable<typeof block> => block !== null);

    const magic = new MagicString(code);
    const rewrites: TransformResult['rewrites'] = [];
    const skips: TransformResult['skips'] = [];
    let changed = false;

    blocks.forEach((block, index) => {
        const result = transform(block.content, blockFileName(fileName, block.lang, index));

        rewrites.push(...result.rewrites);
        skips.push(...result.skips);

        if (result.rewrites.length === 0) {
            return;
        }

        magic.overwrite(block.loc.start.offset, block.loc.end.offset, result.code);
        changed = true;
    });

    return { code: changed ? magic.toString() : code, rewrites, skips };
}
