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
        // responsiveGrid: {
        //     type: Object,
        //     required: false,
        //     default() {
        //         return {};
        //     }
        // },
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
                return ['start', 'end', 'center', 'stretch', 'left', 'right'].includes(value);
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

    mounted() {
        console.log('mounted');
    },

    computed: {
        gridStyles() {
            const styles = {};

            styles[(this.type === 'row') ? 'grid-template-rows' : 'grid-template-columns'] = this.grid;
            styles['grid-gap'] = this.gap;
            styles['justify-items'] = this.justify;
            styles['justify-content'] = this.justify;
            styles['align-items'] = this.align;

            // Object.keys(this.responsiveGrid).forEach((width) => {
            //     const viewportWidth = parseInt(width, 0);
            //     const viewportWidthGrid = this.responsiveGrid[width];
            //
            //     if (this.viewportWidth <= viewportWidth) {
            //         styles['grid-template-columns'] = viewportWidthGrid;
            //     }
            // });

            return styles;
        },

        viewportWidth() {
            return this.$device.getViewportWidth();
        }
    }
});
