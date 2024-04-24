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
    ]
})
