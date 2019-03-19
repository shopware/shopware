import { Component } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
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
            this.set.name = 'custom_';
            this.setId = this.set.id;
        },
        onSave() {
            // Check if a set with the same name exists
            const criteria = CriteriaFactory.equals('name', this.set.name);
            return this.attributeSetStore.getList({ criteria }).then((res) => {
                if (res.total === 0) {
                    this.$super.onSave().then(() => {
                        this.$router.push({ name: 'sw.settings.attribute.detail', params: { id: this.setId } });
                    });

                    return;
                }

                this.createNameNotUniqueNotification();
            });
        },
        createNameNotUniqueNotification() {
            const titleSaveSuccess = this.$tc('sw-settings-attribute.set.detail.titleNameNotUnique');
            const messageSaveSuccess = this.$tc('sw-settings-attribute.set.detail.messageNameNotUnique');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        }
    }
});
