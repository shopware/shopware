/**
 * @sw-package framework
 *
 * Upgrade codemod `flag-gated-behaviour`: marks code that may rely on Administration behaviour that 6.8 changes
 * behind the `v6.8.0.0` feature flag. The factory has no member kind for "declared only", so the visitors mark
 * - the declarations of the data table in overrides of their component,
 * - `encodeURI()` of a thumbnail URL,
 * - `mailService.sendMailTemplate()` calls without `templateData` (the eighth argument, missing or `null`).
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { findOverrides } = require('./upgrade-helpers/override-target');
const { declaredMembers } = require('./upgrade-helpers/member-usage');
const data = require('./no-flag-gated-behaviour.data');

/**
 * The names along a member chain: `media.thumbnails[0].url` → `['media', 'thumbnails', 'url']`.
 *
 * @param {object} node
 * @returns {string[]}
 */
function chainNames(node) {
    if (node.type === 'Identifier') {
        return [node.name];
    }

    if (node.type === 'ChainExpression') {
        return chainNames(node.expression);
    }

    if (node.type !== 'MemberExpression') {
        return [];
    }

    const property = !node.computed && node.property.type === 'Identifier' ? [node.property.name] : [];

    return [...chainNames(node.object), ...property];
}

function isThumbnailUrl(node) {
    const names = node ? chainNames(node) : [];

    return names.at(-1) === 'url' && names.slice(0, -1).some((name) => /thumbnail/i.test(name));
}

function lacksTemplateData(node) {
    const { templateDataIndex } = data.sendMailTemplate;

    if (node.arguments.some((argument) => argument.type === 'SpreadElement')) {
        return false;
    }

    const argument = node.arguments[templateDataIndex];

    return (
        !argument ||
        (argument.type === 'Literal' && argument.value === null) ||
        (argument.type === 'Identifier' && argument.name === 'undefined')
    );
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        Program() {
            for (const override of findOverrides(context)) {
                if (!override.options) {
                    continue;
                }

                for (const member of declaredMembers(override.options)) {
                    const entry = data.declarations.find(
                        (candidate) =>
                            candidate.component === override.target && candidate.name === member.name && candidate.kinds.includes(member.kind),
                    );

                    if (entry) {
                        api.todo(member.node, entry.message);
                    }
                }
            }
        },
        CallExpression(node) {
            const callee = node.callee.type === 'MemberExpression' && !node.callee.computed ? node.callee.property : node.callee;

            if (callee.type === 'Identifier' && callee.name === 'encodeURI' && isThumbnailUrl(node.arguments[0])) {
                api.todo(node, data.encodeThumbnailUrl.message);
            }

            const call = api.serviceCall(node);
            const { service, method, message } = data.sendMailTemplate;

            if (call?.service === service && call.method === method && lacksTemplateData(node)) {
                api.todo(node, message);
            }
        },
    }),
});
