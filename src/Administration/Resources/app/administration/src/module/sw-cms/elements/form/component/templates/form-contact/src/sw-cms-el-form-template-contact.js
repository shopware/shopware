/**
 * @sw-package discovery
 */

import template from './sw-cms-el-form-contact.html.twig';

/**
 * @private
 */
export default {
    template,
    props: ['formSettings'],
    computed: {
        salutationOptions() {
            return [
                {
                    id: 1,
                    value: 'default',
                    disabled: true,
                    label: this.$t('sw-cms.elements.form.element.label.salutationUndisclosed'),
                },
            ];
        },
    },
};
