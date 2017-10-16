export default {

    data() {
        return {
            page: 1,
            limit: 25,
            total: 0
        };
    },

    computed: {
        maxPage() {
            return Math.ceil(this.total / this.limit);
        },
        offset() {
            return (this.page - 1) * this.limit;
        }
    },

    methods: {
        pageChange(opts) {
            if (opts.page) {
                this.page = opts.page;
            }

            if (opts.limit) {
                this.limit = opts.limit;
            }

            this.handlePagination(this.offset, this.limit, this.page);
        }
    },

    watch: {
        page() {
            this.handlePagination(this.offset, this.limit, this.page);
        },
        limit() {
            this.handlePagination(this.offset, this.limit, this.page);
        }
    }
};
