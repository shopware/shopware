import { Component } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';
import template from './sw-icon.html.twig';
import './sw-icon.less';

Component.register('sw-icon', {
    template,

    props: {
        name: {
            type: String,
            required: true
        },
        small: {
            type: Boolean,
            required: false
        },
        large: {
            type: Boolean,
            required: false
        },
        size: {
            type: String,
            required: false
        },
        title: {
            type: String,
            required: false
        },
        color: {
            type: String,
            required: false
        },
        multicolor: {
            type: Boolean,
            required: false
        },
        decorative: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        iconNamePrefix() {
            return 'icon--';
        },

        iconSetPath() {
            return this.multicolor ?
                `/static/img/sw-icons-multicolor.svg#${this.iconNamePrefix + this.name}` :
                `/static/img/sw-icons.svg#${this.iconNamePrefix + this.name}`;
        },

        iconClasses() {
            return {
                [this.iconNamePrefix + this.name]: this.name,
                'sw-icon--small': this.small,
                'sw-icon--large': this.large
            };
        },

        iconStyles() {
            let size = this.size;

            if (!Number.isNaN(parseFloat(size)) && !Number.isNaN(size - 0)) {
                size = `${size}px`;
            }

            return {
                color: this.color,
                width: size,
                height: size
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.color && this.multicolor) {
                debug.warn(
                    this.$options.name,
                    `The color of "${this.name}" cannot be adjusted because it is a multicolor icon.`
                );
            }
        }
    }
});
