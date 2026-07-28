/**
 * @sw-package framework
 */
import template from './sw-bulk-edit-document-generation-failed-list.html.twig';
import './sw-bulk-edit-document-generation-failed-list.scss';

/**
 * @private
 */
export default {
    template,

    props: {
        rows: {
            type: Array,
            required: false,
            default: () => [],
        },

        itemsPerPage: {
            type: Number,
            required: false,
            default: 5,
        },
    },

    data() {
        return {
            page: 1,
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'orderNumber',
                    label: this.$t('sw-bulk-edit.modal.success.failedDocuments.columnOrder'),
                    rawData: true,
                },
                {
                    property: 'documentTypesLabel',
                    label: this.$t('sw-bulk-edit.modal.success.failedDocuments.columnDocumentType'),
                    rawData: true,
                },
            ];
        },

        paginatedRows() {
            const start = (this.page - 1) * this.itemsPerPage;

            return this.rows.slice(start, start + this.itemsPerPage);
        },

        showPagination() {
            return this.rows.length > this.itemsPerPage;
        },
    },

    watch: {
        rows() {
            this.page = 1;
        },

        itemsPerPage() {
            this.page = 1;
        },
    },

    methods: {
        onPageChange({ page }) {
            this.page = page;
        },
    },
};
