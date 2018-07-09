import { Component } from 'src/core/shopware';
import template from './sw-description-list.html.twig';
import './sw-description-list.less';

Component.register('sw-description-list', {
    template,

    props: {
        grid: {
            type: String,
            required: false,
            default: '1fr 1fr'
        }
    },

    computed: {
        descriptionListStyles() {
            return {
                'grid-template-columns': this.grid
            };
        }
    }
});
