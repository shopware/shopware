/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper(config) {
    return mount(
        await wrapTestComponent('sw-cms-el-age-verification', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
            },
            props: {
                element: {
                    config,
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/age-verification/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('renders the configured title and content', async () => {
        const wrapper = await createWrapper({
            minimumAge: { value: 18 },
            title: { value: 'Please confirm your age' },
            content: { value: 'Custom notice' },
            confirmButtonText: { value: 'Yes' },
            declineButtonText: { value: 'No' },
            declineUrl: { value: '' },
            cookieLifetime: { value: 30 },
        });

        expect(wrapper.text()).toContain('Please confirm your age');
        expect(wrapper.text()).toContain('Custom notice');
        expect(wrapper.text()).toContain('Yes');
        expect(wrapper.text()).toContain('No');
    });

    it('falls back to default translated texts when fields are empty', async () => {
        const wrapper = await createWrapper({
            minimumAge: { value: 21 },
            title: { value: '' },
            content: { value: '' },
            confirmButtonText: { value: '' },
            declineButtonText: { value: '' },
            declineUrl: { value: '' },
            cookieLifetime: { value: 30 },
        });

        // Snippets are not loaded in the unit environment, so $t returns the key.
        // Seeing the default keys proves the empty fields fell back to the translated defaults.
        expect(wrapper.text()).toContain('sw-cms.elements.ageVerification.preview.title');
        expect(wrapper.text()).toContain('sw-cms.elements.ageVerification.preview.content');
    });
});
