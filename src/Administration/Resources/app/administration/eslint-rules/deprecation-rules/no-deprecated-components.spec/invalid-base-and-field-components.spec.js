const { withRegistryMessageContext } = require('../registry/with-registry-message-context');
const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (invalid base and field components)', rule, {
    valid: [],
    invalid: [
        ...withRegistryMessageContext([
            {
                name: '"sw-icon" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-icon name="regular-times-s" />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-icon - please check if everything works correctly -->
    <mt-icon name="regular-times-s" />
</template>`,
                errors: [
                    {
                        message: '"sw-icon" is deprecated. Please use "mt-icon" instead.',
                    },
                ],
            },
            {
                name: '"sw-icon" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-icon name="regular-times-s" />
</template>`,
                errors: [
                    {
                        message: '"sw-icon" is deprecated. Please use "mt-icon" instead.',
                    },
                ],
            },
            {
                name: '"sw-card" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-card>Hello</sw-card>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-card - please check if everything works correctly -->
    <mt-card>Hello</mt-card>
</template>`,
                errors: [
                    {
                        message: '"sw-card" is deprecated. Please use "mt-card" instead.',
                    },
                ],
            },
            {
                name: '"sw-card" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-card>Hello</sw-card>
</template>`,
                errors: [
                    {
                        message: '"sw-card" is deprecated. Please use "mt-card" instead.',
                    },
                ],
            },
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
                name: '"sw-loader" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-loader />
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-loader - please check if everything works correctly -->
    <mt-loader />
</template>`,
                errors: [
                    {
                        message: '"sw-loader" is deprecated. Please use "mt-loader" instead.',
                    },
                ],
            },
            {
                name: '"sw-loader" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-loader />
</template>`,
                errors: [
                    {
                        message: '"sw-loader" is deprecated. Please use "mt-loader" instead.',
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
                name: '"sw-tabs" usage is not allowed without autofix because tab metadata needs manual migration',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-tabs />
</template>`,
                output: null,
                errors: [
                    {
                        message: '"sw-tabs" is deprecated. Please use "mt-tabs" instead.',
                    },
                ],
            },
            {
                name: '"sw-tabs" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-tabs />
</template>`,
                errors: [
                    {
                        message: '"sw-tabs" is deprecated. Please use "mt-tabs" instead.',
                    },
                ],
            },
        ]),
    ],
});
