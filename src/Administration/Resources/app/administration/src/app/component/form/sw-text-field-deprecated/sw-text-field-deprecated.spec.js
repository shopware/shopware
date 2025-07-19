/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import { ref } from 'vue';

async function createWrapper({ provide, ...options } = {}) {
    const wrapper = mount(await wrapTestComponent('sw-text-field-deprecated', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-field-error': true,
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'mt-text-field': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
            },
            provide: {
                validationService: {},
                ...provide,
            },
        },
        ...options,
    });

    await flushPromises();

    return wrapper;
}

describe('src/app/component/form/sw-text-field', () => {
    beforeAll(() => {
        Shopware.Component.register('sw-text-field-mock', {
            template: `
            <div>
                <sw-text-field v-model:value="mockVar" class="no-suffix" name="sw-field--mockVar" />
                <sw-text-field v-model:value="mockVar" class="with-suffix" name="sw-field--mockVar-iShallBeSuffix" />
            </div>`,

            data() {
                return {
                    mockVar: 'content',
                };
            },
        });
    });

    it('should render with custom html attributes like minlength and maxlength', async () => {
        const wrapper = await createWrapper({
            attrs: {
                maxlength: '12',
                minlength: '4',
            },
        });

        expect(wrapper.find('input[type="text"]').attributes().maxlength).toBe('12');
        expect(wrapper.find('input[type="text"]').attributes().minlength).toBe('4');
    });

    it('should show the label from the property', async () => {
        const wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
            slots: {
                label: '<template>Label from slot</template>',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('injects ariaLabel prop from global injection', async () => {
        const wrapper = await createWrapper({
            provide: {
                ariaLabel: ref('Aria Label'),
            },
        });
        await flushPromises();

        expect(wrapper.find('input').attributes('aria-label')).toBe('Aria Label');
    });
});
