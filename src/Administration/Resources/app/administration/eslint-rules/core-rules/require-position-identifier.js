/**
 * @sw-package framework
 */

// eslint-plugin-vue 10 ships as ESM under dist/ (utils sit on the module's
// `default` export); older layouts exported them directly from lib/.
// eslint-disable-next-line import/no-extraneous-dependencies
const utilsModule = require('eslint-plugin-vue/dist/utils');
const utils = utilsModule.default ?? utilsModule;

/* eslint-disable max-len */
module.exports = {
    meta: {
        schema: [
            {
                type: 'object',
                properties: {
                    components: {
                        type: 'array',
                        items: {
                            type: 'string',
                        },
                    },
                }
            },
        ],
    },
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
