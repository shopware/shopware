import { Component, State } from 'src/core/shopware';
import EntityProxy from 'src/core/data/EntityProxy';
import utils from 'src/core/service/util.service';
import template from './sw-cms-create.html.twig';

Component.extend('sw-cms-create', 'sw-cms-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.cms.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    mounted() {
        this.$refs.pageConfigSidebar.openContent();
    },

    methods: {
        createdComponent() {
            // ToDo: Make the navigation state accessible via global state
            this.$root.$children[0].$children[2].$children[0].isExpanded = false;

            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            this.salesChannelStore.getList({ page: 1, limit: 25 }).then((response) => {
                this.salesChannels = response.items;

                if (this.salesChannels.length > 0) {
                    this.currentSalesChannelKey = this.salesChannels[0].id;
                }
            });

            if (this.$route.params.id) {
                this.page = new EntityProxy('cms_page', this.cmsPageService, this.$route.params.id, null);
            }
        },


        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
        },

        onSave() {
            this.$super.onSave();
        }
    }
});
