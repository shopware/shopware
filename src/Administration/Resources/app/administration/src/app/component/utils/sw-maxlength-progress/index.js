import template from './sw-maxlength-progress.html.twig';
import './sw-maxlength-progress.scss';

const { Component } = Shopware;

Component.register('sw-maxlength-progress', {
    template,

    props: {
        maxLength: {
            type: Number,
            required: true
        },
        length: {
            type: Number,
            required: true
        },
        showFrom: {
            type: Number,
            required: false,
            default: 0
        }
    },

    computed: {
        progressClasses() {
            const classes = [];
            if (this.length >= this.showFrom) {
                classes.push('is--visible');
            }

            if (this.length === this.maxLength) {
                classes.push('is--full');
            } else if (this.length > this.maxLength * 0.85) {
                classes.push('is--warning');
            }

            return classes.join(' ');
        },

        maxLengthTooltip() {
            return this.$tc('global.sw-maxlength-progress.maxLengthTooltip', 0, {
                maxLength: this.maxLength,
                length: this.length
            });
        }
    }
});
