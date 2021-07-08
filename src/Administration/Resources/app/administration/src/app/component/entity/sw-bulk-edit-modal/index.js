import template from './sw-bulk-edit-modal.html.twig';
import './sw-bulk-edit-modal.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-modal', {
    template,

    props: {
        selection: {
            type: Object,
            required: false,
            default: null,
        },

        steps: {
            type: Array,
            required: false,
            default() {
                return [10, 25, 50];
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
            bulkEditSelection: null,
            limit: 10,
            page: 1,
        };
    },

    computed: {
        itemCount() {
            if (this.bulkEditSelection === undefined || this.bulkEditSelection === null) {
                return 0;
            }

            return Object.keys(this.bulkEditSelection).length;
        },

        paginateRecords() {
            return this.records.slice((this.page - 1) * this.limit, this.page * this.limit);
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            if (this.selection) {
                this.records = Object.values(this.selection);
                this.bulkEditSelection = this.selection;
            }
        },

        mountedComponent() {
            this.$refs.bulkEditGrid.selectAll(true);
        },

        paginate({ page = 1, limit = 10 }) {
            this.page = page;
            this.limit = limit;
        },

        updateBulkEditSelection(selections) {
            this.bulkEditSelection = selections;
        },

        editItems() {
            const entityIds = Object.keys(this.selection);

            this.$emit('modal-close');
            if (entityIds.length > 0) {
                Shopware.State.commit('shopwareApps/setSelectedIds', entityIds);
                this.$emit('edit-items');
            }
        },
    },
});
