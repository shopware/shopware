import { MtFloatingUi } from "@shopware-ag/meteor-component-library";

const RuleTester = require('eslint').RuleTester
const rule = require('./no-deprecated-components');

const tester = new RuleTester({
    parser: require.resolve('vue-eslint-parser'),
    parserOptions: { ecmaVersion: 2015 }
})

tester.run('no-deprecated-components', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: ''
        },
        {
            name: '"mt-button" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`
        },
        {
            name: '"mt-colorpicker" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-colorpicker>Hello</mt-colorpicker>
            </template>`
        },
        {
            name: '"mt-icon" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-icon>Hello</mt-icon>
            </template>`
        },
        {
            name: '"mt-text-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-text-field />
            </template>`
        },
        {
            name: '"mt-loader" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-loader />
            </template>`
        },
        {
            name: '"mt-tabs" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-tabs />
            </template>`
        },
        {
            name: '"mt-checkbox" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-checkbox />
            </template>`
        },
        {
            name: '"mt-textarea" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-textarea />
            </template>`
        },
        {
            name: '"mt-banner" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-banner />
            </template>`
        },
        {
            name: '"mt-email-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-email-field />
            </template>`
        },
        {
            name: '"mt-url-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-url-field />
            </template>`
        },
        {
            name: '"mt-select" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-select />
            </template>`
        },
        {
            name: '"mt-skeleton-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-skeleton-bar />
            </template>`
        },
        {
            name: '"mt-switch" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-switch />
            </template>`
        },
        {
            name: '"mt-number-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-number-field />
            </template>`
        },
        {
            name: '"mt-password-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-password-field />
            </template>`
        },
        {
            name: '"mt-progress-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-progress-bar />
            </template>`
        },
        {
            name: '"mt-data-table" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-data-table />
            </template>`
        },
        {
            name: '"sw-data-grid" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
    <sw-data-grid />
</template>`,
        },
        {
            name: '"mt-floating-ui" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-floating-ui />
            </template>`
        }
    ],
    invalid: [
        {
            name: '"sw-button" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-button>Hello</sw-button>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-button - please check if everything works correctly -->
    <mt-button>Hello</mt-button>
</template>`,
            errors: [{
                message: '"sw-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
        {
            name: '"sw-button" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-button>Hello</sw-button>
</template>`,
            errors: [{
                message: '"sw-button" is deprecated. Please use "mt-button" instead.',
            }]
        },
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
            errors: [{
                message: '"sw-icon" is deprecated. Please use "mt-icon" instead.',
            }]
        },
        {
            name: '"sw-icon" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-icon name="regular-times-s" />
</template>`,
            errors: [{
                message: '"sw-icon" is deprecated. Please use "mt-icon" instead.',
            }]
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
            errors: [{
                message: '"sw-card" is deprecated. Please use "mt-card" instead.',
            }]
        },
        {
            name: '"sw-card" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-card>Hello</sw-card>
</template>`,
            errors: [{
                message: '"sw-card" is deprecated. Please use "mt-card" instead.',
            }]
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
            errors: [{
                message: '"sw-text-field" is deprecated. Please use "mt-text-field" instead.',
            }]
        },
        {
            name: '"sw-text-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-text-field />
</template>`,
            errors: [{
                message: '"sw-text-field" is deprecated. Please use "mt-text-field" instead.',
            }]
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
            errors: [{
                message: '"sw-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
            }]
        },
        {
            name: '"sw-colorpicker" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-colorpicker />
</template>`,
            errors: [{
                message: '"sw-colorpicker" is deprecated. Please use "mt-colorpicker" instead.',
            }]
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
            errors: [{
                message: '"sw-switch-field" is deprecated. Please use "mt-switch" instead.',
            }]
        },
        {
            name: '"sw-switch-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-switch-field>Hello</sw-switch-field>
</template>`,
            errors: [{
                message: '"sw-switch-field" is deprecated. Please use "mt-switch" instead.',
            }]
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
            errors: [{
                message: '"sw-number-field" is deprecated. Please use "mt-number-field" instead.',
            }]
        },
        {
            name: '"sw-number-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-number-field />
</template>`,
            errors: [{
                message: '"sw-number-field" is deprecated. Please use "mt-number-field" instead.',
            }]
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
            errors: [{
                message: '"sw-loader" is deprecated. Please use "mt-loader" instead.',
            }]
        },
        {
            name: '"sw-loader" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-loader />
</template>`,
            errors: [{
                message: '"sw-loader" is deprecated. Please use "mt-loader" instead.',
            }]
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
            errors: [{
                message: '"sw-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
            }]
        },
        {
            name: '"sw-checkbox-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-checkbox-field />
</template>`,
            errors: [{
                message: '"sw-checkbox-field" is deprecated. Please use "mt-checkbox" instead.',
            }]
        },
        {
            name: '"sw-tabs" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-tabs />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-tabs - please check if everything works correctly -->
    <mt-tabs />
</template>`,
            errors: [{
                message: '"sw-tabs" is deprecated. Please use "mt-tabs" instead.',
            }]
        },
        {
            name: '"sw-tabs" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-tabs />
</template>`,
            errors: [{
                message: '"sw-tabs" is deprecated. Please use "mt-tabs" instead.',
            }]
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
            errors: [{
                message: '"sw-select-field" is deprecated. Please use "mt-select" instead.',
            }]
        },
        {
            name: '"sw-select-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-select-field />
</template>`,
            errors: [{
                message: '"sw-select-field" is deprecated. Please use "mt-select" instead.',
            }]
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
            errors: [{
                message: '"sw-textarea-field" is deprecated. Please use "mt-textarea" instead.',
            }]
        },
        {
            name: '"sw-textarea-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-textarea-field />
</template>`,
            errors: [{
                message: '"sw-textarea-field" is deprecated. Please use "mt-textarea" instead.',
            }]
        },
        {
            name: '"sw-alert" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-alert>Hello</sw-alert>
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-alert - please check if everything works correctly -->
    <mt-banner>Hello</mt-banner>
</template>`,
            errors: [{
                message: '"sw-alert" is deprecated. Please use "mt-banner" instead.',
            }]
        },
        {
            name: '"sw-alert" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-alert>Hello</sw-alert>
</template>`,
            errors: [{
                message: '"sw-alert" is deprecated. Please use "mt-banner" instead.',
            }]
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
            errors: [{
                message: '"sw-email-field" is deprecated. Please use "mt-email-field" instead.',
            }]
        },
        {
            name: '"sw-email-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-email-field />
</template>`,
            errors: [{
                message: '"sw-email-field" is deprecated. Please use "mt-email-field" instead.',
            }]
        },
        {
            name: '"sw-skeleton-bar" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-skeleton-bar />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-skeleton-bar - please check if everything works correctly -->
    <mt-skeleton-bar />
</template>`,
            errors: [{
                message: '"sw-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
            }]
        },
        {
            name: '"sw-skeleton-bar" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-skeleton-bar />
</template>`,
            errors: [{
                message: '"sw-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
            }]
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
            errors: [{
                message: '"sw-password-field" is deprecated. Please use "mt-password-field" instead.',
            }]
        },
        {
            name: '"sw-password-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-password-field />
</template>`,
            errors: [{
                message: '"sw-password-field" is deprecated. Please use "mt-password-field" instead.',
            }]
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
            errors: [{
                message: '"sw-url-field" is deprecated. Please use "mt-url-field" instead.',
            }]
        },
        {
            name: '"sw-url-field" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-url-field />
</template>`,
            errors: [{
                message: '"sw-url-field" is deprecated. Please use "mt-url-field" instead.',
            }]
        },
        {
            name: '"sw-progress-bar" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-progress-bar />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-progress-bar - please check if everything works correctly -->
    <mt-progress-bar />
</template>`,
            errors: [{
                message: '"sw-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
            }]
        },
        {
            name: '"sw-progress-bar" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-progress-bar />
</template>`,
            errors: [{
                message: '"sw-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
            }]
        },
        {
            name: '"sw-data-grid" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-data-grid />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
    <sw-data-grid />
</template>`,
            errors: [{
                message: '"sw-data-grid" is deprecated. Please use "mt-data-table" instead.',
            }]
        },
        {
            name: '"sw-data-grid" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-data-grid />
</template>`,
            errors: [{
                message: '"sw-data-grid" is deprecated. Please use "mt-data-table" instead.',
            }]
        },
        {
            name: '"sw-popover" usage is not allowed',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-popover />
</template>`,
            output: `
<template>
    <!-- TODO Codemod: Converted from sw-popover - please check if everything works correctly -->
    <mt-floating-ui />
</template>`,
            errors: [{
                message: '"sw-popover" is deprecated. Please use "mt-floating-ui" instead.',
            }]
        },
        {
            name: '"sw-popover" usage is not allowed [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: `
<template>
    <sw-popover />
</template>`,
            errors: [{
                message: '"sw-popover" is deprecated. Please use "mt-floating-ui" instead.',
            }]
        }
    ]
})

