/**
 * @sw-package discovery
 */

import template from './sw-cms-el-form-contact.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
                    label: this.$tc('sw-cms.elements.form.element.label.salutationUndisclosed'),
                },
            ];
        },
    },
};
