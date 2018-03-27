import { Component } from 'src/core/shopware';
import './sw-pagination.less';
import template from './sw-pagination.html.twig';

Component.register('sw-pagination', {
    template,

    props: {
        total: {
            required: true
        },
        limit: {
            required: true
        },
        offset: {
            required: true
        },
        totalVisible: {
            type: Number,
            required: false,
            default: 7
        }
    },

    data() {
        return {
            currentPage: 0,
            perPage: this.limit,
            steps: [25, 50, 75, 100]
        };
    },

    computed: {
        page() {
            const page = Math.floor(this.offset / this.limit) + 1;
            this.currentPage = page;
            return page;
        },
        maxPage() {
            return Math.ceil(this.total / this.perPage);
        },
        displayedPages() {
            const maxLength = this.totalVisible;
            const value = this.currentPage;

            if (this.maxPage <= maxLength) {
                return this.range(1, this.maxPage);
            }

            const even = maxLength % 2 === 0 ? 1 : 0;
            const left = Math.floor(maxLength / 2);
            const right = (this.maxPage - left) + 1 + even;

            if (value >= left && value <= right) {
                const start = (value - left) + 2;
                const end = (value + left) - 2 - even;

                return [1, '...', ...this.range(start, end), '...', this.maxPage];
            }
            return [
                ...this.range(1, left),
                '...',
                ...this.range((this.maxPage - left) + 1 + even, this.maxPage)
            ];
        }
    },

    methods: {
        range(from, to) {
            const range = [];

            from = from > 0 ? from : 1;

            for (let i = from; i <= to; i += 1) {
                range.push(i);
            }
            return range;
        },
        pageChange() {
            this.$emit('page-change', {
                offset: (this.currentPage - 1) * this.perPage,
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

        changePageByPageNumber(pageNum) {
            this.currentPage = pageNum;
            this.pageChange();
        },

        changePageByOffsetLimit(offset) {
            this.currentPage = offset / this.perPage;
            this.pageChange();
        },

        refresh() {
            this.pageChange();
        }
    }
});
