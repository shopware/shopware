import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

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
            this.$super.createdComponent();
        },

        onSave() {
            this.$super.onSave().then((success) => {
                if (!success) {
                    return;
                }

                this.$router.push({ name: 'sw.settings.rule.detail', params: { id: this.rule.id } });
            });
        }
    }
});
