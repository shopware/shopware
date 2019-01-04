import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

Component.extend('sw-configuration-create', 'sw-configuration-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.configuration.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            this.group = this.groupStore.create(this.$route.params.id);
            this.group.sortingType = 'alphanumeric';
            this.group.displayType = 'text';
            this.groupId = this.group.id;
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.configuration.detail', params: { id: this.groupId } });
            });
        }
    }
});
