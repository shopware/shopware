/**
 * @sw-package framework
 * @private
 *
 * Block index for the Twig → Native Block Runtime Adapter.
 *
 * Populated synchronously whenever `async-component.factory.ts` processes a
 * `Shopware.Component.override()` call that carries a Twig template string.
 * At render time `sw-block` does a single Map lookup — O(1) — and injects the
 * pre-built shim slots without any additional parsing.
 *
 * TwigJS is imported here for parsing only. The global TwigJS singleton is
 * already configured by `template.factory.js` (output tokens filtered,
 * `{% parent %}` tag registered) before this module is first used.
 */

// eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
import TwigLib from 'twig';
import { reconstructInnerTemplate, containsParentToken } from './reconstruct-twig-template';

type TwigParsedToken = {
    type: string;
    token?: {
        blockName?: string;
        output?: Parameters<typeof reconstructInnerTemplate>[0];
    };
};

type TwigInstance = {
    twig: (options: { data: string; rethrow: boolean }) => { tokens: TwigParsedToken[] };
};

// eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
const Twig = TwigLib as unknown as TwigInstance;

/**
 * @private
 */
export interface BlockEntry {
    componentName: string;
    innerTemplate: string;
    hasParent: boolean;
}

const blockIndex = new Map<string, BlockEntry[]>();

/**
 * Parses `rawTemplate` with TwigJS and indexes every top-level `{% block %}`
 * found. Called synchronously from `override()` before the template string is
 * handed to `TemplateFactory`.
 *
 * Silently ignores malformed templates — TwigJS will surface the error again
 * later through the normal template pipeline if needed.
 *
 * @private
 */
export function indexTwigBlocksFromTemplate(componentName: string, rawTemplate: string): void {
    let parsed: { tokens: TwigParsedToken[] };

    try {
        parsed = Twig.twig({ data: rawTemplate, rethrow: true });
    } catch {
        return;
    }

    parsed.tokens
        .filter((token) => token.type === 'logic' && !!token.token?.blockName)
        .forEach((token) => {
            const blockName = token.token!.blockName as string;
            const output = token.token!.output ?? [];

            const innerTemplate = reconstructInnerTemplate(output);
            const hasParent = containsParentToken(output);

            const existing = blockIndex.get(blockName) ?? [];
            existing.push({ componentName, innerTemplate, hasParent });
            blockIndex.set(blockName, existing);
        });
}

/**
 * @private
 */
export function getBlockEntries(blockName: string): BlockEntry[] {
    return blockIndex.get(blockName) ?? [];
}

/**
 * @private
 */
export function hasBlockEntries(blockName: string): boolean {
    return blockIndex.has(blockName);
}

/**
 * Clears the block index. Exposed for test teardown only — do not call in
 * production code.
 *
 * @private
 */
export function resetBlockIndex(): void {
    blockIndex.clear();
}
