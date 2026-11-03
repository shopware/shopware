import template from './sw-compact-colorpicker.html.twig';
import './sw-compact-colorpicker.scss';
import { featureService } from '../dependencies';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: [featureService],

    emits: ['update:value'],

    computed: {
        colorValue: {
            get() {
                return this.localValue;
            },
            set(newColor) {
                this.localValue = newColor;
            },
        },
    },

    methods: {
        emitColor() {
            this.$emit('update:value', this.localValue);
            this.visible = false;
        },
    },
};
