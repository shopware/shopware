import ComponentFactory from 'src/core/factory/component.factory';
import template from 'src/app/component/atom/grid/sw-pagination/sw-pagination.html.twig';

export default ComponentFactory.register('sw-pagination', {
    props: ['pageNum', 'maxPage', 'total', 'perPage'],

    data() {
        return {
            steps: [25, 50, 75, 100]
        };
    },

    methods: {
        emitChange(opts = {}) {
            const defaults = {
                pageNum: this.pageNum,
                limit: this.perPage,
                refresh: false
            };

            const eventParams = Object.assign({}, defaults, opts);
            this.$emit('data-changed', eventParams);
        },

        changePageNum(event) {
            const val = event.target.valueAsNumber;

            this.emitChange({
                pageNum: val
            });
        },

        changeLimit(event) {
            const val = parseInt(event.target.value, 10);

            this.emitChange({
                pageNum: 1,
                limit: val
            });
        },

        firstPage() {
            this.emitChange({
                pageNum: 1
            });
        },

        prevPage() {
            this.emitChange({
                pageNum: this.pageNum - 1
            });
        },

        nextPage() {
            this.emitChange({
                pageNum: this.pageNum + 1
            });
        },

        lastPage() {
            this.emitChange({
                pageNum: this.maxPage
            });
        },

        refresh() {
            this.emitChange({
                refresh: true
            });
        }
    },

    template
});
