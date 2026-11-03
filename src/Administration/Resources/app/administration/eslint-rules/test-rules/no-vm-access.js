/**
 * @sw-package framework
 */

const path = require('path');
const { findMiniModule, readPublicMembers } = require('../core-rules/mini-module');

/**
 * The specs of a mini-module that pin members no user path reaches, so they may read them through `.vm`:
 * `public-api` for those `index.ts` lists, `private-api` for those the `private.ts` of a nested module lists.
 */
const API_SPECS = [
    { file: /(^|\/)public-api\.spec\.[jt]s$/, spec: 'public-api', entry: 'index.ts' },
    { file: /(^|\/)private-api\.spec\.[jt]s$/, spec: 'private-api', entry: 'private.ts' },
];

const CHILD_LOOKUPS = ['findComponent', 'findAllComponents', 'getComponent'];

// `.vm.$emit()` is how Vue Test Utils simulates an event from a child or stub.
// On the mounted `wrapper` it skips the component's own code and only asserts the test's setup.
function isChildEmitCall(vmAccess) {
    const { parent, object } = vmAccess;

    return (
        !(object.type === 'Identifier' && object.name === 'wrapper') &&
        parent.type === 'MemberExpression' &&
        parent.object === vmAccess &&
        !parent.computed &&
        parent.property.name === '$emit'
    );
}

/**
 * Returns the member a `.vm` access reads, as in `wrapper.vm.usedRules`, or null for anything else:
 * a destructuring, a computed key, or a write.
 *
 * @param {import('estree').MemberExpression} vmAccess
 * @returns {string|null}
 */
function getReadMember(vmAccess) {
    const { parent } = vmAccess;
    if (parent.type !== 'MemberExpression' || parent.object !== vmAccess || parent.computed) {
        return null;
    }

    const isWrite =
        (parent.parent.type === 'AssignmentExpression' && parent.parent.left === parent) ||
        parent.parent.type === 'UpdateExpression';

    return isWrite ? null : parent.property.name;
}

/**
 * Returns true for `.vm` on a child that a lookup returned, as in `wrapper.findComponent(X).vm`:
 * the public lists of the module's index.ts are not the child's.
 *
 * @param {import('estree').MemberExpression} vmAccess
 * @returns {boolean}
 */
function isOnChildLookup(vmAccess) {
    const { object } = vmAccess;

    return (
        object.type === 'CallExpression' &&
        object.callee.type === 'MemberExpression' &&
        CHILD_LOOKUPS.includes(object.callee.property.name)
    );
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description:
                "Disallow reaching into component instances through `.vm` in tests, except for the listed members in a mini-module's public-api or private-api spec",
        },
        schema: [],
        messages: {
            vmAccess: 'Test what the user sees. `.vm` reaches into component internals.',
            notPublic: '`{{member}}` is not listed. A {{spec}} spec reads only the members {{entry}} lists: {{allowed}}.',
            childAccess: "A {{spec}} spec reads the listed members of its module's component only, not of a child.",
            notARead:
                'A {{spec}} spec reads one listed member at a time and never writes it, as in `wrapper.vm.{{example}}`. Arrange state through the harness or `setProps()`.',
        },
    },

    create(context) {
        const filename = (context.filename ?? context.getFilename()).split('\\').join('/');
        const apiSpec = API_SPECS.find(({ file }) => file.test(filename));
        let publicMembers = null;

        if (apiSpec) {
            const cwd = context.cwd ?? process.cwd();
            const relative = path.relative(cwd, path.resolve(cwd, filename)).split(path.sep).join('/');
            const miniModule = findMiniModule(relative, cwd);
            const lists = miniModule?.part === 'tests' ? readPublicMembers(miniModule.root, cwd, apiSpec.entry) : null;
            publicMembers = new Set(lists ? Object.values(lists).flat() : []);
        }

        return {
            "MemberExpression[computed=false][property.name='vm']"(node) {
                if (isChildEmitCall(node)) {
                    return;
                }

                if (!publicMembers) {
                    context.report({ node, messageId: 'vmAccess' });
                    return;
                }

                if (isOnChildLookup(node)) {
                    context.report({ node, messageId: 'childAccess', data: apiSpec });
                    return;
                }

                const member = getReadMember(node);
                if (member === null) {
                    context.report({
                        node,
                        messageId: 'notARead',
                        data: { ...apiSpec, example: [...publicMembers][0] ?? '<listed member>' },
                    });
                    return;
                }

                if (!publicMembers.has(member)) {
                    context.report({
                        node: node.parent,
                        messageId: 'notPublic',
                        data: { ...apiSpec, member, allowed: [...publicMembers].join(', ') || 'none' },
                    });
                }
            },
        };
    },
};
