import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-sidebar-item.html.twig';
import './sw-sidebar-item.less';

Component.register('sw-sidebar-item', {
    template,

    props: {
        title: {
            type: String,
            required: false
        },
        icon: {
            type: String,
            required: true
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },
    data() {
        return {
            panelId: utils.createId(),
            isExpanded: false
        };
    },

    computed: {
        hasDefaultSlot() {
            return !!this.$slots.default;
        }
    },

    created() {
        this.$parent.items[this.panelId] = this;
    },

    methods: {
        toggleContentPanel() {
            if (this.disabled) {
                return;
            }
            this.$emit('click', this);

            // The panel is just a button which can be clicked by the user
            if (!this.hasDefaultSlot) {
                return;
            }

            this.isExpanded = !this.isExpanded;
            this.$parent.closeNonExpandedContentPanels(this.panelId);
        }
    }
});
