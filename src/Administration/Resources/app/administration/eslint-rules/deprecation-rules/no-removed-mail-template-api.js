/**
 * @sw-package framework
 *
 * Upgrade codemod `mail-template-api`: marks `mailService.testMailTemplate()` and `buildRenderPreview()`, the routes
 * `/_action/mail-template/build` and `/_action/mail-template/validate`, the members `hasTemplateData`,
 * `mailPreviewContent` and `replaceContent` in overrides of `sw-mail-template-detail`, and `searchTerm` in overrides
 * of the two mail template lists and on their tags. The texts are in `no-removed-mail-template-api.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { elementName, findAttribute } = require('./upgrade-helpers/component-tags');
const data = require('./no-removed-mail-template-api.data');

module.exports = createTodoRule({
    ...data,
    templateVisitors: (context, api) => ({
        VElement(node) {
            if (data.searchTermTags.includes(elementName(node)) && findAttribute(node, 'search-term')) {
                api.todo(node, data.searchTermAttributeMessage, 'template');
            }
        },
    }),
});
