/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { createTodoRule } = require('./create-todo-rule');

const rule = createTodoRule({
    id: 'sample',
    components: { 'sw-old-card': 'sw-old-card is removed.' },
});

const todo = (text) => `// TODO Codemod(6.8, sample): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('create-todo-rule (script)', rule, {
    valid: [
        { name: 'ignores other components', code: "Component.override('sw-card', {});" },
        { name: 'ignores component names from the object prototype', code: "Component.override('constructor', {});" },
    ],
    invalid: [
        {
            name: 'marks overrides and extensions of the component',
            code: "Component.override('sw-old-card', {});\nShopware.Component.extend('acme-card', 'sw-old-card', {});",
            output: [
                todo('sw-old-card is removed.'),
                "Component.override('sw-old-card', {});",
                todo('sw-old-card is removed.'),
                "Shopware.Component.extend('acme-card', 'sw-old-card', {});",
            ].join('\n'),
            errors: [{ message: 'sw-old-card is removed.' }, { message: 'sw-old-card is removed.' }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('create-todo-rule (template)', rule, {
    valid: [
        {
            name: 'ignores other tags',
            filename: '/plugin/src/acme/acme.html.twig',
            code: '<template><sw-card /></template>',
        },
    ],
    invalid: [
        {
            name: 'marks the tags of the component',
            filename: '/plugin/src/acme/acme.html.twig',
            code: ['<template>', '    <sw-old-card />', '    <SwOldCard />', '</template>'].join('\n'),
            output: [
                '<template>',
                '    {# TODO Codemod(6.8, sample): sw-old-card is removed. #}',
                '    <sw-old-card />',
                '    {# TODO Codemod(6.8, sample): sw-old-card is removed. #}',
                '    <SwOldCard />',
                '</template>',
            ].join('\n'),
            errors: [{ message: 'sw-old-card is removed.' }, { message: 'sw-old-card is removed.' }],
        },
    ],
});
