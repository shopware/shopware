import CriteriaFactory from 'src/core/factory/criteria.factory';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-settings-custom-field-set-create', 'sw-settings-custom-field-set-detail', {

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.custom.field.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            this.set = this.customFieldSetStore.create(this.$route.params.id);
            this.set.name = 'custom_';
            this.setId = this.set.id;
        },
        saveFinish() {
            this.isSaveSuccessful = false;
            const criteria = CriteriaFactory.equals('name', this.set.name);
            return this.customFieldSetStore.getList({ criteria }).then((res) => {
                if (res.total === 0) {
                    this.$router.push({ name: 'sw.settings.custom.field.detail', params: { id: this.setId } });
                }
            });
        },
        onSave() {
            // Check if a set with the same name exists
            const criteria = CriteriaFactory.equals('name', this.set.name);
            return this.customFieldSetStore.getList({ criteria }).then((res) => {
                if (res.total === 0) {
                    this.$super('onSave');

                    return;
                }

                this.createNameNotUniqueNotification();
            });
        },
        createNameNotUniqueNotification() {
            const titleSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.titleNameNotUnique');
            const messageSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.messageNameNotUnique');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        }
    }
});
