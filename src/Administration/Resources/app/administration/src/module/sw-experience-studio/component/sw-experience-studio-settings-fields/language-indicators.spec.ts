import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import { MtBadge } from '@shopware-ag/meteor-component-library';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import elementSettingsComponent from '../sw-experience-studio-element-settings';
import settingsFieldsComponent from './index';

function property(
    title: string,
    overrides: Partial<ContentSystemElementTypeProperty> = {},
): ContentSystemElementTypeProperty {
    return {
        title,
        type: 'string',
        contextTypes: ['single'],
        translatable: true,
        enum: null,
        default: null,
        required: false,
        mappable: false,
        inlineMappable: false,
        defaultMapping: null,
        description: '',
        adminUI: null,
        ...overrides,
    };
}

async function fieldStubs() {
    return {
        'sw-collapse': { template: '<div><slot name="content" /></div>' },
        'mt-tabs': true,
        'mt-badge': MtBadge as Component,
        'mt-text-editor': {
            props: ['label', 'modelValue'],
            template:
                '<div class="mt-text-editor"><label>{{ label }}</label><div class="editor-content">{{ modelValue }}</div></div>',
        },
        'sw-experience-studio-inline-text-field': {
            props: ['label'],
            template: '<div class="mt-text-editor"><label>{{ label }}</label></div>',
        },
        'sw-experience-studio-settings-fields': await wrapTestComponent('sw-experience-studio-settings-fields', {
            sync: true,
        }),
    };
}

async function createWrapper(languageName = 'English') {
    return mount(elementSettingsComponent as Component, {
        props: {
            selectedElementId: 'element',
            selectedElement: {
                id: 'element',
                component: 'Sw:Content:Text',
                properties: {
                    title: { english: 'Hello', german: 'Hallo' },
                    shared: 'Shared value',
                    enabled: { english: true, german: false },
                    body: { english: '<p>Fallback text</p>' },
                },
            },
            selectedElementType: {
                name: 'Sw:Content:Text',
                properties: {
                    title: property('Title'),
                    shared: property('Shared', { translatable: false }),
                    enabled: property('Enabled', { type: 'boolean' }),
                    body: property('Body', { adminUI: { component: 'text-editor' } }),
                },
            },
            languageName,
            languageId: 'english',
        },
        global: {
            provide: { repositoryFactory: {} },
            stubs: await fieldStubs(),
        },
    });
}

describe('language indicators on translatable settings fields', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('shows a globe and the frame language beside translatable labels, including read-only controls', async () => {
        wrapper = await createWrapper();

        expect(
            wrapper
                .findAll('.sw-experience-studio-settings-fields__language-label')
                .map((row) => row.get('span[aria-hidden="true"]').text()),
        ).toEqual(['Title', 'Enabled', 'Body']);
        expect(
            wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['English', 'English', 'English']);
        expect(wrapper.findAll('[data-testid="mt-icon__regular-globe"]')).toHaveLength(3);
        const title = wrapper.get('input[aria-label="Title"]');
        expect(title.attributes('disabled')).toBeDefined();
        expect(wrapper.find(`label[for="${title.attributes('id')}"]`).exists()).toBe(true);
        expect(wrapper.get<HTMLInputElement>('input[aria-label="Shared"]').element.value).toBe('Shared value');
    });

    it('updates the editing language and values without inheriting another language', async () => {
        wrapper = await createWrapper();
        expect(wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('Hello');
        expect(wrapper.get('.editor-content').text()).toBe('<p>Fallback text</p>');
        await wrapper.setProps({ languageName: 'German', languageId: 'german' });

        expect(
            wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['German', 'German', 'German']);
        expect(wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('Hallo');
        expect(wrapper.get('.editor-content').text()).toBe('');
        expect(wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('keeps the normal labels until the language name becomes available', async () => {
        wrapper = await createWrapper('');
        expect(wrapper.find('.sw-experience-studio-settings-fields__language-badge').exists()).toBe(false);
        expect(wrapper.find('.has--language-label').exists()).toBe(false);

        await wrapper.setProps({ languageName: 'English' });
        expect(wrapper.findAll('.sw-experience-studio-settings-fields__language-badge')).toHaveLength(3);
    });

    it('includes translated fields rendered as mappings or inline text editors', async () => {
        wrapper = mount(settingsFieldsComponent as Component, {
            props: {
                languageName: 'German',
                fields: [
                    { key: 'mapped', property: property('Mapped title', { mappable: true }) },
                    { key: 'inline', property: property('Text', { inlineMappable: true }) },
                    { key: 'shared', property: property('Shared', { translatable: false }) },
                ],
                values: { inline: 'Hello' },
                mappings: { mapped: 'product.name' },
            },
            global: {
                provide: { repositoryFactory: {} },
                stubs: await fieldStubs(),
            },
        });

        expect(
            wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['German', 'German']);
        expect(wrapper.get('.sw-experience-studio-settings-fields__mapping-name').text()).toBe('product.name');
    });
});
