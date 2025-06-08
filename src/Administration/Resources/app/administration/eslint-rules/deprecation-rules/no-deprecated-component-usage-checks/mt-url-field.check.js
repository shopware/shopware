/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtUrlField = (context, node) => {
    const mtComponentName = 'mt-url-field';

    // Refactor the old usage of mt-url-field to mt-url-field after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-url-field has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-url-field has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-url-field uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-url-field has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    // Check if the mt-url-field has the slot "label" with shorthand syntax
    const labelSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'label'
            });
    });

    // Check if the mt-url-field has the slot "label" with shorthand syntax
    const hintSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'hint'
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

    if (vModelValue) {
        context.report({
            node: vModelValue,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "modelValue" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(vModelValue.key, 'v-model');
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

    if (hintSlotShorthand) {
        context.report({
            node: hintSlotShorthand,
            message: `[${mtComponentName}] The "hint" slot is deprecated.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                const hintSlot = node.children.find((child) => {
                    return child.type === 'VElement' &&
                        child.name === 'template' &&
                        child.startTag?.attributes.find((attr) => {
                            return attr.key?.name?.name === 'slot'
                                    && attr.key?.argument?.name === 'hint'
                        });
                });

                const hintSlotValueRaw = hintSlot.children[0].value;
                // Remove \n and multiple spaces from the string
                const hintSlotValue = hintSlotValueRaw.replace(/\n/g, '').replace(/\s+/g, ' ');

                yield fixer.replaceText(hintSlotShorthand, `<!-- Slot "hint" was removed without replacement. Previous value was: ${hintSlotValue} -->`);
            }
        });
    }
}

const mtUrlFieldValidTests = [
    {
        name: '"sw-url-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-url-field />
            </template>`
    }
]

const mtUrlFieldInvalidTests = [
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-url-field modelValue="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-url-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-url-field  />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-url-field  />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-url-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-url-field" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-url-field @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-url-field" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-url-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template #label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template #label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-url-field" wrong "hint" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field>
                    <!-- Slot "hint" was removed without replacement. Previous value was:  My Hint  -->
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "hint" slot is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "hint" slot usage should be removed [shorthand syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "hint" slot is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "hint" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-url-field>
            </template>`,
        output: `
            <template>
                <mt-url-field>
                    <!-- Slot "hint" was removed without replacement. Previous value was:  My Hint  -->
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "hint" slot is deprecated.',
        }]
    },
    {
        name: '"mt-url-field" wrong "hint" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-url-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-url-field>
            </template>`,
        errors: [{
            message: '[mt-url-field] The "hint" slot is deprecated.',
        }]
    }
];

module.exports = {
    handleMtUrlField,
    mtUrlFieldValidTests,
    mtUrlFieldInvalidTests
};
