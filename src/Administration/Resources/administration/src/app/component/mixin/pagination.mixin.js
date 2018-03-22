export default {

    data() {
        return {
            page: 1,
            offset: 0,
            limit: 25,
            total: 0
        };
    },

    computed: {
        maxPage() {
            return Math.ceil(this.total / this.limit);
        }
    },

    methods: {
        pageChange(opts) {
            this.offset = opts.offset;
            this.limit = opts.limit;

            this.handlePagination(this.offset, this.limit);
        }
    }
};
