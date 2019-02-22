import { Component, State } from 'src/core/shopware';
import type from 'src/core/service/utils/types.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-navigation-view.html.twig';
import './sw-navigation-view.scss';

Component.register('sw-navigation-view', {
    template,

    props: {
        navigation: {
            type: Object,
            required: true,
            default: {}
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    data() {
        return {
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
            if (this.navigation.cmsPageId) {
                this.cmsPageChanged(this.navigation.cmsPageId);
            }
        },

        navigationChanged() {
            this.cmsPage = null;
            if (!this.navigation.slotConfig || !type.isObject(this.navigation.slotConfig)) {
                this.navigation.setLocalData({
                    slotConfig: {}
                });
            }

            if (this.navigation.cmsPageId) {
                return this.cmsPageChanged(this.navigation.cmsPageId);
            }

            return new Promise();
        },

        cmsPageChanged(id) {
            const params = {
                criteria: CriteriaFactory.equals('cms_page.id', id),
                associations: {
                    blocks: {
                        associations: {
                            slots: { }
                        }
                    }
                }
            };

            return this.cmsPageStore.getList(params, true).then((response) => {
                const cmsPages = response.items;
                this.cmsPage = cmsPages[0];
            });
        }
    }
});
