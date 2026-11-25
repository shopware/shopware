import template from './sw-unchecked-areas-hint.html.twig';
import './sw-unchecked-areas-hint.scss';

/**
 * @sw-package framework
 *
 * Names the areas a usage view did not check because the user may not read them,
 * so an empty or short usage list is not mistaken for "not used anywhere".
 * Pairs with `src/core/helper/readable-areas.helper`.
 */
/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        // Snippet keys of the area names
        labels: {
            type: Array as PropType<string[]>,
            required: true,
        },

        // Snippet key of the sentence, with an {areas} placeholder for the area names
        textSnippet: {
            type: String,
            required: false,
            default: 'global.sw-unchecked-areas-hint.text',
        },
    },

    computed: {
        hintText(): string {
            return this.$t(this.textSnippet, {
                areas: this.labels.map((label) => this.$t(label)).join(', '),
            });
        },
    },
});
