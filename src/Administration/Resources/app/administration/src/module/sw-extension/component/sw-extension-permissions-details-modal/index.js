import template from './sw-extension-permissions-details-modal.html.twig';
import './sw-extension-permissions-details-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: ['modal-close'],

    props: {
        permissions: {
            type: Object,
            required: true,
        },
        modalTitle: {
            type: String,
            required: true,
        },
        selectedEntity: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        operations() {
            return [
                {
                    label: this.$t('sw-extension-store.component.sw-extension-permissions-details-modal.operationRead'),
                    operation: 'read',
                },
                {
                    label: this.$t('sw-extension-store.component.sw-extension-permissions-details-modal.operationUpdate'),
                    operation: 'update',
                },
                {
                    label: this.$t('sw-extension-store.component.sw-extension-permissions-details-modal.operationCreate'),
                    operation: 'create',
                },
                {
                    label: this.$t('sw-extension-store.component.sw-extension-permissions-details-modal.operationDelete'),
                    operation: 'delete',
                },
            ];
        },

        ankerId() {
            return this.selectedEntity !== '' ? `permission-${this.selectedEntity}` : null;
        },
    },

    mounted() {
        this.scrollSelectedEntityIntoView();
    },

    methods: {
        scrollSelectedEntityIntoView() {
            if (this.ankerId === null) {
                return;
            }

            const modalBody = this.$el.querySelector('.sw-modal__body');
            const table = this.$el.querySelector('.sw-extension-permissions-details-modal__table');

            if (table.offsetHeight <= modalBody.offsetHeight) {
                return;
            }

            const entityElement = this.$el.querySelector(`#${this.ankerId}`);
            const topOfElement = entityElement.offsetTop;
            const headRow = this.$el.querySelector('.sw-extension-permissions-details-modal__operations');

            modalBody.scroll({
                top: topOfElement - headRow.offsetHeight,
                behavior: 'smooth',
            });
        },

        close() {
            this.$emit('modal-close');
        },

        categoryLabel(category) {
            return this.$t(`entityCategories.${category}.title`);
        },

        entityLabel(category, entity) {
            const translation = `entityCategories.${category}.entities.${entity}`;

            return this.$te(translation) ? this.$t(translation) : entity;
        },
    },
};
