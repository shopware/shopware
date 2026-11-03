import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import mappingModalComponent from './index';

describe('Experience Studio mapping picker selection', () => {
    let wrapper: ReturnType<typeof shallowMount> | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('renders both candidates, marks the current mapping, and emits the clicked candidate', async () => {
        const candidates: ContentSystemMappingCandidate[] = [
            {
                path: 'product.name',
                source: { type: 'root', id: 'product', path: 'name' },
                label: 'Product name',
                description: '',
                group: 'basic',
                valueType: 'string',
                compatibleTypes: ['string'],
                inlineMappable: true,
                canonicalToken: '{{map:product.name}}',
                contextType: 'single',
            },
            {
                path: 'product.description',
                source: { type: 'root', id: 'product', path: 'description' },
                label: 'Product description',
                description: '',
                group: 'basic',
                valueType: 'string',
                compatibleTypes: ['string'],
                inlineMappable: true,
                canonicalToken: '{{map:product.description}}',
                contextType: 'single',
            },
        ];
        wrapper = shallowMount(mappingModalComponent as Component, {
            props: { candidates, currentPath: candidates[0].path },
            global: { stubs: { 'sw-modal': { template: '<div><slot /></div>' } } },
        });
        const buttons = wrapper.findAll('.sw-experience-studio-mapping-modal__candidate');

        expect(buttons).toHaveLength(2);
        expect(buttons[0].text()).toContain('Product name');
        expect(buttons[0].classes()).toContain('is--selected');
        expect(buttons[1].text()).toContain('Product description');
        expect(buttons[1].classes()).not.toContain('is--selected');

        await buttons[1].trigger('click');

        expect(wrapper.emitted('select')).toEqual([[candidates[1]]]);
        await wrapper.setProps({ currentPath: candidates[1].path });
        expect(buttons[0].classes()).not.toContain('is--selected');
        expect(buttons[1].classes()).toContain('is--selected');
    });
});
