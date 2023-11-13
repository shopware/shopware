import template from './sw-promotion-v2-empty-state-hero.html.twig';
import './sw-promotion-v2-empty-state-hero.scss';

/**
 * @package buyers-experience
 *
 * @private
 */
export default {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },

        assetPath: {
            type: String,
            required: false,
            default: '',
        },

        description: {
            type: String,
            required: false,
            default: '',
        },

        hideDescription: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        imagePath() {
            return this.assetPath ||
                '/administration/static/img/empty-states/promotion-v2-empty-state-hero.svg';
        },

        showDescription() {
            return !this.hideDescription && this.description && this.description.length > 0;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
