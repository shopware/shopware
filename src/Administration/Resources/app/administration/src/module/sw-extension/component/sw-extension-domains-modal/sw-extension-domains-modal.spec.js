import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-extension-domains-modal';

function createWrapper(propsData) {
    return shallowMount(Shopware.Component.build('sw-extension-domains-modal'), {
        propsData: {
            extensionLabel: 'SEO Professional App',
            ...propsData
        },
        mocks: {
            $t: (...args) => JSON.stringify([...args]),
            $tc: (...args) => JSON.stringify([...args])
        },
        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot></div>'
            }
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-domains-modal', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper({
            domains: []
        });

        expect(wrapper.vm).toBeTruthy();
    });

    [
        null,
        undefined,
        []
    ].forEach(domains => {
        it(`should not show any domains: ${domains}`, async () => {
            const errorSpy = jest.spyOn(console, 'error').mockImplementation();

            wrapper = createWrapper({
                domains
            });
            if (Array.isArray(domains)) {
                expect(errorSpy).not.toHaveBeenCalled();
            } else {
                expect(errorSpy).toHaveBeenCalled();
            }

            expect(wrapper.findAll('.sw-extension-domains-modal__list li').length).toBe(0);
        });
    });

    [
        ['htpps://www.google.com'],
        ['https://www.google.com', 'https://bing.com']
    ].forEach(domains => {
        it(`should show the domains which are given via the property, domain count: ${domains.length}`, async () => {
            wrapper = createWrapper({
                domains
            });

            expect(wrapper.findAll('.sw-extension-domains-modal__list li').length).toBe(domains.length);

            domains.forEach(domain => {
                expect(wrapper.find('.sw-extension-domains-modal__list').text()).toContain(domain);
            });
        });
    });
});
