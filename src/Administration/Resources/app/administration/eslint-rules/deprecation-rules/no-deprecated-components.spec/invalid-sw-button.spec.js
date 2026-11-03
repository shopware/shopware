const { withRegistryMessageContext } = require('../test-utils/with-registry-message-context');
const { rule, tester } = require('./tester');

tester.run('no-deprecated-components (invalid sw-button)', rule, {
    valid: [],
    invalid: [
        ...withRegistryMessageContext([
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
                errors: [
                    {
                        message: '"sw-button" is deprecated. Please use "mt-button" instead.',
                    },
                ],
            },
            {
                name: '"sw-button" usage with object v-bind leaves dynamic prop warning',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-button v-bind="buttonProps">Hello</sw-button>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-button - please check if everything works correctly. Dynamic v-bind props may still contain deprecated API usage and need manual review. -->
    <mt-button v-bind="buttonProps">Hello</mt-button>
</template>`,
                errors: [
                    {
                        message: '"sw-button" is deprecated. Please use "mt-button" instead.',
                    },
                ],
            },
            {
                name: '"sw-button" usage with object v-on leaves dynamic listener warning',
                filename: 'test.html.twig',
                code: `
<template>
    <sw-button v-on="buttonListeners">Hello</sw-button>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-button - please check if everything works correctly. Dynamic v-on listeners may still contain deprecated event usage and need manual review. -->
    <mt-button v-on="buttonListeners">Hello</mt-button>
</template>`,
                errors: [
                    {
                        message: '"sw-button" is deprecated. Please use "mt-button" instead.',
                    },
                ],
            },
            {
                name: '"sw-button" usage is not allowed when in the activatedComponents list',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: true,
                        activatedComponents: ['sw-button'],
                    },
                ],
                code: `
<template>
    <sw-button>Hello</sw-button>
</template>`,
                output: `
<template>
    <!-- TODO Codemod: Converted from sw-button - please check if everything works correctly -->
    <mt-button>Hello</mt-button>
</template>`,
                errors: [
                    {
                        message: '"sw-button" is deprecated. Please use "mt-button" instead.',
                    },
                ],
            },
            {
                name: '"sw-button" usage is not allowed [disableFix]',
                filename: 'test.html.twig',
                options: [
                    {
                        fix: false,
                    },
                ],
                code: `
<template>
    <sw-button>Hello</sw-button>
</template>`,
                errors: [
                    {
                        message: '"sw-button" is deprecated. Please use "mt-button" instead.',
                    },
                ],
            },
        ]),
    ],
});
