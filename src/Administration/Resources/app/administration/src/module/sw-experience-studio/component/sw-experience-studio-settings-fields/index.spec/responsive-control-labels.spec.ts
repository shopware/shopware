import { mount, type DOMWrapper } from '@vue/test-utils';
import type { Component } from 'vue';
import { MtSelect, MtTextField } from '@shopware-ag/meteor-component-library';
import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import settingsFields from '../index';

const sliderStub = { name: 'SliderStub', props: ['label'], template: '<div />' };
const boxSpacingStub = { name: 'BoxSpacingStub', props: ['label'], template: '<div />' };

function createWrapper(title: string, property: Omit<ContentSystemElementTypeProperty, 'title'>) {
    return mount(settingsFields as Component, {
        attachTo: document.body,
        props: {
            fields: [{ key: 'subject', breakpointAware: true, property: { ...property, title } }],
            values: {},
            allowEdit: true,
            breakpointFallbacks: { xs: ['sm', 'lg'], sm: ['lg'], md: ['lg'], lg: [], xl: ['lg'], xxl: ['xl', 'lg'] },
        },
        global: {
            provide: { repositoryFactory: {} },
            stubs: {
                'sw-collapse': { template: '<div><slot name="content" /></div>' },
                BreakpointSelect: true,
                'mt-select': MtSelect as Component,
                'mt-slider': sliderStub,
                'mt-text-field': MtTextField as Component,
                'sw-experience-studio-box-spacing-field': boxSpacingStub,
            },
        },
    });
}

function accessibleName(
    wrapper: ReturnType<typeof createWrapper>,
    input: Pick<DOMWrapper<HTMLElement>, 'attributes'>,
): string {
    const ariaLabel = input.attributes('aria-label');
    if (ariaLabel) return ariaLabel;

    return wrapper.find(`label[for="${input.attributes('id')}"]`).text();
}

describe('responsive settings field labels', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it.each([
        [
            'text field',
            'Max width',
            { type: 'string', adminUI: { component: 'text' } },
            'input[type="text"]',
        ],
        [
            'select',
            'Alignment',
            { type: 'string', enum: ['start', 'end'], adminUI: { component: 'select' } },
            'input',
        ],
    ] as const)('names the %s input after its property', (_control, title, property, selector) => {
        wrapper = createWrapper(title, property as Omit<ContentSystemElementTypeProperty, 'title'>);

        expect(
            accessibleName(wrapper, wrapper.get(`.sw-experience-studio-settings-fields__responsive-input ${selector}`)),
        ).toBe(title);
    });

    it.each([
        [
            'slider',
            'Columns',
            { type: 'integer', adminUI: { component: 'responsive-number' } },
            sliderStub,
        ],
        [
            'box spacing field',
            'Padding',
            { type: 'string', adminUI: { component: 'box-spacing' } },
            boxSpacingStub,
        ],
    ] as const)('passes the property title as the %s label', (_control, title, property, stub) => {
        wrapper = createWrapper(title, property as Omit<ContentSystemElementTypeProperty, 'title'>);

        const control = wrapper.getComponent(stub);
        expect(control.props('label')).toBe(title);
        expect(control.classes()).toContain('sw-experience-studio-settings-fields__responsive-input');
    });
});
