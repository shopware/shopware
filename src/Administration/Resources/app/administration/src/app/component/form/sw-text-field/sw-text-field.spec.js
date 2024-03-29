/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';

async function createWrapper(options = {}) {
    const wrapper = mount(await wrapTestComponent('sw-text-field', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-field-error': true,
            },
            provide: {
                validationService: {},
            },
        },
        ...options,
    });

    await flushPromises();

    return wrapper;
}

async function createWrappedComponent() {
    const wrapper = mount(await Shopware.Component.build(
        'sw-text-field-mock',
    ), {
        global: {
            stubs: {
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-field-error': true,
            },
            provide: {
                validationService: {},
            },
        },
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

    it('should render without idSuffix correctly', async () => {
        const wrapper = await createWrappedComponent();
        const noSuffix = wrapper.find('.no-suffix');

        expect(noSuffix.exists()).toBeTruthy();
        expect(noSuffix.find('#sw-field--mockVar').exists()).toBeTruthy();
    });

    it('should render with idSuffix correctly and generated a correct HTML-ID', async () => {
        const wrapper = await createWrappedComponent();
        const withSuffix = wrapper.find('.with-suffix');

        expect(withSuffix.exists()).toBeTruthy();
        expect(withSuffix.find('#sw-field--mockVar-iShallBeSuffix').exists()).toBeTruthy();
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
});
