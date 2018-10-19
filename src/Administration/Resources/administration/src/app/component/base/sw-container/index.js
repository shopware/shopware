import { Component } from 'src/core/shopware';
import template from './sw-container.html.twig';
import './sw-container.less';

Component.register('sw-container', {
    template,

    props: {
        columns: {
            type: String,
            default: '',
            required: false
        },
        rows: {
            type: String,
            default: '',
            required: false
        },
        gap: {
            type: String,
            default: '',
            required: false
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
        },
        breakpoints: {
            type: Object,
            default() {
                return {};
            },
            required: false
        }
    },

    data() {
        return {
            currentCssGrid: this.buildCssGrid()
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.registerBreakpointListener();
        },

        registerBreakpointListener() {
            const that = this;
            this.$device.onResize({
                listener() {
                    that.updateCssGrid();
                },
                component: this
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
                if (Number.isNaN(Number.parseInt(breakpoint, 0))) {
                    console.error(
                        `[${this.$options.name}] Unable to register breakpoint "${breakpoint}". 
                        The breakpoint key has to be a number.`
                    );
                    return cssGrid;
                }

                if (Number.parseInt(breakpoint, 0) > this.$device.getViewportWidth()) {
                    cssGrid = this.buildCssGridProps(
                        this.breakpoints[breakpoint]
                    );
                    return cssGrid;
                }

                return cssGrid;
            });

            return cssGrid;
        },

        cssGridDefaults() {
            return {
                columns: this.columns,
                rows: this.rows,
                gap: this.gap,
                justify: this.justify,
                align: this.align
            };
        },

        buildCssGridProps(currentBreakpoint = {}) {
            const grid = Object.assign(this.cssGridDefaults(), currentBreakpoint);

            return {
                'grid-template-columns': grid.columns,
                'grid-template-rows': grid.rows,
                'grid-gap': grid.gap,
                'justify-items': grid.justify,
                'align-items': grid.align
            };
        }
    },

    computed: {
        defaultLayouts() {
            return {
                form2Column: 'repeat(auto-fit, minmax(250px, 1fr)'
            };
        }
    }
});
