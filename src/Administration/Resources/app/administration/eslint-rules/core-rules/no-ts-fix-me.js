/**
 * @sw-package framework
 */

const createSelectorRule = require('./create-selector-rule');

module.exports = createSelectorRule({
    description: 'Disallow the $TSFixMe alias for any',
    selector: "TSTypeReference[typeName.name='$TSFixMe']",
    message: '$TSFixMe is `any` under another name. Use the real type, or `unknown` and narrow it.',
});
