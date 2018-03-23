import { Component } from 'src/core/shopware';
import template from './sw-container.html.twig';
import './sw-container.less';

Component.register('sw-container', {
    template,

    props: {
        type: {
            type: String,
            required: false,
            default: 'column',
            validator(value) {
                return ['column', 'row'].includes(value);
            }
        },
        grid: {
            type: String,
            required: false,
            default: '50% 50%'
        },
        gap: {
            type: String,
            required: false,
            default: '0px'
        },
        justify: {
            type: String,
            required: false,
            default: 'stretch',
            validator(value) {
                return ['start', 'end', 'center', 'stretch'].includes(value);
            }
        },
        align: {
            type: String,
            required: false,
            default: 'stretch',
            validator(value) {
                return ['start', 'end', 'center', 'stretch'].includes(value);
            }
        }
    },

    computed: {
        gridStyles() {
            const styles = {};

            styles[(this.type === 'row') ? 'grid-template-rows' : 'grid-template-columns'] = this.grid;
            styles[(this.type === 'row') ? 'grid-row-gap' : 'grid-column-gap'] = this.gap;
            styles['justify-items'] = this.justify;
            styles['align-items'] = this.align;

            return styles;
        }
    }
});
