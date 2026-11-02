/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-sw-loader-usage');

const ruleTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2021,
    },
});

const TODO = '<!-- TODO Codemod: Converted from sw-loader - please check if everything works correctly -->';
const MIGRATE_MESSAGE = '"sw-loader" is deprecated and will be removed in 6.8. Please use "mt-loader" instead.';

ruleTester.run('no-sw-loader-usage', rule, {
    valid: [
        {
            name: 'allows empty files',
            filename: 'test.html.twig',
            code: '',
        },
        {
            name: 'allows mt-loader',
            filename: 'test.html.twig',
            code: `
<template>
    <mt-loader />
</template>`,
        },
    ],
    invalid: [
        {
            name: 'converts a loader without attributes',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-loader />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-loader :backdrop="false" />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'adds the backdrop on the tag line when the attributes are there',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-loader v-if="isLoading" size="20px" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-loader v-if="isLoading" size="20px" :backdrop="false" />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'adds the backdrop on its own line when the attributes are on their own lines',
            filename: 'test.html.twig',
            code: `
<template>
    <div>
        <sw-loader
            v-if="isLoading"
            class="sw-select__select-indicator"
            size="16px"
        />
    </div>
</template>`,
            output: `
<template>
    <div>
        ${TODO}
        <mt-loader
            v-if="isLoading"
            class="sw-select__select-indicator"
            size="16px"
            :backdrop="false"
        />
    </div>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'indents the backdrop like the line of the last attribute',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-loader v-if="isLoading"
               size="16px" />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-loader v-if="isLoading"
               size="16px"
               :backdrop="false" />
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'renames the end tag',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-loader v-if="isLoading"></sw-loader>
</template>`,
            output: `
<template>
    ${TODO}
    <mt-loader v-if="isLoading" :backdrop="false"></mt-loader>
</template>`,
            errors: [{ message: MIGRATE_MESSAGE }],
        },
        {
            name: 'keeps a backdrop that is already set',
            filename: 'test.html.twig',
            code: `
<template>
    <sw-loader :backdrop="hasBackdrop" />
    <sw-loader v-bind:backdrop="hasBackdrop" />
    <sw-loader backdrop />
</template>`,
            output: `
<template>
    ${TODO}
    <mt-loader :backdrop="hasBackdrop" />
    ${TODO}
    <mt-loader v-bind:backdrop="hasBackdrop" />
    ${TODO}
    <mt-loader backdrop />
</template>`,
            errors: [
                { message: MIGRATE_MESSAGE },
                { message: MIGRATE_MESSAGE },
                { message: MIGRATE_MESSAGE },
            ],
        },
    ],
});
