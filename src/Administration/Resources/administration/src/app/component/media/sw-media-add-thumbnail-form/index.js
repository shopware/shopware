import { Component } from 'src/core/shopware';
import template from './sw-media-add-thumbnail-form.html.twig';
import './sw-media-add-thumbnail-form.less';

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
            this.$emit('sw-media-add-thumbnail-form-size-added', { width: this.width, height: this.height });
            this.width = null;
            this.height = null;
        }
    }
});
