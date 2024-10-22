import { mount } from '@vue/test-utils';

async function createWrapper(propsData) {
    return mount(await wrapTestComponent('sw-extension-domains-modal', { sync: true }), {
        global: {
            mocks: {
                $t: (...args) => JSON.stringify([...args]),
                $tc: (...args) => JSON.stringify([...args]),
            },
            stubs: {
                'sw-button': true,
            },
        },
        props: {
            extensionLabel: 'SEO Professional App',
            ...propsData,
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-domains-modal', () => {
    it('should not show any domains: null', async () => {
        const wrapper = await createWrapper({
            domains: [],
        });

        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    it('should not show any domains: undefined', async () => {
        const wrapper = await createWrapper({
            domains: [],
        });

        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    it('should not show any domains: []', async () => {
        const wrapper = await createWrapper({
            domains: [],
        });

        expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(0);
    });

    [
        ['htpps://www.google.com'],
        [
            'https://www.google.com',
            'https://bing.com',
        ],
    ].forEach((domains) => {
        it(`should show the domains which are given via the property, domain count: ${domains.length}`, async () => {
            const wrapper = await createWrapper({
                domains,
            });

            expect(wrapper.findAll('.sw-extension-domains-modal__list li')).toHaveLength(domains.length);

            domains.forEach((domain) => {
                expect(wrapper.find('.sw-extension-domains-modal__list').text()).toContain(domain);
            });
        });
    });
});
