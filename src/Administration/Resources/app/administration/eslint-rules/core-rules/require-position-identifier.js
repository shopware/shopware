/**
 * @package admin
 */

// eslint-disable-next-line import/no-extraneous-dependencies
const utils = require('eslint-plugin-vue/lib/utils');

/* eslint-disable max-len */
module.exports = {
    create(context) {
        // get components from the options
        const components = context.options[0].components;

        return utils.defineTemplateBodyVisitor(context, {
            'VElement'(node) {
                const nodeName = node.name;
                if (!components.includes(nodeName)) {
                    return;
                }

                const positionIdentifier = utils.getAttribute(node, 'position-identifier');
                const positionIdentifierDirective = utils.getDirective(node, 'bind', 'position-identifier');

                if (!positionIdentifier && !positionIdentifierDirective) {
                    context.report({
                        loc: node.loc.start,
                        message: 'The component "{{ nodeName }}" requires the property "position-identifier"',
                        data: { nodeName },
                    });
                }
            },
        });
    },
};
