import { extractBlocks } from './extract-blocks';

describe('extract-blocks', () => {
    it.each([
        [
            'nested twig blocks',
            '{% block sw_outer %}{% block sw_inner %}{% endblock %}{% endblock %}',
            [
                'sw_outer',
                'sw_inner',
            ],
        ],
        [
            'nested sw-block declarations in a vue template',
            `<template>
                <sw-block name="sw_outer" :data="$dataScope">
                    <sw-block name="sw_inner" :data="$dataScope">Content</sw-block>
                </sw-block>
            </template>`,
            [
                'sw_outer',
                'sw_inner',
            ],
        ],
        [
            'the block an override extends',
            '<template><sw-block extends="sw_outer">Replacement</sw-block></template>',
            ['sw_outer'],
        ],
        [
            'a block name spread over multiple lines',
            `<template>
                <sw-block
                    name="sw_outer"
                    :data="$dataScope"
                >Content</sw-block>
            </template>`,
            ['sw_outer'],
        ],
        [
            'both dialects, twig first',
            '{% block sw_legacy %}{% endblock %}<sw-block name="sw_native">Content</sw-block>',
            [
                'sw_legacy',
                'sw_native',
            ],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractBlocks(code)).toEqual(expected);
    });

    it.each([
        [
            'sw-block-field, whose bound name is not a block name',
            '<sw-block-field v-model:value="value"\n :name="formFieldName"\n>Content</sw-block-field>',
        ],
        [
            'sw-block-parent',
            '<sw-block-parent />',
        ],
        [
            'a bound name on sw-block',
            '<sw-block :name="blockName">Content</sw-block>',
        ],
        [
            'a v-bind name on sw-block',
            '<sw-block v-bind:name="blockName">Content</sw-block>',
        ],
        [
            'a closing tag',
            '</sw-block>',
        ],
    ])('ignores %s', (_case, template) => {
        expect(extractBlocks(`<template>${template}</template>`)).toEqual([]);
    });
});
