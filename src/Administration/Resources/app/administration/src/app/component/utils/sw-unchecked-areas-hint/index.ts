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
    },

    computed: {
        hintText(): string {
            return this.$t('global.sw-unchecked-areas-hint.text', {
                areas: this.labels.map((label) => this.$t(label)).join(', '),
            });
        },
    },
});
