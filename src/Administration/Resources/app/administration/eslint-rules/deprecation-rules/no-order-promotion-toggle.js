/**
 * @sw-package framework
 *
 * Upgrade codemod `order-promotion-toggle`: 6.8 removes the automatic promotion toggle of the order detail page.
 * Members, the service call and the route are in the data table. The route is matched by a visitor, because the
 * factory's `literals` do not look into template literals such as `` `_action/order/${id}/toggleAutomaticPromotions` ``.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const data = require('./no-order-promotion-toggle.data');

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => {
        const check = (node, text) => {
            const route = data.routes.find((entry) => entry.pattern.test(text));

            if (route) {
                api.todo(node, route.message);
            }
        };

        return {
            Literal(node) {
                if (typeof node.value === 'string' && node.parent.type !== 'ImportDeclaration') {
                    check(node, node.value);
                }
            },
            TemplateLiteral(node) {
                check(node, node.quasis.map((quasi) => quasi.value.cooked).join('*'));
            },
        };
    },
});
