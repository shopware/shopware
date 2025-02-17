import { type PropType } from 'vue';
import template from './sw-cms-missing-element-modal.html.twig';
import './sw-cms-missing-element-modal.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: [
        'modal-close',
        'modal-save',
        'modal-dont-remind-change',
    ],

    props: {
        missingElements: {
            type: Array as PropType<string[]>,
            required: true,
            default() {
                return [];
            },
        },
    },

    computed: {
        element() {
            return this.missingElements
                .map((missingElement) => {
                    return this.$tc(`sw-cms.elements.${missingElement}.label`);
                })
                .join(', ');
        },

        title() {
            return this.$tc('sw-cms.components.cmsMissingElementModal.title', {
                element: this.element,
            }, this.missingElements.length);
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
});
