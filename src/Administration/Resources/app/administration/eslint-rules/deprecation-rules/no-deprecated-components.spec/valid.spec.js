const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (valid)', rule, {
    valid: [
        {
            name: 'Empty file',
            filename: 'test.html.twig',
            code: '',
        },
        {
            name: '"mt-button" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-button>Hello</mt-button>
            </template>`,
        },
        {
            name: '"mt-colorpicker" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-colorpicker>Hello</mt-colorpicker>
            </template>`,
        },
        {
            name: '"mt-icon" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-icon>Hello</mt-icon>
            </template>`,
        },
        {
            name: '"mt-text-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-text-field />
            </template>`,
        },
        {
            name: '"mt-loader" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-loader />
            </template>`,
        },
        {
            name: '"mt-tabs" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-tabs />
            </template>`,
        },
        {
            name: '"mt-checkbox" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-checkbox />
            </template>`,
        },
        {
            name: '"mt-textarea" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-textarea />
            </template>`,
        },
        {
            name: '"mt-banner" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-banner />
            </template>`,
        },
        {
            name: '"mt-email-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-email-field />
            </template>`,
        },
        {
            name: '"mt-url-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-url-field />
            </template>`,
        },
        {
            name: '"mt-select" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-select />
            </template>`,
        },
        {
            name: '"mt-skeleton-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-skeleton-bar />
            </template>`,
        },
        {
            name: '"mt-switch" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-switch />
            </template>`,
        },
        {
            name: '"mt-number-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-number-field />
            </template>`,
        },
        {
            name: '"mt-password-field" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-password-field />
            </template>`,
        },
        {
            name: '"mt-progress-bar" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-progress-bar />
            </template>`,
        },
        {
            name: '"mt-data-table" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-data-table />
            </template>`,
        },
        {
            name: '"mt-floating-ui" usage is allowed',
            filename: 'test.html.twig',
            code: `
            <template>
                <mt-floating-ui />
            </template>`,
        },
        {
            name: '"sw-button" usage is allowed when not in the activatedComponents list',
            filename: 'test.html.twig',
            options: [
                {
                    fix: true,
                    activatedComponents: ['sw-icon'],
                },
            ],
            code: `
<template>
    <sw-button>Hello</sw-button>
</template>`,
        },
    ],
    invalid: [],
});
