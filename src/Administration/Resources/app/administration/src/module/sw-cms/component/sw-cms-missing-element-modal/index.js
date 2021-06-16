import template from './sw-cms-missing-element-modal.html.twig';
import './sw-cms-missing-element-modal.scss';

const { Component } = Shopware;

Component.register('sw-cms-missing-element-modal', {
    template,

    props: {
        missingElements: {
            type: Array,
            required: true,
            default() {
                return [];
            },
        },
    },

    computed: {
        element() {
            return this.missingElements.map((missingElement) => {
                return this.$tc(`sw-cms.elements.${missingElement}.label`);
            }).join(', ');
        },

        title() {
            return this.$tc('sw-cms.components.cmsMissingElementModal.title', this.missingElements.length, {
                element: this.element,
            });
        },
    },

    methods: {
        onClose() {
            this.$emit('modal-close');
        },

        onSave() {
            this.$emit('modal-save');
        },
    },
});
