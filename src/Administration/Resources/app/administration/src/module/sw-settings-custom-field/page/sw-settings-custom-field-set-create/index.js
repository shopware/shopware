/**
 * @package system-settings
 */
import template from './sw-settings-custom-field-set-create.html.twig';

const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.custom.field.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            this.set = await this.customFieldSetRepository.create(Shopware.Context.api, this.$route.params.id);
            this.set.name = 'custom_';
            this.$set(this.set, 'config', {});
            this.setId = this.set.id;
            this.isLoading = false;
        },
        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.custom.field.detail', params: { id: this.setId } });
        },
        onSave() {
            this.isLoading = true;

            if (!this.set || !this.set.name) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                });

                this.technicalNameError = {
                    detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                };

                this.isLoading = false;

                return;
            }

            // Check if a set with the same name exists
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('name', this.set.name));

            this.customFieldSetRepository.search(criteria).then((res) => {
                if (res.length === 0) {
                    this.$super('onSave');

                    return;
                }

                this.createNameNotUniqueNotification();
                this.isLoading = false;
            });
        },
        createNameNotUniqueNotification() {
            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: this.$tc('sw-settings-custom-field.set.detail.messageNameNotUnique'),
            });

            this.technicalNameError = {
                detail: this.$tc('sw-settings-custom-field.set.detail.messageNameNotUnique'),
            };
        },
    },
};
