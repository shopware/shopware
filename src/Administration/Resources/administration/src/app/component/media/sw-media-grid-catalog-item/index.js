import { Component } from 'src/core/shopware';
import template from './sw-media-grid-catalog-item.html.twig';
import './sw-media-grid-catalog-item.less';

Component.register('sw-media-grid-catalog-item', {
    template,

    props: {
        catalog: {
            required: true,
            type: Object,
            validator(value) {
                return value.type === 'catalog';
            }
        }
    },

    methods: {
        viewCatalogContent() {
            this.$router.push({
                name: 'sw.media.catalog-content',
                params: {
                    id: this.catalog.id
                },
                query: {
                    limit: 25,
                    page: 1
                }
            });
        }
    }
});
