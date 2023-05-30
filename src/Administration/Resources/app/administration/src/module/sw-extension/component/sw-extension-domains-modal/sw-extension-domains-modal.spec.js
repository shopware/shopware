import { shallowMount } from '@vue/test-utils';
import swExtensionDomainsModal from 'src/module/sw-extension/component/sw-extension-domains-modal';

Shopware.Component.register('sw-extension-domains-modal', swExtensionDomainsModal);

async function createWrapper(propsData) {
    return shallowMount(await Shopware.Component.build('sw-extension-domains-modal'), {
        propsData: {
            extensionLabel: 'SEO Professional App',
            ...propsData,
        },
        mocks: {
            $t: (...args) => JSON.stringify([...args]),
            $tc: (...args) => JSON.stringify([...args]),
        },
        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot></div>',
            },
        },
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-domains-modal', () => {
    it('should not show any domains: null', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();

        const wrapper = await createWrapper({
            domains: null,
        });

        expect(errorSpy).toHaveBeenCalled();
        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    it('should not show any domains: undefined', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();

        const wrapper = await createWrapper({
            domains: undefined,
        });

        expect(errorSpy).toHaveBeenCalled();
        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    it('should not show any domains: []', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation();

        const wrapper = await createWrapper({
            domains: [],
        });

        expect(errorSpy).not.toHaveBeenCalled();

        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    [
        ['htpps://www.google.com'],
        ['https://www.google.com', 'https://bing.com'],
    ].forEach(domains => {
        it(`should show the domains which are given via the property, domain count: ${domains.length}`, async () => {
            const wrapper = await createWrapper({
                domains,
            });

            expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(domains.length);

            domains.forEach(domain => {
                expect(wrapper.find('.sw-extension-domains-modal__list').text()).toContain(domain);
            });
        });
    });
});
