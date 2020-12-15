
export default {
    mixins: [Shopware.Mixin.getByName('notification')],

    methods: {
        showSaasErrors(errorResponse) {
            Shopware.Service('saasErrorHandler')
                .handleErrorResponse(errorResponse, this)
                .forEach((notification) => {
                    this.createNotificationError(notification);
                });
        },

        showSingleSaasError(error) {
            this.createNotificationError(
                Shopware.Service('saasErrorHandler')
                    .handleError(error, this)
            );
        }
    }
};
