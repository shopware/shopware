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
        offset: {
            get() {
                return (this.page - 1) * this.limit;
            },
            set(offset) {
                if (offset <= 0) {
                    this.page = 1;
                } else {
                    this.page = (offset / this.limit) + 1;
                }
            }
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
    }
};
