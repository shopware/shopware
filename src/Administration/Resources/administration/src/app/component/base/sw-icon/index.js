import './sw-icon.less';
import template from './sw-icon.html.twig';

Shopware.Component.register('sw-icon', {
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
        decorative: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        iconNamePrefix() {
            return 'icons--';
        },

        iconSetPath() {
            return `/static/svg/sw-icons.svg#${this.iconNamePrefix + this.name}`;
        },

        iconClasses() {
            return {
                'sw-icon--small': this.small,
                'sw-icon--large': this.large
            };
        },

        iconSize() {
            const size = this.size;

            return {
                width: size,
                height: size
            };
        },

        iconColor() {
            return {
                color: this.color
            };
        }
    },

    template
});
