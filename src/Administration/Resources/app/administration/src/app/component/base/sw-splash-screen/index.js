import template from './sw-splash-screen.html.twig';
import './sw-splash-screen.scss';

const { Component } = Shopware;

/**
 * @description Creates a splash screen, e.g. for empty state screens
 * @status ready
 * @example-type static
 * @component-example
 * <sw-splash-screen
 *     title="No promotions yet."
 *     description="Boost your sales!"
 *     assetPath="/administration/static/img/splash-screens/some-really-awesome-asset.svg">
 *
 *     <template #actions>
 *         <sw-button>Press me!</sw-button>
 *     </template>
 *
 * </sw-rating-stars>
 */
Component.register('sw-splash-screen', {
    template,

    props: {
        title: {
            type: String,
            required: true
        },

        assetPath: {
            type: String,
            required: false,
            default: ''
        },

        description: {
            type: String,
            required: false,
            default: ''
        },

        hideDescription: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        imagePath() {
            return this.assetPath ||
                `/administration/static/img/splash-screens/${this.$route.meta.$module.name}-splash-screen.svg`;
        },

        showDescription() {
            return !this.hideDescription && this.description && this.description.length > 0;
        }
    }
});
