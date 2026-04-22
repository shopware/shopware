/**
 * @sw-package framework
 * @private
 *
 * Walks a TwigJS parsed token tree and reconstructs a Vue-compatible HTML
 * template string. TwigJS is used exclusively as a parser here — its renderer
 * is never invoked — so Vue directives, interpolations and HTML attributes
 * survive verbatim inside raw tokens.
 *
 * Known limitation: Twig control-flow tags ({% if %}, {% for %}, …) are not
 * supported as block content and collapse to an empty string. This matches the
 * existing Shopware admin contract where only {% block %} and {% parent %} are
 * valid inside component templates.
 */

/**
 * Mirrors the TwigJS internal token structure. This shape is not part of a
 * public TwigJS API contract and must be re-validated whenever the `twig`
 * package is upgraded. TwigJS ships no stable TypeScript types; this
 * definition is derived from observed runtime token output.
 *
 * @private
 */
export type TwigToken = {
    type: 'raw' | 'logic';
    /** Value of a raw text token — the verbatim HTML/Vue template fragment. */
    value?: string;
    token?: {
        /**
         * Logic type string. For the custom `{% parent %}` tag registered via
         * `extendTag({ type: 'parent' })`, this is `'parent'`. For built-in
         * Twig logic tags the value is `'Twig.logic.type.<tag>'`.
         */
        type?: 'parent' | (string & {});
        /**
         * Present on `{% block name %}` tokens — contains the block's name.
         * This is how template.factory.js identifies block tokens.
         */
        blockName?: string;
        output?: TwigToken[];
    };
};

/**
 * Stringifies a TwigJS token array into a Vue-compatible HTML fragment.
 * Converts `{% parent %}` to `<sw-block-parent />`, recurses into nested
 * `{% block %}` tokens, and collapses unsupported control-flow tags
 * (`{% if %}`, `{% for %}`, …) to empty strings.
 *
 * @private
 */
export default function reconstructInnerTemplate(tokens: TwigToken[]): string {
    return tokens
        .map((token) => {
            if (token.type === 'raw') {
                return token.value ?? '';
            }

            if (token.type === 'logic') {
                if (token.token?.type === 'parent') {
                    return '<sw-block-parent />';
                }

                if (token.token?.blockName !== undefined) {
                    const innerContent = reconstructInnerTemplate(token.token.output ?? []);
                    return `<sw-block name="${token.token.blockName}">${innerContent}</sw-block>`;
                }
            }

            return '';
        })
        .join('');
}
