import template from './sw-settings-custom-field-set-create.html.twig';

const { Component, Context, Data } = Shopware;
const { Criteria, RepositoryIterator } = Data;
const utils = Shopware.Utils;

Component.extend('sw-settings-custom-field-set-create', 'sw-settings-custom-field-set-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.custom.field.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        async createdComponent() {
            this.set = await this.customFieldSetRepository.create(Shopware.Context.api, this.$route.params.id);
            this.set.name = 'custom_';
            this.$set(this.set, 'config', {});
            this.setId = this.set.id;
        },
        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.custom.field.detail', params: { id: this.setId } });
        },
        onSave() {
            // Check if a set with the same name exists
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('name', this.set.name));

            const iterator = new RepositoryIterator(this.customFieldSetRepository, Context.api, criteria);
            return iterator.getTotal().then(total => {
                if (total === 0) {
                    this.$super('onSave');

                    return;
                }

                this.createNameNotUniqueNotification();
            });
        },
        createNameNotUniqueNotification() {
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.messageNameNotUnique');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        }
    }
});
