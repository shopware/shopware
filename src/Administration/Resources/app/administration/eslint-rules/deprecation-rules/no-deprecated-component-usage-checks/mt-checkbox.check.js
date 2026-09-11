/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtCheckbox = (context, node) => {
    const mtComponentName = 'mt-checkbox';

    // Refactor the old usage of mt-checkbox to mt-checkbox after the migration to the new component
    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-checkbox has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-checkbox has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // "v-model" (modelValue) is the current API. "v-model:checked" is deprecated at runtime,
    // but still used in many templates, so it cannot be flagged without a mass migration.

    // Check if the mt-checkbox has the slot "hint"
    const hintSlot = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'hint'
            });
    });

    // Attribute checks
    const idAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'id');
    const ghostValueAttribute = node.startTag.attributes.find((attr) => {
        return [
            'ghostValue',
            'ghost-value',
            'ghostvalue'
        ].includes(attr.key.name)
    });
    const partlyCheckedAttribute = node.startTag.attributes.find((attr) => {
        return [
            'partlyChecked',
            'partly-checked',
            'partlychecked'
        ].includes(attr.key.name)
    });
    const paddedAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'padded');

    // Attribute expression checks
    const idAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'id';
    });
    const ghostValueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'ghostValue',
                'ghost-value',
                'ghostvalue'
            ].includes(attr?.key?.argument?.name);
    });
    const partlyCheckedAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'partlyChecked',
                'partly-checked',
                'partlychecked'
            ].includes(attr?.key?.argument?.name);
    });
    const paddedAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'padded';
    });

    // Check if the mt-checkbox has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    if (valueAttribute) {
        context.report({
            node: valueAttribute,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "v-model" instead.`,
        });
    }

    if (valueAttributeExpression) {
        context.report({
            node: valueAttributeExpression,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "v-model" instead.`,
        });
    }

    if (hintSlot) {
        context.report({
            node: hintSlot,
            message: `[${mtComponentName}] The "hint" slot is deprecated. Use the "label" prop instead.`,
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

                yield fixer.replaceText(hintSlot, `<!-- Slot "hint" was removed and should be replaced with "label" prop. Previous value was: ${hintSlotValue} -->`);
            }
        });
    }

    if (idAttribute) {
        context.report({
            node: idAttribute,
            message: `[${mtComponentName}] The "id" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(idAttribute);
            }
        });
    }

    if (idAttributeExpression) {
        context.report({
            node: idAttributeExpression,
            message: `[${mtComponentName}] The "id" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(idAttributeExpression);
            }
        });
    }

    if (ghostValueAttribute) {
        context.report({
            node: ghostValueAttribute,
            message: `[${mtComponentName}] The "ghostValue" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(ghostValueAttribute);
            }
        });
    }

    if (ghostValueAttributeExpression) {
        context.report({
            node: ghostValueAttributeExpression,
            message: `[${mtComponentName}] The "ghostValue" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(ghostValueAttributeExpression);
            }
        });
    }

    if (partlyCheckedAttribute) {
        context.report({
            node: partlyCheckedAttribute,
            message: `[${mtComponentName}] The "partlyChecked" prop is deprecated. Use "partial" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(partlyCheckedAttribute.key, 'partial');
            }
        });
    }

    if (partlyCheckedAttributeExpression) {
        context.report({
            node: partlyCheckedAttributeExpression,
            message: `[${mtComponentName}] The "partlyChecked" prop is deprecated. Use "partial" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(partlyCheckedAttributeExpression.key.argument, 'partial');
            }
        });
    }

    if (paddedAttribute) {
        context.report({
            node: paddedAttribute,
            message: `[${mtComponentName}] The "padded" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(paddedAttribute);
            }
        });
    }

    if (paddedAttributeExpression) {
        context.report({
            node: paddedAttributeExpression,
            message: `[${mtComponentName}] The "padded" prop is deprecated. Remove it without replacement.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(paddedAttributeExpression);
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

const mtCheckboxValidTests = [
    {
        name: '"sw-checkbox-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-checkbox-field />
            </template>`
    },
    {
        name: '"mt-checkbox" with "v-model:checked" is still allowed until the mass migration',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model:checked="isCheckedValue" />
            </template>`,
    },
    {
        name: '"mt-checkbox" with plain "v-model" is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox v-model="isCheckedValue" />
            </template>`,
    },
]

const mtCheckboxInvalidTests = [
    {
        name: '"mt-checkbox" wrong "value" prop usage should be reported',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox value="yes" />
            </template>`,
        output: null,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong "value" prop usage should be reported [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :value="myValue" />
            </template>`,
        output: null,
        errors: [{
            message: '[mt-checkbox] The "value" prop is deprecated. Use "v-model" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template v-slot:hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        output: `
            <template>
                <mt-checkbox>
                    <!-- Slot "hint" was removed and should be replaced with "label" prop. Previous value was:  Hello Shopware  -->
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox>
                    <template v-slot:hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [shorthandSyntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox>
                    <template #hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        output: `
            <template>
                <mt-checkbox>
                    <!-- Slot "hint" was removed and should be replaced with "label" prop. Previous value was:  Hello Shopware  -->
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong slot "hint" usage. Was removed without replacement [shorthandSyntax, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox>
                    <template #hint>
                        Hello Shopware
                    </template>
                </mt-checkbox>
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "hint" slot is deprecated. Use the "label" prop instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox id="checkbox-id" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox id="checkbox-id" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :id="checkboxId" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "id" usage should be removed without replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :id="checkboxId" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "id" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox ghostValue="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox ghostValue="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :ghostValue="yes" />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "ghostValue" usage should be removed without replacement [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :ghostValue="yes" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "ghostValue" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox partlyChecked />
            </template>`,
        output: `
            <template>
                <mt-checkbox partial />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox partlyChecked />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox :partlyChecked="isChecked" />
            </template>`,
        output: `
            <template>
                <mt-checkbox :partial="isChecked" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "partlyChecked" usage should be replaced with "partial" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox :partlyChecked="isChecked" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "partlyChecked" prop is deprecated. Use "partial" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "padded" usage should be removed without replacement',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox padded />
            </template>`,
        output: `
            <template>
                <mt-checkbox  />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "padded" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong property "padded" usage should be removed without replacement [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox padded />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "padded" prop is deprecated. Remove it without replacement.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:modelValue"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-checkbox @update:modelValue="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    },
    {
        name: '"mt-checkbox" wrong event "update:value" usage should be replaced with "update:modelValue" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-checkbox @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-checkbox] The "update:value" event is deprecated. Use "update:modelValue" instead.',
        }]
    }
]

module.exports = {
    handleMtCheckbox,
    mtCheckboxValidTests,
    mtCheckboxInvalidTests
};
