import template from './sw-bulk-edit-modal.html.twig';
import './sw-bulk-edit-modal.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-bulk-edit-modal', {
    template,

    props: {
        selection: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },

        steps: {
            type: Array,
            required: false,
            default() {
                return [200, 300, 400, 500];
            },
        },

        bulkGridEditColumns: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            records: [],
            bulkEditSelection: this.selection,
            limit: 200,
            page: 1,
            identifier: 'sw-bulk-edit-grid',
        };
    },

    computed: {
        itemCount() {
            return Object.keys(this.bulkEditSelection).length;
        },

        paginateRecords() {
            return this.records.slice((this.page - 1) * this.limit, this.page * this.limit);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const records = Object.values(this.selection);

            if (records.length > 0) {
                this.records = records;
            }
        },

        paginate({ page = 1, limit = 10 }) {
            this.page = page;
            this.limit = limit;
        },

        updateBulkEditSelection(selections) {
            this.bulkEditSelection = selections;
        },

        editItems() {
            this.$emit('modal-close');

            if (this.itemCount > 0) {
                Shopware.State.commit('shopwareApps/setSelectedIds', Object.keys(this.bulkEditSelection));
                this.$emit('edit-items');
            }
        },
    },
});
