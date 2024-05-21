/** @param {RuleContext} context
 *  @param {VElement} node
 *  @param emailMode
 */
const handleMtTextField = (context, node, emailMode = false) => {
    let mtComponentName = 'mt-text-field';
    if (emailMode) {
        mtComponentName = 'mt-email-field';
    }

    // Refactor the old usage of mt-text-field to mt-text-field after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-text-field has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-text-field has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-text-field uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-text-field has the attribute "size"
    const sizeAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'size');
    const sizeAttributeIsMedium = sizeAttribute?.value?.value === 'medium';

    // Check if the mt-text-field has the attribute "isInvalid"
    const isInvalidAttribute = node.startTag.attributes.find((attr) => {
        return [
            'isInvalid',
            'is-invalid',
            'isinvalid'
        ].includes(attr.key.name)
    });
    // Check if the mt-text-field has the attribute expression "isInvalid"
    const isInvalidAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'isInvalid',
                'is-invalid',
                'isinvalid'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-text-field has the attribute "aiBadge"
    const aiBadgeAttribute = node.startTag.attributes.find((attr) => {
        return [
            'aiBadge',
            'ai-badge',
            'aibadge'
        ].includes(attr.key.name)
    });
    // Check if the mt-text-field has the attribute expression "aiBadge"
    const aiBadgeAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'aiBadge',
                'ai-badge',
                'aibadge'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-text-field has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    // Check if the mt-text-field has the event "base-field-mounted"
    const baseFieldMountedEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'base-field-mounted';
    });

    // Check if the mt-text-field has the slot "label" with shorthand syntax
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

    if (aiBadgeAttribute) {
        context.report({
            node: aiBadgeAttribute,
            message: `[${mtComponentName}] The "aiBadge" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(aiBadgeAttribute);
            }
        });
    }

    if (aiBadgeAttributeExpression) {
        context.report({
            node: aiBadgeAttributeExpression,
            message: `[${mtComponentName}] The "aiBadge" prop is deprecated. Remove it.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(aiBadgeAttributeExpression);
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
}

const mtTextFieldValidTests = [
    {
        name: '"sw-text-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-text-field />
            </template>`
    }
];

const mtTextFieldInvalidTests = [
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-text-field modelValue="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "modelValue" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field :modelValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "value" prop usage should be replaced with "modelValue" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "value" prop is deprecated. Use "modelValue" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "size" prop "medium" usage should be replaced with "default"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field size="medium" />
            </template>`,
        output: `
            <template>
                <mt-text-field size="default" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "size" prop usage should be replaced with "default" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field size="medium" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "size" prop value "medium" is deprecated. Use "default" instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field isInvalid />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field isInvalid />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :isInvalid="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "isInvalid" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :isInvalid="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "isInvalid" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field aiBadge />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field aiBadge />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop expression usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field :aiBadge="1 == 1" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "aiBadge" prop expression usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field :aiBadge="1 == 1" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "aiBadge" prop is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "update:value" event usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-text-field @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-text-field" wrong "update:value" event usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }],
    },
    {
        name: '"mt-text-field" wrong "base-field-mounted" event usage should be removed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field @base-field-mounted="onFieldMounted" />
            </template>`,
        output: `
            <template>
                <mt-text-field  />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "base-field-mounted" event usage should be removed [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field @base-field-mounted="onFieldMounted" />
            </template>`,
        errors: [{
            message: '[mt-text-field] The "base-field-mounted" event is deprecated. Remove it.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [shorthand syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-text-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [disableFix, shorthand syntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field>
                    <template #label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [v-slot syntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-text-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        output: `
            <template>
                <mt-text-field>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  My Label  -->
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-text-field" wrong "label" slot usage should be removed [v-slot syntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-text-field>
                    <template v-slot:label>
                        My Label
                    </template>
                </mt-text-field>
            </template>`,
        errors: [{
            message: '[mt-text-field] The "label" slot is deprecated. Use the "label" prop instead.',
        }]
    },
];

module.exports = {
    handleMtTextField,
    mtTextFieldValidTests,
    mtTextFieldInvalidTests
};
