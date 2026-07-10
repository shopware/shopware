/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-age-verification', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
                stubs: {
                    'mt-number-field': true,
                    'mt-text-field': true,
                    'mt-textarea': true,
                },
            },
            props: {
                element: {
                    config: {
                        minimumAge: { value: 18 },
                        title: { value: '' },
                        content: { value: '' },
                        confirmButtonText: { value: '' },
                        declineButtonText: { value: '' },
                        declineUrl: { value: '' },
                        cookieLifetime: { value: 30 },
                    },
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/age-verification/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should update the config value and emit element-update on change', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onFieldChange('minimumAge', 21);

        expect(wrapper.vm.element.config.minimumAge.value).toBe(21);
        expect(wrapper.emitted('element-update')).toBeTruthy();
    });

    it('should not emit element-update when the value is unchanged', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onFieldChange('minimumAge', 18);

        expect(wrapper.emitted('element-update')).toBeFalsy();
    });
});
