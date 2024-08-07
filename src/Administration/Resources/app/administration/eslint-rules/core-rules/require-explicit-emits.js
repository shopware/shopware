/**
 * @package admin
 */
const fs = require('node:fs');
const path = require('node:path');

const EVENT_NAME_REGEXP = /\$emit\('([^']+)'[^)]*\)/gm;

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Ensure that $emit events are defined in the emits option',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [], // No options needed
    },
    create(context) {
        let componentNode;
        let emitsNode;
        const emittedEvents = new Set();

        function getComponentAndEmitsNodes(objectNode) {
            if (isComponentDefinition(objectNode)) {
                componentNode = objectNode;
                emitsNode = objectNode.properties.find(property => property.key?.name === 'emits'
                    && property.value.type === 'ArrayExpression')?.value;
            }
        }

        function getEmitCallsFromScript(callNode) {
            if (
                callNode.callee.type === 'MemberExpression' &&
                callNode.callee.object.type === 'ThisExpression' &&
                callNode.callee.property.name === '$emit'
            ) {
                const eventName = callNode.arguments[0]?.value;
                emittedEvents.add(eventName);
            }
        }

        function getEmitCallsFromTemplate(importNode) {
            if (importNode.name === 'template') {
                const templateFileName = importNode.parent.parent.source.value;
                const directoryPath = path.dirname(context.getFilename());
                const templateSource = fs.readFileSync(path.resolve(directoryPath, templateFileName), 'utf8');

                const templateEventsNames = Array.from(templateSource.matchAll(EVENT_NAME_REGEXP))
                    .map(([, capturedGroup]) => capturedGroup).filter(Boolean);

                templateEventsNames.forEach(eventName => emittedEvents.add(eventName));
            }
        }

        function isComponentDefinition(node) {
            const parent = node.parent;

            // default export component
            if (parent.type === 'ExportDefaultDeclaration') {
                return true;
            }

            // registered component, extend component or mixin
            if (parent.type === 'CallExpression' &&
                parent.callee.type === 'MemberExpression' &&
                (parent.callee.property.name === 'register' ||
                    parent.callee.property.name === 'extend')) {
                const callExpression = parent;

                // Component.register() or Component.extend()
                if (callExpression.callee.object.name === 'Component') {
                    return true;
                }

                // Shopware.Component.register() or Shopware.Component.extend()
                if (parent.callee.object.type === 'MemberExpression' &&
                    parent.callee.object.object.name === 'Shopware' &&
                    parent.callee.object.property.name === 'Component'
                ) {
                    return true;
                }

                // Mixin.register()
                if (callExpression.callee.object.name === 'Mixin' &&
                    callExpression.callee.property.name === 'register') {
                    return true;
                }

                // Shopware.Mixin.register()
                if (parent.callee.object.type === 'MemberExpression' &&
                    parent.callee.object.object.name === 'Shopware' &&
                    parent.callee.object.property.name === 'Mixin') {
                    return true;
                }
            }

            return false;
        }

        function fixMissingEmitDefinitions(programNode) {
            const emitsDefinition = emitsNode?.elements.map(element => element.value) ?? [];
            const pendingEmitDefinitions = Array.from(emittedEvents)
                .filter(e => e && !emitsDefinition.includes(e));

            if (pendingEmitDefinitions.length) {
                const stringEmitEvents = `'${pendingEmitDefinitions.join('\', \'')}'`;

                context.report({
                    node: programNode,
                    message: `Event(s) ${stringEmitEvents} not defined in the emits option.`,
                    * fix(fixer) {
                        // no emits field in the component
                        if (!emitsNode) {
                            return yield insertNewEmitsNode(fixer, stringEmitEvents);
                        }

                        // emits with already some event in the component
                        const lastElement = emitsNode.elements.at(-1);
                        if (lastElement) {
                            return yield fixer.insertTextAfter(lastElement, `, ${stringEmitEvents}`);
                        }

                        // emits without any event in
                        const emitsNodeEnd = emitsNode.range[1]; // accessing to emitsNode.end causes an error in tests
                        return yield fixer.insertTextAfterRange(
                            [emitsNodeEnd - 1, emitsNodeEnd - 1],
                            `${stringEmitEvents}`,
                        );
                    },
                });
            }
        }

        function insertNewEmitsNode(fixer, stringEmitEvents) {
            const fieldsBeforeEmits = [
                'el', 'name', 'parent', 'functional', 'template', 'render',
                'inheritAttrs', 'compatConfig' , 'inject', 'provide',
            ];

            const nodeAfterWhichToInsert = componentNode.properties
                .findLast(property => fieldsBeforeEmits.includes(property.key.name));

            if (nodeAfterWhichToInsert) {
                return fixer.insertTextAfter(nodeAfterWhichToInsert, `,\n\nemits: [${stringEmitEvents}]`);
            }

            // in the case there is no fields that should be before emits, then we insert at the beginning of the component
            const componentNodeStart = componentNode.range[0]; // accessing to componentNodeStart.start causes an error in tests
            return fixer.insertTextAfterRange(
                [componentNodeStart + 1, componentNodeStart + 1],
                `\nemits: [${stringEmitEvents}],\n`,
            );
        }

        return {
            ObjectExpression: getComponentAndEmitsNodes,
            CallExpression: getEmitCallsFromScript,
            'ImportDefaultSpecifier > Identifier': getEmitCallsFromTemplate,
            'Program:exit': fixMissingEmitDefinitions,
        };
    },
};

