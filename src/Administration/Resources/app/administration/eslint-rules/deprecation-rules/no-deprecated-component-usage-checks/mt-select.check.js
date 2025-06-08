/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtSelect = (context, node) => {
    const mtComponentName = 'mt-select';

    // Refactor the old usage of mt-select to mt-select after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    const templateComments = context.getSourceCode().ast?.templateBody?.comments;

    // Check if the mt-select has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-select has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-select has the attribute "aside"
    const asideAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'aside');
    // Check if the mt-select has the attribute expression "aside"
    const asideAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'aside';
    });

    // Check if the mt-select uses v-model:value
    const vModelValueAttribute = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'model' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if component uses slot "default" with shorthand syntax, e.g. <template #default="{ active }">
    const shorthandSyntaxSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.startTag &&
            child.startTag.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot' && attr.key?.argument?.name === 'default';
            });
    });

    // Check if component uses slot "label" with shorthand syntax, e.g. <template #label>
    const shorthandSyntaxLabelSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.startTag &&
            child.startTag.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot' && attr.key?.argument?.name === 'label';
            });
    });

    // Check if component has children without a slot declaration
    const childrenWithoutSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name !== 'template';
    });

    // Check if default slot or children has the fix comment already applied
    const hasDefaultSlotFixComment = templateComments?.some((comment) => {
        if (!comment.value.includes('Remove the "default" slot and use the "options" prop instead')) {
            return false;
        }

        // Check if comment exists in the range of the slot or children
        return comment.loc.start.line >= node.loc.start.line && comment.loc.end.line <= node.loc.end.line;
    });

    // Check if label slot has the fix comment already applied
    const hasLabelSlotFixComment = templateComments?.some((comment) => {
        if (!comment.value.includes('Remove the "label" slot and use the "label" prop instead')) {
            return false;
        }

        // Check if comment exists in the range of the slot or children
        return comment.loc.start.line >= node.loc.start.line && comment.loc.end.line <= node.loc.end.line;
    });

    // Check if the component has the "@update:value" event
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr?.key?.type === 'VDirectiveKey' &&
            attr?.key?.name?.rawName === '@' &&
            attr?.key?.argument?.name === 'update:value';
    });

    // Check if the component has the "v-on:update:value" event
    const updateValueEventLongSyntax = node.startTag.attributes.find((attr) => {
        return attr?.key?.type === 'VDirectiveKey' &&
            attr?.key?.name?.rawName === 'on' &&
            attr?.key?.argument?.name === 'update:value';
    });

    if (valueAttribute) {
        context.report({
            node: valueAttribute,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttribute.key, 'modelValue');
            }
        });
    }

    if (valueAttributeExpression) {
        context.report({
            node: valueAttributeExpression,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttributeExpression.key.argument, 'modelValue');
            }
        });
    }

    if (vModelValueAttribute) {
        context.report({
            node: vModelValueAttribute,
            message: `[${mtComponentName}] The "v-model:value" binding is deprecated. Use "v-model" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(vModelValueAttribute.key, 'v-model');
            }
        });
    }

    if (asideAttribute) {
        context.report({
            node: asideAttribute,
            message: `[${mtComponentName}] The "aside" prop was removed without a replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(asideAttribute);
            }
        });
    }

    if (asideAttributeExpression) {
        context.report({
            node: asideAttributeExpression,
            message: `[${mtComponentName}] The "aside" prop was removed without a replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(asideAttributeExpression);
            }
        });
    }

    if (childrenWithoutSlot && !hasDefaultSlotFixComment) {
        context.report({
            node: childrenWithoutSlot,
            message: `[${mtComponentName}] The "default" slot is deprecated. Use the "options" prop instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextBefore(childrenWithoutSlot.startTag, `<!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->\n`);
            }
        });
    }

    if (shorthandSyntaxSlot && !hasDefaultSlotFixComment) {
        context.report({
            node: shorthandSyntaxSlot,
            message: `[${mtComponentName}] The "default" slot is deprecated. Use the "options" prop instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextBefore(shorthandSyntaxSlot.startTag, `<!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->\n`);
            }
        });
    }

    if (shorthandSyntaxLabelSlot && !hasLabelSlotFixComment) {
        context.report({
            node: shorthandSyntaxLabelSlot,
            message: `[${mtComponentName}] The "label" slot is deprecated. Use the "label" prop instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.insertTextBefore(shorthandSyntaxLabelSlot.startTag, `<!-- TODO Codemod: Remove the "label" slot and use the "label" prop instead -->\n`);
            }
        });
    }

    if (updateValueEvent) {
        context.report({
            node: updateValueEvent,
            message: `[${mtComponentName}] The "update:value" event is deprecated. Use "update:modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(updateValueEvent.key, '@update:modelValue');
            }
        });
    }

    if (updateValueEventLongSyntax) {
        context.report({
            node: updateValueEventLongSyntax,
            message: `[${mtComponentName}] The "update:value" event is deprecated. Use "update:modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(updateValueEventLongSyntax.key, 'v-on:update:modelValue');
            }
        });
    }
}

