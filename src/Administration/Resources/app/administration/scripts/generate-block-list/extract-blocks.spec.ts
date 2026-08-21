import fs from 'fs';
import { extractBlocks } from './extract-blocks';

jest.mock('fs');

/**
 * Feeds `extractBlocks` file contents by path and returns the block names it found.
 */
function extractFrom(files: Record<string, string>): string[] {
    jest.spyOn(fs, 'readFileSync').mockImplementation((filePath) => files[filePath as string]);

    return extractBlocks(Object.keys(files));
}

describe('extract-blocks', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('collects twig blocks', () => {
        expect(
            extractFrom({
                'a.html.twig': `
                    {% block sw_outer %}
                        {% block sw_inner %}{% endblock %}
                    {% endblock %}
                `,
            }),
        ).toEqual([
            'sw_outer',
            'sw_inner',
        ]);
    });

    it('collects sw-block declarations from a vue template', () => {
        expect(
            extractFrom({
                'a.vue': `
                    <template>
                        <sw-block name="sw_outer" :data="$dataScope">
                            <sw-block name="sw_inner" :data="$dataScope">Content</sw-block>
                        </sw-block>
                    </template>
                `,
            }),
        ).toEqual([
            'sw_outer',
            'sw_inner',
        ]);
    });

    it('collects the block an override extends', () => {
        expect(
            extractFrom({
                'a.override.vue': `
                    <template>
                        <sw-block extends="sw_outer">Replacement</sw-block>
                    </template>
                `,
            }),
        ).toEqual(['sw_outer']);
    });

    it('reads a block name spread over multiple lines', () => {
        expect(
            extractFrom({
                'a.vue': `
                    <template>
                        <sw-block
                            name="sw_outer"
                            :data="$dataScope"
                        >
                            Content
                        </sw-block>
                    </template>
                `,
            }),
        ).toEqual(['sw_outer']);
    });

    it('collects both dialects from one scan', () => {
        expect(
            extractFrom({
                'a.html.twig': '{% block sw_legacy %}{% endblock %}',
                'b.vue': '<template><sw-block name="sw_native">Content</sw-block></template>',
            }),
        ).toEqual([
            'sw_legacy',
            'sw_native',
        ]);
    });

    it.each([
        [
            'sw-block-field, whose bound name is not a block name',
            `
                <sw-block-field
                    v-model:value="value"
                    :name="formFieldName"
                >
                    Content
                </sw-block-field>
            `,
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
        expect(extractFrom({ 'a.vue': `<template>${template}</template>` })).toEqual([]);
    });
});
