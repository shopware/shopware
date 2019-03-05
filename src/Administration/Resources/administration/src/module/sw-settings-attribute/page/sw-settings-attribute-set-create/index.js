import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-settings-attribute-set-create', 'sw-settings-attribute-set-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.attribute.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            this.set = this.attributeSetStore.create(this.$route.params.id);
            this.set.name = 'core_';
            this.setId = this.set.id;
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.settings.attribute.detail', params: { id: this.setId } });
            });
        }
    }
});
