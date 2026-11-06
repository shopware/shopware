const { withRegistryMessageContext } = require('../test-utils/with-registry-message-context');
const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (invalid layout and feedback components)', rule, {
    valid: [],
    invalid: [
        ...withRegistryMessageContext([
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
        ]),
    ],
});
