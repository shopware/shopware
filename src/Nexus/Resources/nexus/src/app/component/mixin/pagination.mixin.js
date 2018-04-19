export default {
    computed: {
        maxPage() {
            return Math.ceil(this.total / this.limit);
        },
        offset() {
            return (this.pageNum - 1) * this.limit;
        }
    },

    methods: {
        handlePagination(opts) {
            this.limit = opts.limit;
            this.pageNum = opts.pageNum;

            if (opts.refresh === true) {
                this.getData();
            }
        }
    },

    watch: {
        pageNum() {
            this.getData();
        },
        limit() {
            this.getData();
        }
    }
};
