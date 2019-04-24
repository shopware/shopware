import { Component, State } from 'src/core/shopware';
import template from './sw-category-detail-cms.html.twig';
import './sw-category-detail-cms.scss';

Component.register('sw-category-detail-cms', {
    template,

    props: {
        category: {
            type: Object,
            required: true
        },
        mediaItem: {
            type: Object,
            required: false,
            default: null
        },
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            cmsPages: [],
            cmsPage: null
        };
    },

    computed: {
        cmsPageStore() {
            return State.getStore('cms_page');
        },

        cmsBlocks() {
            if (!this.cmsPage) {
                return [];
            }
            return Object.values(this.cmsPage.getAssociation('blocks').store);
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.cmsPage = null;
            this.cmsPages = [];

            const params = {
                associations: {
                    blocks: {
                        associations: {
                            slots: { }
                        }
                    }
                }
            };

            this.cmsPageStore.getList(params, true).then(response => {
                this.cmsPages = response.items;
                // @todo get selected cms page by route ID
                this.cmsPage = this.cmsPages[0];
            });
        }
    }
});
