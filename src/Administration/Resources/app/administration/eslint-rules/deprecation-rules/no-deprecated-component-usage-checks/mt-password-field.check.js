/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtPasswordField = (context, node) => {
    const mtComponentName = 'mt-password-field';

    // Refactor the old usage of mt-password-field to mt-password-field after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-password-field has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-password-field has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-password-field uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-password-field has the attribute "size"
    const sizeAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'size');
    const sizeAttributeIsMedium = sizeAttribute?.value?.value === 'medium';

    // Check if the mt-password-field has the attribute "isInvalid"
    const isInvalidAttribute = node.startTag.attributes.find((attr) => {
        return [
            'isInvalid',
            'is-invalid',
            'isinvalid'
        ].includes(attr.key.name)
    });
    // Check if the mt-password-field has the attribute expression "isInvalid"
    const isInvalidAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'isInvalid',
                'is-invalid',
                'isinvalid'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-password-field has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    // Check if the mt-password-field has the event "base-field-mounted"
    const baseFieldMountedEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'base-field-mounted';
    });

    // Check if the mt-password-field has the slot "label" with shorthand syntax
    const labelSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'label'
            });
    });

    // Check if the mt-password-field has the slot "hint" with shorthand syntax
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

    if (sizeAttribute && sizeAttributeIsMedium) {
        context.report({
            node: sizeAttribute,
            message: `[${mtComponentName}] The "size" prop value "medium" is deprecated. Use "default" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(sizeAttribute.value, '"default"');
            }
        });
    }

    if (isInvalidAttribute) {
        context.report({
            node: isInvalidAttribute,
            message: `[${mtComponentName}] The "isInvalid" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(isInvalidAttribute);
            }
        });
    }

    if (isInvalidAttributeExpression) {
        context.report({
            node: isInvalidAttributeExpression,
            message: `[${mtComponentName}] The "isInvalid" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(isInvalidAttributeExpression);
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

    if (baseFieldMountedEvent) {
        context.report({
            node: baseFieldMountedEvent,
            message: `[${mtComponentName}] The "base-field-mounted" event is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(baseFieldMountedEvent);
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
            message: `[${mtComponentName}] The "hint" slot is deprecated. Use the "hint" prop instead.`,
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

                yield fixer.replaceText(hintSlotShorthand, `<!-- Slot "hint" was removed and should be replaced with "hint" prop. Previous value was: ${hintSlotValue} -->`);
            }
        });
    }
}

const mtPasswordFieldValidTests = [
    {
        name: '"sw-password-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-password-field />
            </template>`
    }
]

const mtPasswordFieldInvalidTests = [
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-password-field modelValue="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-password-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-password-field @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-password-field" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-password-field" wrong "base-field-mounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field @base-field-mounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-password-field  />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "base-field-mounted" event usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field @base-field-mounted="onFieldMounted" />
            </template>`,
        errors: [{
            message: '[mt-password-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template #label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template #label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "hint" was removed and should be replaced with "hint" prop. Previous value was:  My Hint  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template #hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        output: `
            <template>
                <mt-password-field>
                    <!-- Slot "hint" was removed and should be replaced with "hint" prop. Previous value was:  My Hint  -->
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    },
    {
        name: '"mt-password-field" wrong "hint" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-password-field>
                    <template v-slot:hint>
                        My Hint
                    </template>
                </mt-password-field>
            </template>`,
        errors: [{
            message: '[mt-password-field] The "hint" slot is deprecated. Use the "hint" prop instead.',
        }]
    }
];

module.exports = {
    handleMtPasswordField,
    mtPasswordFieldValidTests,
    mtPasswordFieldInvalidTests
};
