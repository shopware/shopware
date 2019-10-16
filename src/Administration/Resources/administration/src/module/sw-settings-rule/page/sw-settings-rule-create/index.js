const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-settings-rule-create', 'sw-settings-rule-detail', {
    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.rule.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.ruleStore.create(this.$route.params.id);
            }
            this.$super('createdComponent');
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.rule.detail', params: { id: this.rule.id } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
