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

type TwigToken = {
    type: 'raw' | 'logic';
    /** Value of a raw text token — the verbatim HTML/Vue template fragment. */
    value?: string;
    token?: {
        /**
         * Logic type string. For the custom `{% parent %}` tag registered via
         * `extendTag({ type: 'parent' })`, this is `'parent'`. For built-in
         * Twig logic tags the value is `'Twig.logic.type.<tag>'`.
         */
        type?: string;
        /**
         * Present on `{% block name %}` tokens — contains the block's name.
         * This is how template.factory.js identifies block tokens.
         */
        blockName?: string;
        output?: TwigToken[];
    };
};

/**
 * Reconstructs the inner Vue-compatible template string from a TwigJS token array.
 *
 * - `raw` tokens pass through verbatim (HTML, Vue directives, {{ }} interpolation).
 * - `logic` tokens with `token.type === 'parent'` become `<sw-block-parent />`.
 *   (The `{% parent %}` custom tag is registered with `type: 'parent'` via
 *   `Twig.extendTag` in `template.factory.js`; TwigJS stores this type verbatim.)
 * - `logic` tokens that have a `blockName` property are nested `{% block %}` tags;
 *   recurse into their `output` array.
 * - All other logic tokens (if, for, set, …) collapse to `''` (known limitation).
 *
 * @private
 */
export function reconstructInnerTemplate(tokens: TwigToken[]): string {
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
                    return reconstructInnerTemplate(token.token.output ?? []);
                }
            }

            return '';
        })
        .join('');
}

/**
 * Returns true when any token in the array (at any nesting depth inside nested
 * `{% block %}` tags) is a `{% parent %}` token.
 *
 * Not consumed at runtime — kept as a public utility for future tooling
 * (e.g. migration codemods, dev-tools block inspection).
 *
 * @private
 */
export function containsParentToken(tokens: TwigToken[]): boolean {
    return tokens.some((token) => {
        if (token.type !== 'logic') return false;

        if (token.token?.type === 'parent') return true;

        if (token.token?.blockName !== undefined) {
            return containsParentToken(token.token.output ?? []);
        }

        return false;
    });
}
