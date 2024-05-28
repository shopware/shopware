/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtNumberField = (context, node) => {
    const mtComponentName = 'mt-number-field';

    // Refactor the old usage of mt-number-field to mt-number-field after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-number-field has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');

    // Check if the mt-number-field has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-number-field has the slot "label" with shorthand syntax
    const labelSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'label'
            });
    });

    // Check if the mt-number-field has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
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
}

// - prop "value" was replaced with "modelValue"
// - slot "label" was removed and should be replaced with "label" prop
// - event "update:value" was replaced with "change"

const mtNumberFieldValidTests = [
    {
        name: '"sw-number-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-number-field />
            </template>`
    },
]

const mtNumberFieldInvalidTests = [
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field value="5" />
            </template>`,
        output: `
            <template>
                <mt-number-field modelValue="5" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field value="5" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-number-field :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field>
                    <template #label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        output: `
            <template>
                <mt-number-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field>
                    <template #label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        output: `
            <template>
                <mt-number-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-number-field>
            </template>`,
        errors: [{
            message: '[mt-number-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-number-field" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-number-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-number-field @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-number-field" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-number-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-number-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
];

module.exports = {
    handleMtNumberField,
    mtNumberFieldValidTests,
    mtNumberFieldInvalidTests
};
