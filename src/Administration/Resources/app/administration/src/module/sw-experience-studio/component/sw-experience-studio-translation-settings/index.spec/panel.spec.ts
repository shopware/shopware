import { mount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { MtBadge } from '@shopware-ag/meteor-component-library';
import component from '../index';
import { translationProperty, translationType } from './fixtures';
import { getLayoutTranslationFields } from '../../../util/layout-translation.util';

async function createWrapper(allowEdit = true) {
    const type = translationType({
        title: translationProperty(),
        enabled: translationProperty({ title: 'Enabled', type: 'boolean' }),
        count: translationProperty({ title: 'Count', type: 'integer' }),
        shared: translationProperty({ translatable: false }),
    });
    const elements: ContentElementNode[] = [
        {
            id: 'element',
            component: type.name,
            properties: { title: { english: 'Hello' }, enabled: { german: false }, count: { german: 0 }, shared: 'Shared' },
        },
    ];
    const fields = getLayoutTranslationFields(elements, () => type);
    const wrapper = mount(component as Component, {
        props: { fields, languageChain: ['german', 'english'], languageName: 'German', allowEdit },
        global: {
            provide: { repositoryFactory: {} },
            stubs: {
                'sw-experience-studio-settings-fields': await wrapTestComponent('sw-experience-studio-settings-fields', {
                    sync: true,
                }),
                'sw-collapse': { template: '<div><slot name="content" /></div>' },
                'sw-button-group': { template: '<div><slot /></div>' },
                'sw-experience-studio-box-spacing-field': await wrapTestComponent('sw-experience-studio-box-spacing-field', {
                    sync: true,
                }),
                'mt-badge': MtBadge as Component,
            },
        },
    });
    await flushPromises();
    return { wrapper, fields, type };
}

describe('layout translation settings', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => view?.wrapper.unmount());

    it('shows only translatable controls with frame language fallback, including false and zero values', async () => {
        view = await createWrapper();
        expect(view.wrapper.findAll('li')).toHaveLength(3);
        expect(
            view.wrapper.findAll('.sw-experience-studio-translation-settings__context').map((context) => context.text()),
        ).toEqual(['Text', 'Text', 'Text']);
        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('Hello');
        expect(view.wrapper.get<HTMLInputElement>('input[type="checkbox"]').element.checked).toBe(false);
        expect(view.wrapper.get<HTMLInputElement>('.mt-number-field input').element.value).toBe('0');
        expect(
            view.wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['German', 'German', 'German']);
        expect(view.wrapper.find('input[aria-label="Shared"]').exists()).toBe(false);

        await view.wrapper.setProps({ languageName: 'English', languageChain: ['english'] });
        expect(
            view.wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['English', 'English', 'English']);
    });

    it('routes each edit to its original element and property', async () => {
        view = await createWrapper();
        await view.wrapper.get('input[aria-label="Title"]').setValue('Hallo');
        expect(view.wrapper.emitted('update-properties')).toEqual([
            [{ elementId: 'element', propertyKey: 'title', value: 'Hallo' }],
        ]);
    });

    it('reports the component root when a field receives focus, including repeated and keyboard focus', async () => {
        view = await createWrapper();
        await view.wrapper.setProps({
            fields: [
                view.fields[0],
                { ...view.fields[0], element: { ...view.fields[0].element, id: 'nested' } },
            ],
        });
        const inputs = view.wrapper.findAll('input[aria-label="Title"]');
        await inputs[0].trigger('focusin');
        await inputs[1].trigger('focusin');
        await inputs[1].trigger('focusout');
        await inputs[0].trigger('focusin');

        expect(view.wrapper.emitted('focus-element')).toEqual([['element'], ['nested'], ['element']]);
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('prevents editing for viewers', async () => {
        view = await createWrapper(false);
        expect(view.wrapper.findAll('input').every((input) => input.element.disabled)).toBe(true);
        view.wrapper
            .getComponent<ComponentPublicInstance>('.sw-experience-studio-settings-fields')
            .vm.$emit('update-field', { key: 'title', value: 'Blocked' });
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('keeps mapped fields read-only and hides actions that would change layout bindings', async () => {
        view = await createWrapper();
        await view.wrapper.setProps({ fields: [{ ...view.fields[0], mappingPath: 'product.name' }] });
        expect(view.wrapper.get('.sw-experience-studio-settings-fields__mapping-name').text()).toBe('product.name');
        expect(
            view.wrapper.get('.sw-experience-studio-settings-fields__mapping-value').attributes('disabled'),
        ).toBeDefined();
        expect(view.wrapper.find('.sw-experience-studio-settings-fields__mapping-remove').exists()).toBe(false);
        view.wrapper
            .getComponent<ComponentPublicInstance>('.sw-experience-studio-settings-fields')
            .vm.$emit('update-field', { key: 'title', value: 'Blocked' });
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('rejects updates to properties outside the entry being edited', async () => {
        view = await createWrapper();
        view.wrapper
            .getComponent<ComponentPublicInstance>('.sw-experience-studio-settings-fields')
            .vm.$emit('update-field', { key: 'shared', value: 'Blocked' });
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it.each(['radio-panel', 'box-spacing'])(
        'keeps repeated %s controls and their labels distinct between elements',
        async (control) => {
            view = await createWrapper();
            const radio = control === 'radio-panel';
            const type = translationType({
                title: translationProperty({
                    enum: radio ? ['left', 'right'] : null,
                    adminUI: {
                        component: control,
                        props: radio
                            ? {
                                  options: [
                                      { value: 'left', label: 'Left', icon: 'regular-align-left' },
                                      { value: 'right', label: 'Right', icon: 'regular-align-right' },
                                  ],
                              }
                            : {},
                    },
                }),
            });
            const fields = getLayoutTranslationFields(
                ['first', 'second'].map((id) => ({
                    id,
                    component: type.name,
                    properties: { title: { english: radio ? 'left' : '8px' } },
                })),
                () => type,
            );
            await view.wrapper.setProps({ fields, languageChain: ['english'] });

            const controls = view.wrapper.findAll(
                radio ? 'button[id^="sw-experience-studio-radio-panel-"]' : 'input[id^="sw-experience-studio-box-spacing-"]',
            );
            const ids = controls.map((item) => item.attributes('id'));
            expect(ids).toHaveLength(radio ? 4 : 8);
            expect(new Set(ids).size).toBe(ids.length);
            expect(
                view.wrapper
                    .findAll('label[for^="sw-experience-studio-radio-panel-"]')
                    .map((label) => label.attributes('for')),
            ).toEqual(radio ? [ids[0], ids[2]] : []);
            if (radio) {
                await controls[1].trigger('click');
            }
            expect(view.wrapper.emitted('update-properties')).toEqual(
                radio ? [[{ elementId: 'first', propertyKey: 'title', value: 'right' }]] : undefined,
            );
        },
    );

    it.each([
        [{ fields: [] }, 'translationSettings.emptyState'],
        [{ isLoadingTypes: true }, 'elementSettings.loading'],
        [{ typeLoadError: 'Failed' }, 'elementSettings.typeLoadError'],
    ])('shows the appropriate state instead of fields for %j', async (props, snippet) => {
        view = await createWrapper();
        await view.wrapper.setProps(props);
        expect(view.wrapper.find('li').exists()).toBe(false);
        expect(view.wrapper.text()).toContain(`sw-experience-studio.detail.${snippet}`);
    });
});
