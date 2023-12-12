import template from './sw-button-process.html.twig';
import './sw-button-process.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @description The <u>sw-button-process</u> component extends the sw-button component with visual feedback,
 * indicating loading and success states.
 * @example-type dynamic
 * @component-example
 * <sw-button-process>
 *     Button
 * </sw-button-process>
 */
Component.register('sw-button-process', {
    template,
    inheritAttrs: false,

    inject: ['feature'],

    model: {
        prop: 'processSuccess',
        event: 'process-finish',
    },

    props: {
        processSuccess: {
            type: Boolean,
            required: true,
        },

        animationTimeout: {
            type: Number,
            required: false,
            default: 1250,
        },
    },

    computed: {
        contentVisibilityClass() {
            return {
                'is--hidden': this.processSuccess,
            };
        },
    },

    watch: {
        processSuccess(value) {
            if (!value) {
                return;
            }

            setTimeout(() => {
                if (this.feature.isActive('VUE3')) {
                    this.$emit('update:processSuccess', false);

                    return;
                }

                this.$emit('process-finish', false);
            }, this.animationTimeout);
        },
    },
});
