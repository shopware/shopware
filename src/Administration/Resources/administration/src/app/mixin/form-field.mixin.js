import { Mixin } from 'src/core/shopware';

Mixin.register('sw-form-field', {
    computed: {
        formFieldName() {
            let boundExpressionName = null;
            if (this.$vnode.data.model && this.$vnode.data.model.expression) {
                boundExpressionName = `sw-field--${this.$vnode.data.model.expression.replace(/\./g, '-')}`;
            }

            return this.$attrs.name || this.name || boundExpressionName;
        }
    }
});
