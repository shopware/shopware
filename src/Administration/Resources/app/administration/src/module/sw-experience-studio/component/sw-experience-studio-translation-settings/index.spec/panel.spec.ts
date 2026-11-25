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
        attachTo: document.body,
        props: { fields, languageId: 'german', languageName: 'German', allowEdit },
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
                'mt-text-editor': {
                    props: ['label', 'modelValue', 'disabled'],
                    template:
                        '<textarea :aria-label="label" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
            },
        },
    });
    await flushPromises();
    return { wrapper, fields, type };
}

describe('layout translation settings', () => {
    let view: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => view?.wrapper.unmount());

    it('shows only the frame language values in translatable controls, preserving false and zero', async () => {
        view = await createWrapper();
        expect(view.wrapper.findAll('li')).toHaveLength(1);
        expect(view.wrapper.findAllComponents('.sw-experience-studio-settings-fields')).toHaveLength(1);
        expect(
            view.wrapper.findAll('.sw-experience-studio-translation-settings__context').map((context) => context.text()),
        ).toEqual(['Text']);
        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('');
        expect(view.wrapper.get<HTMLInputElement>('input[type="checkbox"]').element.checked).toBe(false);
        expect(view.wrapper.get<HTMLInputElement>('.mt-number-field input').element.value).toBe('0');
        expect(
            view.wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['German', 'German', 'German']);
        expect(view.wrapper.find('input[aria-label="Shared"]').exists()).toBe(false);

        await view.wrapper.setProps({ languageName: 'English', languageId: 'english' });
        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('Hello');
        expect(view.wrapper.get<HTMLInputElement>('.mt-number-field input').element.value).toBe('');
        expect(
            view.wrapper.findAll('.sw-experience-studio-settings-fields__language-badge').map((badge) => badge.text()),
        ).toEqual(['English', 'English', 'English']);
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('leaves missing translations blank without substituting field defaults or changing stored entries', async () => {
        view = await createWrapper();
        const type = translationType({
            title: translationProperty({ default: 'Default title' }),
            text: translationProperty({
                title: 'Body',
                default: '<p>Default body</p>',
                adminUI: { component: 'text-editor' },
            }),
            enabled: translationProperty({ title: 'Enabled', type: 'boolean', default: true }),
            count: translationProperty({ title: 'Count', type: 'integer', default: 9 }),
        });
        const element: ContentElementNode = {
            id: 'untranslated',
            component: type.name,
            properties: {
                title: { english: 'Hello' },
                text: { english: '<p>Body</p>' },
                enabled: { english: true },
                count: { english: 7 },
            },
        };
        const original = JSON.stringify(element);
        await view.wrapper.setProps({ fields: getLayoutTranslationFields([element], () => type) });
        await flushPromises();

        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('');
        expect(view.wrapper.get<HTMLTextAreaElement>('textarea').element.value).toBe('');
        expect(view.wrapper.get<HTMLInputElement>('input[type="checkbox"]').element.checked).toBe(false);
        expect(view.wrapper.get<HTMLInputElement>('.mt-number-field input').element.value).toBe('');
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();

        await view.wrapper.setProps({ languageId: 'english' });
        await flushPromises();
        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('Hello');
        expect(view.wrapper.get<HTMLTextAreaElement>('textarea').element.value).toBe('<p>Body</p>');

        await view.wrapper.setProps({ languageId: 'german' });
        await flushPromises();
        expect(view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]').element.value).toBe('');
        expect(view.wrapper.get<HTMLTextAreaElement>('textarea').element.value).toBe('');
        expect(JSON.stringify(element)).toBe(original);
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();
    });

    it('keeps an explicitly empty translation blank and emits an empty string when a translation is cleared', async () => {
        view = await createWrapper();
        const element = {
            ...view.fields[0].element,
            properties: { title: { english: 'Hello', german: '' } },
        };
        await view.wrapper.setProps({ fields: getLayoutTranslationFields([element], () => view.type) });

        const input = view.wrapper.get<HTMLInputElement>('input[aria-label="Title"]');
        expect(input.element.value).toBe('');
        expect(view.wrapper.emitted('update-properties')).toBeUndefined();

        await input.setValue('Hallo');
        await input.setValue('');

        expect(view.wrapper.emitted('update-properties')).toEqual([
            [{ elementId: 'element', propertyKey: 'title', value: 'Hallo' }],
            [{ elementId: 'element', propertyKey: 'title', value: '' }],
        ]);
    });

    it('reveals large layouts in batches without remounting existing rich-text editors', async () => {
        view = await createWrapper();
        const type = translationType({
            text: translationProperty({ title: 'Body', adminUI: { component: 'text-editor' } }),
            altText: translationProperty({ title: 'Alternate body', adminUI: { component: 'text-editor' } }),
            caption: translationProperty({ title: 'Caption', adminUI: { component: 'text-editor' } }),
        });
        const fields = getLayoutTranslationFields(
            Array.from({ length: 15 }, (_, index) => ({
                id: `element-${index}`,
                component: type.name,
                properties: {
                    text: { english: `Body ${index}` },
                    altText: { english: `Alternate body ${index}` },
                    caption: { english: `Caption ${index}` },
                },
            })),
            () => type,
        );
        await view.wrapper.setProps({ fields, languageId: 'english' });

        expect(view.wrapper.findAll('textarea')).toHaveLength(20);
        const settings = view.wrapper.findAllComponents('.sw-experience-studio-settings-fields');
        expect(settings).toHaveLength(7);
        const splitElementSettings = settings[6].element;
        expect(settings[6].findAll('textarea')).toHaveLength(2);
        const firstEditor = view.wrapper.get<HTMLTextAreaElement>('textarea');
        await firstEditor.setValue('Updated body');
        expect(view.wrapper.emitted('update-properties')).toEqual([
            [{ elementId: 'element-0', propertyKey: 'text', value: 'Updated body' }],
        ]);

        await view.wrapper.get('.sw-experience-studio-translation-settings__show-more').trigger('click');

        expect(view.wrapper.findAll('textarea')).toHaveLength(40);
        const expandedSettings = view.wrapper.findAllComponents('.sw-experience-studio-settings-fields');
        expect(expandedSettings).toHaveLength(14);
        expect(expandedSettings[6].element).toBe(splitElementSettings);
        expect(expandedSettings[6].findAll('textarea')).toHaveLength(3);
        expect(view.wrapper.get('textarea').element).toBe(firstEditor.element);
        expect(document.activeElement).toBe(view.wrapper.findAll('textarea')[20].element);

        await view.wrapper.get('.sw-experience-studio-translation-settings__show-more').trigger('click');

        const editors = view.wrapper.findAll('textarea');
        expect(editors).toHaveLength(45);
        expect(view.wrapper.findAllComponents('.sw-experience-studio-settings-fields')).toHaveLength(15);
        expect(view.wrapper.find('.sw-experience-studio-translation-settings__show-more').exists()).toBe(false);
        expect(document.activeElement).toBe(editors[40].element);
        await editors[44].setValue('Last updated body');
        expect(view.wrapper.emitted('update-properties')?.[1]).toEqual([
            { elementId: 'element-14', propertyKey: 'caption', value: 'Last updated body' },
        ]);
    });

    it('routes fields with the same keys to their own element and property', async () => {
        view = await createWrapper();
        const secondElement: ContentElementNode = {
            id: 'second',
            component: view.type.name,
            properties: { title: { english: 'Second' }, enabled: { german: false }, count: { german: 1 } },
        };
        await view.wrapper.setProps({
            fields: [...view.fields, ...view.fields.map((entry) => ({ ...entry, element: secondElement }))],
        });
        expect(view.wrapper.findAllComponents('.sw-experience-studio-settings-fields')).toHaveLength(2);
        const titles = view.wrapper.findAll<HTMLInputElement>('input[aria-label="Title"]');
        expect(titles.map((input) => input.element.value)).toEqual(['', '']);

        await titles[1].setValue('Zweiter');
        await view.wrapper.findAll('.mt-number-field input')[1].setValue('4');
        await titles[0].setValue('Hallo');

        expect(view.wrapper.emitted('update-properties')).toEqual([
            [{ elementId: 'second', propertyKey: 'title', value: 'Zweiter' }],
            [{ elementId: 'second', propertyKey: 'count', value: 4 }],
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
        await view.wrapper.setProps({
            fields: view.fields.map((entry) =>
                entry.field.key === 'title' ? { ...entry, mappingPath: 'product.name' } : entry,
            ),
        });
        expect(view.wrapper.findAllComponents('.sw-experience-studio-settings-fields')).toHaveLength(1);
        expect(view.wrapper.get('.sw-experience-studio-settings-fields__mapping-name').text()).toBe('product.name');
        expect(
            view.wrapper.get('.sw-experience-studio-settings-fields__mapping-value').attributes('disabled'),
        ).toBeDefined();
        expect(view.wrapper.find('.sw-experience-studio-settings-fields__mapping-remove').exists()).toBe(false);
        await view.wrapper.get('input[type="checkbox"]').setValue(true);
        view.wrapper
            .getComponent<ComponentPublicInstance>('.sw-experience-studio-settings-fields')
            .vm.$emit('update-field', { key: 'title', value: 'Blocked' });
        expect(view.wrapper.emitted('update-properties')).toEqual([
            [{ elementId: 'element', propertyKey: 'enabled', value: true }],
        ]);
    });

    it('keeps validation attached to the correct element and property within a group', async () => {
        view = await createWrapper();
        const secondElement: ContentElementNode = { ...view.fields[0].element, id: 'second' };
        await view.wrapper.setProps({
            fields: [...view.fields, ...view.fields.map((entry) => ({ ...entry, element: secondElement }))],
            violations: [
                {
                    code: 'unfilled_required_input',
                    scope: 'binding',
                    severity: 'error',
                    elementId: 'second',
                    key: 'count',
                    message: 'Count input is unavailable.',
                    candidates: [],
                },
            ],
        });

        const settings = view.wrapper.findAllComponents('.sw-experience-studio-settings-fields');
        expect(settings).toHaveLength(2);
        expect(settings[0].find('[role="alert"]').exists()).toBe(false);
        expect(settings[1].get('[role="alert"]').text()).toBe('Count input is unavailable.');
        expect(settings[1].findAll('.is--error')).toHaveLength(1);
        expect(settings[1].find('.is--error .mt-number-field input').exists()).toBe(true);
    });

    it('rejects updates to non-translatable properties', async () => {
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
            await view.wrapper.setProps({ fields, languageId: 'english' });

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
