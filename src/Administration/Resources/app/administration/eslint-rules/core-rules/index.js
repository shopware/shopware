/**
 * @sw-package framework
 */

module.exports = {
    rules: {
        'require-position-identifier': require('./require-position-identifier'),
        'require-package-annotation': require('./require-package-annotation'),
        'require-explicit-emits': require('./require-explicit-emits'),
        'move-v-if-conditions-to-blocks': require('./move-v-if-conditions-to-blocks'),
        'remove-empty-templates': require('./remove-empty-templates'),
        'move-slots-to-wrap-blocks': require('./move-slots-to-wrap-blocks'),
        'replace-top-level-blocks-to-extends': require('./replace-top-level-blocks-to-extends'),
        'enforce-async-component-registers': require('./enforce-async-component-registers'),
        'no-tc-translation': require('./no-tc-translation'),
        'require-global-default-use': require('./require-global-default-use'),
        'valid-shopware-setup': require('./valid-shopware-setup'),
        'native-setup-filename': require('./native-setup-filename'),
        'no-swallowed-errors': require('./no-swallowed-errors'),
        'no-new-mixins': require('./no-new-mixins'),
    },
};
