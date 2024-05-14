/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtSwitch = (context, node) => {
    const mtSwitchComponentName = 'mt-switch';

    // Refactor the old usage of sw-switch-field to mt-switch after the migration to the new component
    if (node.name !== mtSwitchComponentName) {
        return;
    }

    // Check if the mt-switch component has the attribute "noMarginTop"
    const noMarginTopAttribute = node.startTag.attributes.find((attr) => {
        return [
            'noMarginTop',
            'no-margin-top',
            'nomargintop'
        ].includes(attr.key.name)
    });
    // Check if the mt-switch component has the attribute expression ":noMarginTop"
    const noMarginTopAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'noMarginTop',
                'no-margin-top',
                'nomargintop'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-switch component has the attribute "size"
    const sizeAttribute = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'size';
    });
    const sizeAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'size';
    });

    // Check if the mt-switch component has the attribute "id"
    const idAttribute = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'id';
    })
    const idAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'id';
    });

    // Check if the mt-switch component has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'value';
    });
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-switch component uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-switch component has the attribute "ghostValue"
    const ghostValueAttribute = node.startTag.attributes.find((attr) => {
        return [
            'ghostValue',
            'ghost-value',
            'ghostvalue'
        ].includes(attr.key.name)
    });
    // Check if the mt-switch component has the attribute expression ":ghostValue"
    const ghostValueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'ghostValue',
                'ghost-value',
                'ghostvalue'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-switch component has the attribute "padded"
    const paddedAttribute = node.startTag.attributes.find((attr) => {
        return attr.key.name === 'padded';
    });
    const paddedAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' && attr?.key?.argument?.name === 'padded';
    });

    // Check if the mt-switch component has the attribute "partlyChecked"
    const partlyCheckedAttribute = node.startTag.attributes.find((attr) => {
        return [
            'partlyChecked',
            'partly-checked',
            'partlychecked'
        ].includes(attr.key.name)
    });
    // Check if the mt-switch component has the attribute expression ":partlyChecked"
    const partlyCheckedAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            [
                'partlyChecked',
                'partly-checked',
                'partlychecked'
            ].includes(attr?.key?.argument?.name);
    });

    // Check if the mt-switch component has the slot "label" with shorthand syntax
    const labelSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'label'
            });
    });

    // Check if the mt-switch component has the slot "hint" with shorthand syntax
    const hintSlotShorthand = node.children.find((child) => {
        return child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes.find((attr) => {
                return attr.key?.name?.name === 'slot'
                    && attr.key?.argument?.name === 'hint'
            });
    });

    if (noMarginTopAttribute) {
        context.report({
            node: noMarginTopAttribute,
            message: `[${mtSwitchComponentName}] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(noMarginTopAttribute.key, 'removeTopMargin');
            }
        });
    }

    if (noMarginTopAttributeExpression) {
        context.report({
            node: noMarginTopAttributeExpression,
            message: `[${mtSwitchComponentName}] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(noMarginTopAttributeExpression.key.argument, 'removeTopMargin');
            }
        });
    }

    if (sizeAttribute) {
        context.report({
            node: sizeAttribute,
            message: `[${mtSwitchComponentName}] The "size" prop is removed with no replacement.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(sizeAttribute);
            }
        });
    }

    if (sizeAttributeExpression) {
        context.report({
            node: sizeAttributeExpression,
            message: `[${mtSwitchComponentName}] The "size" prop is removed with no replacement.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(sizeAttributeExpression);
            }
        });
    }

    if (idAttribute) {
        context.report({
            node: idAttribute,
            message: `[${mtSwitchComponentName}] The "id" prop is removed with no replacement.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(idAttribute);
            }
        });
    }

    if (idAttributeExpression) {
        context.report({
            node: idAttributeExpression,
            message: `[${mtSwitchComponentName}] The "id" prop is removed with no replacement.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(idAttributeExpression);
            }
        });
    }

    if (valueAttribute) {
        context.report({
            node: valueAttribute,
            message: `[${mtSwitchComponentName}] The "value" prop is removed. Use "checked" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttribute.key, 'checked');
            }
        });
    }

    if (vModelValue) {
        context.report({
            node: vModelValue,
            message: `[${mtSwitchComponentName}] The "value" prop is removed. Use ":checked" in combination with "@change".`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                const vModelExpressionRaw = context.getSourceCode().text.slice(vModelValue.value.range[0], vModelValue.value.range[1]);
                // Remove quotes at the beginning and end of the string if exist
                const vModelExpression = vModelExpressionRaw.replace(/^['"]|['"]$/g, '');

                yield fixer.replaceText(vModelValue.key, ':checked');
                yield fixer.insertTextAfter(vModelValue, ` @change="${vModelExpression} = $event"`);
            }
        });
    }

    if (valueAttributeExpression) {
        context.report({
            node: valueAttributeExpression,
            message: `[${mtSwitchComponentName}] The "value" prop is removed. Use "checked" instead.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttributeExpression.key.argument, 'checked');
            }
        });
    }

    if (ghostValueAttribute) {
        context.report({
            node: ghostValueAttribute,
            message: `[${mtSwitchComponentName}] The "ghostValue" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(ghostValueAttribute);
            }
        });
    }

    if (ghostValueAttributeExpression) {
        context.report({
            node: ghostValueAttributeExpression,
            message: `[${mtSwitchComponentName}] The "ghostValue" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(ghostValueAttributeExpression);
            }
        });
    }

    if (paddedAttribute) {
        context.report({
            node: paddedAttribute,
            message: `[${mtSwitchComponentName}] The "padded" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(paddedAttribute);
            }
        });
    }

    if (paddedAttributeExpression) {
        context.report({
            node: paddedAttributeExpression,
            message: `[${mtSwitchComponentName}] The "padded" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(paddedAttributeExpression);
            }
        });
    }

    if (partlyCheckedAttribute) {
        context.report({
            node: partlyCheckedAttribute,
            message: `[${mtSwitchComponentName}] The "partlyChecked" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(partlyCheckedAttribute);
            }
        });
    }

    if (partlyCheckedAttributeExpression) {
        context.report({
            node: partlyCheckedAttributeExpression,
            message: `[${mtSwitchComponentName}] The "partlyChecked" prop is removed.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.remove(partlyCheckedAttributeExpression);
            }
        });
    }

    if (labelSlotShorthand) {
        context.report({
            node: labelSlotShorthand,
            message: `[${mtSwitchComponentName}] The "label" slot is removed. Use the "label" prop instead.`,
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
            message: `[${mtSwitchComponentName}] The "hint" slot is removed with no replacement.`,
            *fix(fixer) {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(hintSlotShorthand, `<!-- Slot "hint" was removed with no replacement. -->`);
            }
        });
    }
}

