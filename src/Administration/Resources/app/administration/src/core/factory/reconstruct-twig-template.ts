/**
 * @sw-package framework
 * @private
 *
 * Rebuilds the Vue template of a parsed Twig block without running the TwigJS renderer, so directives,
 * interpolations and attributes survive verbatim in the raw tokens. Twig control-flow tags (`{% if %}`,
 * `{% for %}`, …) are not supported inside blocks and are dropped.
 */

/**
 * The part of the TwigJS token tree this relies on. TwigJS has no typed or documented token API, so this must be
 * re-checked on every `twig` upgrade.
 *
 * @private
 */
export type TwigToken = {
    type: 'raw' | 'logic';
    value?: string;
    token?: {
        /** `'parent'` for the `{% parent %}` tag registered in `template.factory.js`. */
        type?: 'parent' | (string & {});
        blockName?: string;
        output?: TwigToken[];
    };
};

/**
 * `{% parent %}` becomes `<sw-block-parent />`, a nested `{% block %}` becomes a `<sw-block name>`.
 *
 * @private
 */
export default function reconstructInnerTemplate(tokens: TwigToken[]): string {
    return tokens
        .map((token) => {
            if (token.type === 'raw') {
                return token.value ?? '';
            }

            if (token.token?.type === 'parent') {
                return '<sw-block-parent />';
            }

            if (token.token?.blockName !== undefined) {
                return `<sw-block name="${token.token.blockName}">${reconstructInnerTemplate(token.token.output ?? [])}</sw-block>`;
            }

            return '';
        })
        .join('');
}
