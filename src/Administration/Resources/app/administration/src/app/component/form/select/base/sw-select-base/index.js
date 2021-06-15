import template from './sw-select-base.html.twig';
import './sw-select-base.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @description Base component for creating new select components. Uses sw-field base components as basic structure.
 * @example-type code-only
 */
Component.register('sw-select-base', {
    template,
    inheritAttrs: false,

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            expanded: false,
        };
    },

    computed: {
        swFieldClasses() {
            return { 'has--focus': this.expanded };
        },
    },

    methods: {
        toggleExpand() {
            if (!this.expanded) {
                this.expand();
            } else {
                this.collapse();
            }
        },

        expand() {
            if (this.expanded) {
                return;
            }

            if (this.disabled) {
                return;
            }

            this.expanded = true;
            document.addEventListener('click', this.listenToClickOutside);
            this.$emit('select-expanded');
        },

        collapse() {
            document.removeEventListener('click', this.listenToClickOutside);
            this.expanded = false;
            this.$emit('select-collapsed');
        },

        listenToClickOutside(event) {
            let path = event.path;
            if (typeof path === 'undefined') {
                path = this.computePath(event);
            }

            if (!path.find((element) => {
                return element === this.$el;
            })) {
                this.collapse();
            }
        },

        computePath(event) {
            const path = [];
            let target = event.target;

            while (target) {
                path.push(target);
                target = target.parentElement;
            }

            return path;
        },
    },
});
