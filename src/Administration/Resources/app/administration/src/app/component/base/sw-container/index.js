import template from './sw-container.html.twig';
import './sw-container.scss';

const { Component } = Shopware;
const { warn } = Shopware.Utils.debug;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Provides a container element which is divided in multiple sections with the use of CSS grid.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-container columns="1fr 1fr">
 *     <div>Left content</div>
 *     <div>Right content</div>
 * </sw-container>
 */
Component.register('sw-container', {
    template,

    props: {
        columns: {
            type: String,
            default: '',
            required: false,
        },
        rows: {
            type: String,
            default: '',
            required: false,
        },
        gap: {
            type: String,
            default: '',
            required: false,
        },
        justify: {
            type: String,
            required: false,
            default: 'stretch',
            validValues: ['start', 'end', 'center', 'stretch', 'left', 'right'],
            validator(value) {
                return ['start', 'end', 'center', 'stretch', 'left', 'right'].includes(value);
            },
        },
        align: {
            type: String,
            required: false,
            default: 'stretch',
            validValues: ['start', 'end', 'center', 'stretch'],
            validator(value) {
                return ['start', 'end', 'center', 'stretch'].includes(value);
            },
        },
        breakpoints: {
            type: Object,
            default() {
                return {};
            },
            required: false,
        },
    },

    data() {
        return {
            currentCssGrid: this.buildCssGrid(),
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.registerResizeListener();
        },

        registerResizeListener() {
            const that = this;

            this.$device.onResize({
                listener() {
                    that.updateCssGrid();
                },
                component: this,
            });
        },

        updateCssGrid() {
            this.currentCssGrid = this.buildCssGrid();
        },

        buildCssGrid() {
            let cssGrid = this.buildCssGridProps();

            if (Object.keys(this.breakpoints).length === 0) {
                return cssGrid;
            }

            Object.keys(this.breakpoints).find(breakpoint => {
                const currentBreakpointWidth = Number.parseInt(breakpoint, 10);
                const currentBreakpoint = this.breakpoints[breakpoint];

                if (Number.isNaN(currentBreakpointWidth)) {
                    warn(
                        this.$options.name,
                        `Unable to register breakpoint "${breakpoint}".
                        The breakpoint key has to be a number equal to your desired pixel value.`,
                        currentBreakpoint,
                    );
                }

                if (currentBreakpointWidth > this.$device.getViewportWidth()) {
                    cssGrid = this.buildCssGridProps(currentBreakpoint);
                    return cssGrid;
                }
                return null;
            });

            return cssGrid;
        },

        cssGridDefaults() {
            return {
                columns: this.columns,
                rows: this.rows,
                gap: this.gap,
                justify: this.justify,
                align: this.align,
            };
        },

        buildCssGridProps(currentBreakpoint = {}) {
            const grid = Object.assign(this.cssGridDefaults(), currentBreakpoint);

            return {
                'grid-template-columns': grid.columns,
                'grid-template-rows': grid.rows,
                'grid-gap': grid.gap,
                'justify-items': grid.justify,
                'align-items': grid.align,
            };
        },
    },
});