const mtSwitchValidChecks = [
    {
        name: '"sw-switch-field" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-switch-field></sw-switch-field>
            </template>
        `,
    }
]
const mtSwitchInvalidChecks = [
    {
        name: '"mt-switch" wrong "noMarginTop" prop usage should be replaced with "removeTopMargin"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch noMarginTop />
            </template>`,
        output: `
            <template>
                <mt-switch removeTopMargin />
            </template>`,
        errors: [{
            message: '[mt-switch] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.',
        }]
    },
    {
        name: '"mt-switch" wrong "noMarginTop" prop usage should be replaced with "removeTopMargin" [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch noMarginTop />
            </template>`,
        errors: [{
            message: '[mt-switch] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.',
        }]
    },
    {
        name: '"mt-switch" wrong "noMarginTop" prop usage should be replaced with "removeTopMargin" [bindUsage]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :noMarginTop="true" />
            </template>`,
        output: `
            <template>
                <mt-switch :removeTopMargin="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.',
        }]
    },
    {
        name: '"mt-switch" wrong "noMarginTop" prop usage should be replaced with "removeTopMargin" [bindUsage]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :noMarginTop="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "noMarginTop" prop is removed. Use "removeTopMargin" instead.',
        }]
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch size="small" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed. [noSelfClosing]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch size="small">Test</mt-switch>
            </template>`,
        output: `
            <template>
                <mt-switch >Test</mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch size="small" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed. [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch size />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed. [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch size />
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "size" prop usage. Should be removed. [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :size="mySize" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "size" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch id="example-identifier" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch id="example-identifier" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed. [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch id />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed. [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch id />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed. [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :id="myId" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "id" prop usage. Should be removed. [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :id="myId" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "id" prop is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong v-model "value" usage. Should be replaced with "checked" using event listener to @change',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch v-model:value="myExampleValue" />
            </template>`,
        output: `
            <template>
                <mt-switch :checked="myExampleValue" @change="myExampleValue = $event" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use ":checked" in combination with "@change".',
        }],
    },
    {
        name: '"mt-switch" wrong v-model "value" usage. Should be replaced with "checked" using event listener to @change [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch v-model:value="myExampleValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use ":checked" in combination with "@change".',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked".',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch value="true" />
            </template>`,
        output: `
            <template>
                <mt-switch checked="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked". [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch value="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked". [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-switch :checked="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked". [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked". [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch value />
            </template>`,
        output: `
            <template>
                <mt-switch checked />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "checked". [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch value />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "checked" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch ghostValue="true" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch ghostValue="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed. [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch ghostValue />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed. [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch ghostValue />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed. [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :ghostValue="myValue" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "ghostValue" prop usage. Should be removed. [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :ghostValue="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "ghostValue" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch padded="true" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch padded="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed. [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch padded />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed. [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch padded />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed. [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :padded="myValue" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "padded" prop usage. Should be removed. [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :padded="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "padded" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch partlyChecked="true" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch partlyChecked="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed. [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch partlyChecked />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed. [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch partlyChecked />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed. [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :partlyChecked="myValue" />
            </template>`,
        output: `
            <template>
                <mt-switch  />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "partlyChecked" prop usage. Should be removed. [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :partlyChecked="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "partlyChecked" prop is removed.',
        }],
    },
    {
        name: '"mt-switch" wrong "label" slot usage. Should be replaced with "label" prop. [shorthandSyntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch>
                    <template #label>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        output: `
            <template>
                <mt-switch>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  Foobar  -->
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "label" slot is removed. Use the "label" prop instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "label" slot usage. Should be replaced with "label" prop. [shorthandSyntax, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch>
                    <template #label>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "label" slot is removed. Use the "label" prop instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "label" slot usage. Should be replaced with "label" prop.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch>
                    <template v-slot:label>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        output: `
            <template>
                <mt-switch>
                    <!-- Slot "label" was removed and should be replaced with "label" prop. Previous value was:  Foobar  -->
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "label" slot is removed. Use the "label" prop instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "label" slot usage. Should be replaced with "label" prop. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch>
                    <template v-slot:label>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "label" slot is removed. Use the "label" prop instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "hint" slot usage. Should be removed. [shorthandSyntax]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch>
                    <template #hint>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        output: `
            <template>
                <mt-switch>
                    <!-- Slot "hint" was removed with no replacement. -->
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "hint" slot is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "hint" slot usage. Should be removed. [disabledFix, shorthandSyntax]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch>
                    <template #hint>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "hint" slot is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "hint" slot usage. Should be removed.',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch>
                    <template v-slot:hint>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        output: `
            <template>
                <mt-switch>
                    <!-- Slot "hint" was removed with no replacement. -->
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "hint" slot is removed with no replacement.',
        }],
    },
    {
        name: '"mt-switch" wrong "hint" slot usage. Should be removed. [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch>
                    <template v-slot:hint>
                        Foobar
                    </template>
                </mt-switch>
            </template>`,
        errors: [{
            message: '[mt-switch] The "hint" slot is removed with no replacement.',
        }],
    },
];

module.exports = {
    mtSwitchValidChecks,
    mtSwitchInvalidChecks,
    handleMtSwitch
};
