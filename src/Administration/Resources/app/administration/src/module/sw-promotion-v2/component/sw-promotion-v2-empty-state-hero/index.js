import template from './sw-promotion-v2-empty-state-hero.html.twig';
import './sw-promotion-v2-empty-state-hero.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-promotion-v2-empty-state-hero', {
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
                `/administration/static/img/empty-states/${this.$route.meta.$module.name}-empty-state-hero.svg`;
        },

        showDescription() {
            return !this.hideDescription && this.description && this.description.length > 0;
        },
    },
});
