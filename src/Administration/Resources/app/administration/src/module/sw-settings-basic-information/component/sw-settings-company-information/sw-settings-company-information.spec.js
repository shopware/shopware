import { mount } from '@vue/test-utils';
import component from './index';

/**
 * @sw-package fundamentals@framework
 */
describe('src/module/sw-settings-basic-information/component/sw-settings-company-information', () => {
    async function createWrapper(props = {}) {
        return mount(component, {
            props,
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot /></div>',
                    },
                    'sw-media-compact-upload-v2': {
                        name: 'sw-media-compact-upload-v2',
                        template: '<div class="sw-media-compact-upload-v2" />',
                        props: [
                            'source',
                            'label',
                            'name',
                        ],
                    },
                    'sw-entity-single-select': {
                        name: 'sw-entity-single-select',
                        template: '<div class="sw-entity-single-select" />',
                        props: [
                            'value',
                            'disabled',
                            'label',
                            'placeholder',
                            'entity',
                            'required',
                        ],
                    },
                    'mt-text-field': {
                        name: 'mt-text-field',
                        template:
                            '<input class="mt-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
                        props: [
                            'modelValue',
                            'disabled',
                            'label',
                            'placeholder',
                            'name',
                            'required',
                        ],
                    },
                    'mt-url-field': {
                        name: 'mt-url-field',
                        template:
                            '<input class="mt-url-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
                        props: [
                            'modelValue',
                            'disabled',
                            'label',
                            'placeholder',
                            'name',
                        ],
                    },
                },
                mocks: {
                    $t: (key) => key,
                },
            },
        });
    }

    it('should not emit an update on initial render when using the default value', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should emit the nested company info object when a field changes', async () => {
        const wrapper = await createWrapper({
            value: {
                companyName: '',
            },
        });

        await wrapper.find('.mt-text-field').setValue('ACME');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)[0]).toEqual(
            expect.objectContaining({
                companyName: 'ACME',
            }),
        );
    });

    it('should emit the company logo id when the selection changes', async () => {
        const wrapper = await createWrapper({
            value: {
                logoId: null,
            },
        });

        await wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).vm.$emit('selection-change', [{ id: 'logo-id' }]);

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)[0]).toEqual(
            expect.objectContaining({
                logoId: 'logo-id',
            }),
        );
    });
});
