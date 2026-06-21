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
        name: '"mt-switch" wrong v-model "value" usage. Should be replaced with "v-model"',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch v-model:value="myExampleValue" />
            </template>`,
        output: `
            <template>
                <mt-switch v-model="myExampleValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "v-model" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong v-model "value" usage. Should be replaced with "v-model" [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch v-model:value="myExampleValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "v-model" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value".',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch value="true" />
            </template>`,
        output: `
            <template>
                <mt-switch model-value="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value". [disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch value="true" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value". [expression]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch :value="myValue" />
            </template>`,
        output: `
            <template>
                <mt-switch :model-value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value". [expression, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch :value="myValue" />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value". [withoutBinding]',
        filename: 'test.html.twig',
        code: `
            <template>
                <mt-switch value />
            </template>`,
        output: `
            <template>
                <mt-switch model-value />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
        }],
    },
    {
        name: '"mt-switch" wrong "value" prop usage. Should be replaced with "model-value". [withoutBinding, disabledFix]',
        filename: 'test.html.twig',
        options: ['disableFix'],
        code: `
            <template>
                <mt-switch value />
            </template>`,
        errors: [{
            message: '[mt-switch] The "value" prop is removed. Use "model-value" instead.',
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
    mtSwitchInvalidChecks
};