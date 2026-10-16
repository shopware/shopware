/**
 * @sw-package framework
 */

const createSelectorRule = require('./create-selector-rule');

module.exports = createSelectorRule({
    description: 'Disallow registering new mixins',
    // `Mixin.register()` and `Shopware.Mixin.register()`
    selector: [
        "CallExpression[callee.property.name='register'][callee.object.name='Mixin']",
        "CallExpression[callee.property.name='register'][callee.object.property.name='Mixin']",
    ].join(', '),
    message: 'Do not register new mixins. Share the logic as a composable in src/app/composables instead.',
});
