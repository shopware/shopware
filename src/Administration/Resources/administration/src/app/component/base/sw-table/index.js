import { Component } from 'src/core/shopware';
import template from './sw-table.html.twig';
import './sw-table.less';

Component.register('sw-table', {
    template,

    props: {
        columns: {
            type: String,
            required: false,
            default: '1fr 1fr'
        }
    },

    computed: {
        tableStyles() {
            return {
                'grid-template-columns': this.columns
            };
        }
    }
});
