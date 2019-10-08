import template from './sw-cms-create.html.twig';

const { Component, Mixin, State } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-cms-create', 'sw-cms-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            wizardComplete: false
        };
    },

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.cms.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        },

        pageHasSections() {
            return this.page.sections.length > 0 && this.wizardComplete;
        }
    },

    methods: {
        createdComponent() {
            this.store.commit('adminMenu/collapseSidebar');

            if (!this.isSystemDefaultLanguage) {
                this.languageStore.setCurrentId(this.context.systemLanguageId);
                this.currentLanguageId = this.context.systemLanguageId;
            }

            this.page = this.pageRepository.create();
            this.page.sections = [];
        },

        onSave() {
            this.$super.onSave();
            this.$router.push({ name: 'sw.cms.detail', params: { id: this.page.id } });
        },

        onWizardComplete() {
            if (this.page.type === 'product_list') {
                this.onPageTypeChange();
            }

            this.wizardComplete = true;
            this.onSave();
        }
    }
});
