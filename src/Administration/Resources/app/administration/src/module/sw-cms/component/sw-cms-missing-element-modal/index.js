import template from './sw-cms-missing-element-modal.html.twig';
import './sw-cms-missing-element-modal.scss';

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['modal-close', 'modal-save', 'modal-dont-remind-change'],

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

        onChangeDontRemindCheckbox() {
            this.$emit('modal-dont-remind-change');
        },
    },
};