const mtSelectValidTests = [
    {
        name: '"sw-select-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-select-field />
            </template>`
    },
    {
        name: '"mt-select" fix already applied for: wrong "default" slot usage. Should be replaced with prop "options" [without declaration]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
    },
    {
        name: '"mt-select" fix already applied for: wrong "default" slot usage. Should be replaced with prop "options" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
    },
    {
        name: '"mt-select" fix already applied for: wrong "label" slot usage. Should be replaced with prop "label" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "label" slot and use the "label" prop instead -->
<template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
    }
]

const mtSelectInvalidTests = [
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select value="optionA" />
            </template>`,
        output: `
            <template>
                <mt-select modelValue="optionA" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select value="optionA" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-select :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "v-model:value" binding usage should be replaced with "v-model"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-select v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        filename: 'test.html.twig',
        name: '"mt-select" wrong "v-model:value" binding usage should be replaced with "v-model" [disableFix]',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select aside />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select aside />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select aside="true" />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select aside="true" />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression binding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select :aside="true" />
            </template>`,
        output: `
            <template>
                <mt-select  />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "aside" prop usage. It was removed without a replacement [expression binding, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select :aside="true" />
            </template>`,
        errors: [{
            message: '[mt-select] The "aside" prop was removed without a replacement.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [without declaration]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
<option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [without declaration, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <option value="optionA">Option A</option>
                    <option value="optionB">Option B</option>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "default" slot and use the "options" prop instead -->
<template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "default" slot usage. Should be replaced with prop "options" [short syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <template #default>
                        <option value="optionA">Option A</option>
                        <option value="optionB">Option B</option>
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "default" slot is deprecated. Use the "options" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "label" slot usage. Should be replaced with prop "label" [short syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select>
                    <template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        output: `
            <template>
                <mt-select>
                    <!-- TODO Codemod: Remove the "label" slot and use the "label" prop instead -->
<template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-select" wrong "label" slot usage. Should be replaced with prop "label" [short syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select>
                    <template #label>
                        My Label
                    </template>
                </mt-select>
            </template>`,
        errors: [{
            message: '[mt-select] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select @update:value="onUpdateValue" />
            </template>`,
        output: `
            <template>
                <mt-select @update:modelValue="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select @update:value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:modelValue" [long syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-select v-on:update:value="onUpdateValue" />
            </template>`,
        output: `
            <template>
                <mt-select v-on:update:modelValue="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    },
    {
        name: '"mt-select" event "update:value" was renamed to "update:modelValue" [long syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-select v-on:update:value="onUpdateValue" />
            </template>`,
        errors: [{
            message: '[mt-select] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    },
]

module.exports = {
    handleMtSelect,
    mtSelectValidTests,
    mtSelectInvalidTests
};
