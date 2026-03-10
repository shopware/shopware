/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtProgressBar = (context, node) => {
    let mtComponentName = 'mt-progress-bar';

    if (node.name !== mtComponentName) {
        return;
    }

    // Check if the mt-progress-bar has the attribute "value"
    const valueAttribute = node.startTag.attributes.find((attr) => attr.key.name === 'value');
    // Check if the mt-progress-bar has the attribute expression "value"
    const valueAttributeExpression = node.startTag.attributes.find((attr) => {
        return attr?.key?.name?.name === 'bind' &&
            attr?.key?.argument?.name === 'value';
    });

    // Check if the mt-progress-bar uses v-model:value
    const vModelValue = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'model' &&
            attr.key?.argument?.name === 'value';
    });

    // Check if the mt-progress-bar has the event "update:value"
    const updateValueEvent = node.startTag.attributes.find((attr) => {
        return attr.key?.name?.name === 'on' &&
            attr.key?.argument?.name === 'update:value';
    });

    if (valueAttribute) {
        context.report({
            node: valueAttribute,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "model-value" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttribute.key, 'model-value');
            }
        });
    }

    if (vModelValue) {
        context.report({
            node: vModelValue,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "model-value" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(vModelValue.key, 'v-model');
            }
        });
    }

    if (valueAttributeExpression) {
        context.report({
            node: valueAttributeExpression,
            message: `[${mtComponentName}] The "value" prop is deprecated. Use "model-value" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(valueAttributeExpression.key.argument, 'model-value');
            }
        });
    }

    if (updateValueEvent) {
        context.report({
            node: updateValueEvent,
            message: `[${mtComponentName}] The "update:value" event is deprecated. Use "update:mode-value" instead.`,
            *fix(fixer)  {
                if (context.options.includes('disableFix')) return;

                yield fixer.replaceText(updateValueEvent.key.argument, 'update:model-value');
            }
        });
    }
}

const mtProgressBarValidTests = [
    {
        name: '"sw-progress-bar" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-progress-bar />
            </template>`
    }
];

const mtProgressBarInvalidTests = [
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar value="Hello World" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar model-value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar value="Hello World" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "value" prop usage should be replaced with "model-value" [expression, disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "v-model:value" usage should be replaced with default v-model',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar v-model="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "v-model:value" usage should be replaced with default v-model [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar v-model:value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "value" prop is deprecated. Use "model-value" instead.',
        }]
    },
    {
        name: '"mt-progress-bar" wrong "update:value" event usage should be replaced with "update:mode-value"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-progress-bar @update:value="updateValue" />
            </template>`,
        output: `
            <template>
                <mt-progress-bar @update:model-value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
    {
        name: '"mt-progress-bar" wrong "update:value" event usage should be replaced with "update:mode-value" [disableFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-progress-bar @update:value="updateValue" />
            </template>`,
        errors: [{
            message: '[mt-progress-bar] The "update:value" event is deprecated. Use "update:mode-value" instead.',
        }],
    },
];

module.exports = {
    handleMtProgressBar,
    mtProgressBarValidTests,
    mtProgressBarInvalidTests
};
