import template from './sw-ratings-summary.html.twig';
import './sw-ratings-summary.scss';

const { Component } = Shopware;

Component.register('sw-ratings-summary', {
    template,

    props: {
        summary: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            maxRating: 5
        };
    },

    computed: {
        maxProgressValue() {
            return this.summary.numberOfRatings === 0 ? 1 : this.summary.numberOfRatings;
        }
    }
});
