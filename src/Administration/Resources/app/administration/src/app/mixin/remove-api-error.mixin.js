const { Mixin } = Shopware;

Mixin.register('remove-api-error', {
    created() {
        if (typeof this.$options.$apiErrorHandler === 'function') {
            this.$options.$apiErrorHandler(this);
        }
    },

    $apiErrorHandler($vm) {
        let property = 'value';
        if ($vm.$options.model?.prop) {
            property = $vm.$options.model.prop;
        }

        $vm.$watch(
            property,
            /* eslint-disable-next-line */
            function watchEventProperty() {
                if (this.$attrs.error && this.$attrs.error.selfLink) {
                    Shopware.State.dispatch(
                        'error/removeApiError',
                        { expression: this.$attrs.error.selfLink },
                    );
                }
            },
        );
    },
});
