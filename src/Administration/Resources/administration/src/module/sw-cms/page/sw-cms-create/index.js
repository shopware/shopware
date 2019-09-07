import EntityProxy from 'src/core/data/EntityProxy';
import template from './sw-cms-create.html.twig';

const { Component, Mixin, State } = Shopware;
const utils = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

Component.extend('sw-cms-create', 'sw-cms-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

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

            if (!this.isSystemDefaultLanguage) {
                this.languageStore.setCurrentId(this.context.systemLanguageId);
                this.currentLanguageId = this.context.systemLanguageId;
            }

            const defaultStorefrontId = '8A243080F92E4C719546314B577CF82B';

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('typeId', defaultStorefrontId)
            );

            this.salesChannelRepository.search(criteria, this.context).then((response) => {
                this.salesChannels = response;

                if (this.salesChannels.length > 0) {
                    this.currentSalesChannelKey = this.salesChannels[0].id;
                    this.page = this.pageRepository.create();
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
