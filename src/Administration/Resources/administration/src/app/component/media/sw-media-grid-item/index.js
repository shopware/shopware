import { Component } from 'src/core/shopware';
import template from './sw-media-grid-item.twig';
import './sw-media-grid-item.less';

Component.register('sw-media-grid-item', {
    template,

    data() {
        return {};
    },

    props: {
        showInline: {
            required: false,
            type: Boolean,
            default: false
        },
        selected: {
            type: Boolean,
            required: true
        },
        mediaItem: {
            required: true,
            type: Object
        },
        showCheckbox: {
            required: false,
            type: Boolean,
            default: false
        }
    },

    computed: {
        mediaItemClass() {
            return {
                'sw-media-grid-item': true,
                'sw-media-grid-item--selected': this.selected
            };
        },
        mediaItemContentClass() {
            return {
                'sw-media-grid-item__content--isGrid': !this.showInline,
                'sw-media-grid-item__content': true,
                'sw-media-grid-item__content--isList': this.showInline
            };
        },
        mediaItemCheckboxClass() {
            return {
                'sw-media-grid-item__content__checkbox': true,
                'sw-media-grid-item__content__checkbox--is-visible': this.showCheckbox
            };
        }
    },

    methods: {
        doSelectItem(event) {
            if (!this.selected ||
                event.target.type === 'text' ||
                ['SVG', 'BUTTON'].includes(event.target.tagName.toUpperCase())
            ) {
                this.$emit('media-item-add-to-selection', this.mediaItem);
                return;
            }

            this.$emit('media-item-remove-from-selection', this.mediaItem);
        }
    }
});
