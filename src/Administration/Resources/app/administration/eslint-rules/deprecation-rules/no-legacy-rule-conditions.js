/**
 * @sw-package framework
 *
 * Upgrade codemod `rule-condition-components`: 6.8 removes three rule builder condition components. The factory marks
 * their tags and `Component.override/extend`. The visitor marks them as `component:` value, the form
 * `ruleConditionDataProviderService.addCondition(type, { component: '…' })` uses.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const { propertyName } = require('./upgrade-helpers/member-usage');
const data = require('./no-legacy-rule-conditions.data');

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        Property(node) {
            const message = propertyName(node) === 'component' ? data.components[stringValue(node.value)] : undefined;

            if (message) {
                api.todo(node, message);
            }
        },
    }),
});
