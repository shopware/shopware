/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtDatepicker = (context, node) => {
    const mtComponentName = 'mt-datepicker';

    // Refactor the old usage of sw-datepicker to mt-datepicker after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-datepicker has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-datepicker has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-datepicker uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-datepicker has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    // Check if the mt-datepicker has the slot "label" with shorthand syntax
    const labelSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'label'
            });
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

    if (vModelValue) {
        context.report({
            node: vModelValue,
            message: `[${mtComponentName}] The "v-model:value" binding is deprecated. Use "v-model" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(vModelValue.key, 'v-model');
            }
        });
    }

    if (updateValueEvent) {
        context.report({
            node: updateValueEvent,
            message: `[${mtComponentName}] The "update:value" event is deprecated. Use "update:modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(updateValueEvent.key.argument, 'update:modelValue');
            }
        });
    }

    if (labelSlotShorthand) {
        context.report({
            node: labelSlotShorthand,
            message: `[${mtComponentName}] The "label" slot is deprecated. Use the "label" prop instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                const labelSlot = node.children.find((child) => {
                    return child.type === 'VElement' &&
                        child.name === 'template' &&
                        child.startTag?.attributes.find((attr) => {
                            return attr.key?.name?.name === 'slot'
                                && attr.key?.argument?.name === 'label'
                        });
                });

                const labelSlotValueRaw = labelSlot.children[0].value;
                // Remove \n and multiple spaces from the string
                const labelSlotValue = labelSlotValueRaw.replace(/\n/g, '').replace(/\s+/g, ' ');

                yield fixer.replaceText(labelSlotShorthand, `<!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was: ${labelSlotValue} -->`);
            }
        });
    }
}

const mtDatepickerValidTests = [
    {
        name: '"mt-datepicker" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>Hello</mt-datepicker>
            </template>`
    },
    {
        name: '"sw-datepicker" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-datepicker>Hello</sw-datepicker>
            </template>`
    }
]

const mtDatepickerInvalidTests = [
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker value="yes" />
            </template>`,
        output: `
            <template>
                <mt-datepicker modelValue="yes" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker value="yes" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "v-model:value" binding is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-datepicker @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-datepicker" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>
                    <template #label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        output: `
            <template>
                <mt-datepicker>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker>
                    <template #label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-datepicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        output: `
            <template>
                <mt-datepicker>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-datepicker" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-datepicker>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-datepicker>
            </template>`,
        errors: [{
            message: '[mt-datepicker] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
];

module.exports = {
    mtDatepickerValidTests,
    mtDatepickerInvalidTests,
    handleMtDatepicker
};
