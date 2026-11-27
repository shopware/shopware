import { shallowMount } from '@vue/test-utils';

import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import settingsFieldsComponent from './index';

describe('radio panel option descriptions', () => {
    let wrappers: ReturnType<typeof shallowMount>[] = [];

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        wrappers = [];
    });

    it('shows the selected option description and updates it when the value changes', async () => {
        const property: ContentSystemElementTypeProperty = {
            type: 'string',
            contextTypes: [],
            translatable: false,
            enum: null,
            default: null,
            required: false,
            mappable: false,
            defaultMapping: null,
            inlineMappable: false,
            title: 'Mode',
            description: '',
            adminUI: {
                component: 'radio-panel',
                props: {
                    options: [
                        { value: 'auto', label: 'Auto', description: 'Automatic layout' },
                        { value: 'explicit', label: 'Explicit', description: 'Explicit layout' },
                        { value: 'other', label: 'Other' },
                    ],
                },
            },
        };
        const wrapper = shallowMount(settingsFieldsComponent, {
            attachTo: document.body,
            props: { fields: [{ key: 'mode', property }], values: { mode: 'auto' }, allowEdit: true },
            global: {
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'sw-button-group': { template: '<div><slot /></div>' },
                },
            },
        });

        wrappers.push(wrapper);
        const description = wrapper.find('.sw-experience-studio-settings-fields__radio-panel-group p');
        const buttons = wrapper.findAll('button');
        expect(description.attributes('aria-live')).toBe('polite');
        expect(buttons[0].attributes('aria-describedby')).toBe(description.attributes('id'));
        expect(buttons[1].attributes('aria-describedby')).toBeUndefined();
        expect(wrapper.text()).toContain('Automatic layout');
        (buttons[1].element as HTMLButtonElement).focus();
        await buttons[1].trigger('click');
        expect(wrapper.emitted('update-field')?.[0]).toEqual([{ key: 'mode', value: 'explicit' }]);
        await wrapper.setProps({ values: { mode: 'explicit' } });
        expect(wrapper.text()).toContain('Explicit layout');
        expect(document.activeElement).toBe(buttons[1].element);
        expect(buttons[1].attributes('aria-describedby')).toBe(description.attributes('id'));
        expect(buttons[0].attributes('aria-describedby')).toBeUndefined();
        expect(wrapper.text()).not.toContain('Automatic layout');
        await wrapper.setProps({ values: { mode: 'other' } });
        expect(description.isVisible()).toBe(false);
        await wrapper.setProps({ values: { mode: null } });
        expect(description.isVisible()).toBe(false);
        const repeated = shallowMount(settingsFieldsComponent, {
            props: {
                fields: [
                    { key: 'mode', property },
                    { key: 'other-mode', property },
                ],
                values: {},
                allowEdit: true,
            },
            global: {
                provide: { repositoryFactory: {} },
                stubs: {
                    'sw-collapse': { template: '<div><slot name="content" /></div>' },
                    'sw-button-group': { template: '<div><slot /></div>' },
                },
            },
        });
        wrappers.push(repeated);
        const ids = [
            description.attributes('id'),
            ...repeated.findAll('p[aria-live]').map((region) => region.attributes('id')),
        ];
        expect(new Set(ids).size).toBe(3);
        const buttonIds = [...buttons, ...repeated.findAll('button')].map((button) => button.attributes('id'));
        expect(new Set(buttonIds).size).toBe(buttonIds.length);
    });
});
