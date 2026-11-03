const { withRegistryMessageContext } = require('../test-utils/with-registry-message-context');
const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (invalid field components)', rule, {
    valid: [],
    invalid: [
        ...withRegistryMessageContext([
            {
                name: '"sw-text-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-text-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-text-field - please check if everything works correctly -->
    <mt-text-field />
</template>`,
                errors: [
                    {
                        message: '"sw-text-field" is deprecated. Please use "mt-text-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-text-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-text-field />
</template>`,
                errors: [
                    {
                        message: '"sw-text-field" is deprecated. Please use "mt-text-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-number-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-number-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-number-field - please check if everything works correctly -->
    <mt-number-field />
</template>`,
                errors: [
                    {
                        message: '"sw-number-field" is deprecated. Please use "mt-number-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-number-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-number-field />
</template>`,
                errors: [
                    {
                        message: '"sw-number-field" is deprecated. Please use "mt-number-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-checkbox-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-checkbox-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-checkbox-field - please check if everything works correctly -->
    <mt-checkbox />
</template>`,
                errors: [
                    {
                        message: '"sw-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
                    },
                ],
            },
            {
                name: '"sw-checkbox-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-checkbox-field />
</template>`,
                errors: [
                    {
                        message: '"sw-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
                    },
                ],
            },
            {
                name: '"sw-switch-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-switch-field>Hello</sw-switch-field>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-switch-field - please check if everything works correctly -->
    <mt-switch>Hello</mt-switch>
</template>`,
                errors: [
                    {
                        message: '"sw-switch-field" is deprecated. Please use "mt-switch" instead.',
                    },
                ],
            },
            {
                name: '"sw-switch-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-switch-field>Hello</sw-switch-field>
</template>`,
                errors: [
                    {
                        message: '"sw-switch-field" is deprecated. Please use "mt-switch" instead.',
                    },
                ],
            },
            {
                name: '"sw-colorpicker" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-colorpicker />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-colorpicker - please check if everything works correctly -->
    <mt-colorpicker />
</template>`,
                errors: [
                    {
                        message: '"sw-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
                    },
                ],
            },
            {
                name: '"sw-colorpicker" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-colorpicker />
</template>`,
                errors: [
                    {
                        message: '"sw-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
                    },
                ],
            },
            {
                name: '"sw-email-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-email-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-email-field - please check if everything works correctly -->
    <mt-email-field />
</template>`,
                errors: [
                    {
                        message: '"sw-email-field" is deprecated. Please use "mt-email-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-email-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-email-field />
</template>`,
                errors: [
                    {
                        message: '"sw-email-field" is deprecated. Please use "mt-email-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-password-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-password-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-password-field - please check if everything works correctly -->
    <mt-password-field />
</template>`,
                errors: [
                    {
                        message: '"sw-password-field" is deprecated. Please use "mt-password-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-password-field" converts to mt-password-field and keeps its "hint" slot',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-password-field><template #hint>Min 8 chars</template></sw-password-field>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-password-field - please check if everything works correctly -->
    <mt-password-field><template #hint>Min 8 chars</template></mt-password-field>
</template>`,
                errors: [
                    {
                        message: '"sw-password-field" is deprecated. Please use "mt-password-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-password-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-password-field />
</template>`,
                errors: [
                    {
                        message: '"sw-password-field" is deprecated. Please use "mt-password-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-url-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-url-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-url-field - please check if everything works correctly -->
    <mt-url-field />
</template>`,
                errors: [
                    {
                        message: '"sw-url-field" is deprecated. Please use "mt-url-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-url-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-url-field />
</template>`,
                errors: [
                    {
                        message: '"sw-url-field" is deprecated. Please use "mt-url-field" instead.',
                    },
                ],
            },
            {
                name: '"sw-textarea-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-textarea-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-textarea-field - please check if everything works correctly -->
    <mt-textarea />
</template>`,
                errors: [
                    {
                        message: '"sw-textarea-field" is deprecated. Please use "mt-textarea" instead.',
                    },
                ],
            },
            {
                name: '"sw-textarea-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-textarea-field />
</template>`,
                errors: [
                    {
                        message: '"sw-textarea-field" is deprecated. Please use "mt-textarea" instead.',
                    },
                ],
            },
            {
                name: '"sw-select-field" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-select-field />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-select-field - please check if everything works correctly -->
    <mt-select />
</template>`,
                errors: [
                    {
                        message: '"sw-select-field" is deprecated. Please use "mt-select" instead.',
                    },
                ],
            },
            {
                name: '"sw-select-field" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-select-field />
</template>`,
                errors: [
                    {
                        message: '"sw-select-field" is deprecated. Please use "mt-select" instead.',
                    },
                ],
            },
        ]),
    ],
});
