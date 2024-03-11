/**
 * @package services-settings
 *
 * @private
 */
export default {
    methods: {
        createdComponent() {
            this.isLoading = true;
            Shopware.State.commit('context/resetLanguageToDefault');
            this.customerGroup = this.customerGroupRepository.create();
            this.isLoading = false;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (!this.validateSaveRequest()) {
                return;
            }

            try {
                await this.customerGroupRepository.save(this.customerGroup);

                this.isSaveSuccessful = true;

                this.$router.push({ name: 'sw.settings.customer.group.detail', params: { id: this.customerGroup.id } });
            } catch (err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
