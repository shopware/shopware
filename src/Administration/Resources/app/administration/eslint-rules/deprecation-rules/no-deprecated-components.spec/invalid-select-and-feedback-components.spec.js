const { withRegistryMessageContext } = require('../registry/with-registry-message-context');
const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (invalid select and feedback components)', rule, {
    valid: [],
    invalid: [
        ...withRegistryMessageContext([
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
                errors: [
                    {
                        message: '"sw-alert" is deprecated. Please use "mt-banner" instead.',
                    },
                ],
            },
            {
                name: '"sw-alert" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-alert>Hello</sw-alert>
</template>`,
                errors: [
                    {
                        message: '"sw-alert" is deprecated. Please use "mt-banner" instead.',
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
                errors: [
                    {
                        message: '"sw-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
                    },
                ],
            },
            {
                name: '"sw-skeleton-bar" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-skeleton-bar />
</template>`,
                errors: [
                    {
                        message: '"sw-skeleton-bar" is deprecated. Please use "mt-skeleton-bar" instead.',
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
                errors: [
                    {
                        message: '"sw-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
                    },
                ],
            },
            {
                name: '"sw-progress-bar" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-progress-bar />
</template>`,
                errors: [
                    {
                        message: '"sw-progress-bar" is deprecated. Please use "mt-progress-bar" instead.',
                    },
                ],
            },
            {
                name: '"sw-data-grid" usage is not allowed',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-data-grid />
</template>`,
                output: null,
                errors: [
                    {
                        message: '"sw-data-grid" is deprecated. Please use "mt-data-table" instead.',
                    },
                ],
            },
            {
                name: '"sw-data-grid" usage on first line is not allowed',
                filename: 'test.html.twig',
                code: '<template><sw-data-grid /></template>',
                output: null,
                errors: [
                    {
                        message: '"sw-data-grid" is deprecated. Please use "mt-data-table" instead.',
                    },
                ],
            },
            {
                name: '"sw-data-grid" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-data-grid />
</template>`,
                errors: [
                    {
                        message: '"sw-data-grid" is deprecated. Please use "mt-data-table" instead.',
                    },
                ],
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
                errors: [
                    {
                        message: '"sw-popover" is deprecated. Please use "mt-floating-ui" instead.',
                    },
                ],
            },
            {
                name: '"sw-popover" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-popover />
</template>`,
                errors: [
                    {
                        message: '"sw-popover" is deprecated. Please use "mt-floating-ui" instead.',
                    },
                ],
            },
        ]),
        {
            name: '"sw-data-grid" reports its manual migration hint without a fix',
            filename: 'test.html.twig',
            code: '<template><sw-data-grid /></template>',
            output: null,
            errors: [
                {
                    message:
                        /^"sw-data-grid" is deprecated\. Please use "mt-data-table" instead\.\nRebuild the table configuration for mt-data-table/,
                },
            ],
        },
    ],
});
