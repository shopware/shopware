import { Component } from 'src/core/shopware';
import template from './sw-show-case-detail-base.html.twig';

Component.register('sw-show-case-detail-base', {
    template,

    props: {
        record: {
            required: true
        }
    },

    data() {
        return {
            optionId: 1,
            options: [
                { key: 1, value: 'Value 1' },
                { key: 2, value: 'Value 2' },
                { key: 3, value: 'Value 3' },
                { key: 4, value: 'Value 4' },
                { key: 5, value: 'Value 5' },
                { key: 6, value: 'Value 6' },
                { key: 7, value: 'Value 7' },
                { key: 8, value: 'Value 8' },
                { key: 9, value: 'Value 9' },
                { key: 10, value: 'Value 10' },
                { key: 11, value: 'Value 11' },
                { key: 12, value: 'Value 12' },
                { key: 13, value: 'Value 13' },
                { key: 14, value: 'Value 14' },
                { key: 15, value: 'Value 15' },
                { key: 16, value: 'Value 16' },
                { key: 17, value: 'Value 17' },
                { key: 18, value: 'Value 18' },
                { key: 19, value: 'Value 19' },
                { key: 20, value: 'Value 20' },
                { key: 21, value: 'Value 21' },
                { key: 22, value: 'Value 22' },
                { key: 23, value: 'Value 23' },
                { key: 24, value: 'Value 24' },
                { key: 25, value: 'Value 25' },
                { key: 26, value: 'Value 26' },
                { key: 27, value: 'Value 27' },
                { key: 28, value: 'Value 28' },
                { key: 29, value: 'Value 29' },
                { key: 30, value: 'Value 30' },
                { key: 31, value: 'Value 31' },
                { key: 32, value: 'Value 32' },
                { key: 33, value: 'Value 33' },
                { key: 34, value: 'Value 34' },
                { key: 35, value: 'Value 35' },
                { key: 36, value: 'Value 36' },
                { key: 37, value: 'Value 37' },
                { key: 38, value: 'Value 38' },
                { key: 39, value: 'Value 39' }
            ],

            selectedOptions: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
        };
    },

    computed: {
        priceColumns() {
            return this.getPriceColumns();
        },

        mediaColumns() {
            return this.getMediaColumns();
        },

        categoryColumns() {
            return this.getCategoryColumns();
        }
    },

    methods: {
        save() {
            this.$emit('save');
        },

        getCategoryColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'Category name',
                primary: true
            }];
        },

        getMediaColumns() {
            return [{
                property: 'media.fileName',
                dataIndex: 'media.fileName',
                label: 'Media path',
                primary: true
            }, {
                property: 'media.mimeType',
                dataIndex: 'media.mimeType',
                label: 'Mime type'
            }];
        },

        getPriceColumns() {
            return [{
                property: 'price.gross',
                dataIndex: 'price.gross',
                label: 'Brutto preis',
                primary: true
            }];
        }

    }
});
