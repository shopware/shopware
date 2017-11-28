import 'src/app/component/atom/grid/sw-pagination/sw-pagination.less';
import template from 'src/app/component/atom/grid/sw-pagination/sw-pagination.html.twig';

export default Shopware.Component.register('sw-pagination', {

    props: ['page', 'maxPage', 'total', 'limit'],

    data() {
        return {
            currentPage: this.page,
            perPage: this.limit,
            steps: [25, 50, 75, 100]
        };
    },

    methods: {
        pageChange() {
            this.$emit('page-change', {
                page: this.currentPage,
                limit: this.perPage
            });
        },

        firstPage() {
            this.currentPage = 1;
            this.pageChange();
        },

        prevPage() {
            this.currentPage -= 1;
            this.pageChange();
        },

        nextPage() {
            this.currentPage += 1;
            this.pageChange();
        },

        lastPage() {
            this.currentPage = this.maxPage;
            this.pageChange();
        },

        refresh() {
            this.pageChange();
        }
    },

    template
});
