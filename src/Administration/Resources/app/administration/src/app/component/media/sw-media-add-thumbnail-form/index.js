import template from './sw-media-add-thumbnail-form.html.twig';
import './sw-media-add-thumbnail-form.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-media-add-thumbnail-form', {
    template,

    data() {
        return {
            width: null,
            height: null,
            isLocked: true
        };
    },

    computed: {
        lockedButtonClass() {
            return {
                'is--locked': this.isLocked
            };
        }
    },

    watch: {
        width(value) {
            if (this.isLocked) {
                this.height = value;
            }
        }
    },

    methods: {
        onLockSwitch() {
            this.isLocked = !this.isLocked;
        },

        onAdd() {
            this.$emit('thumbnail-form-size-add', { width: this.width, height: this.height });
            this.width = null;
            this.height = null;
        }
    }
});
