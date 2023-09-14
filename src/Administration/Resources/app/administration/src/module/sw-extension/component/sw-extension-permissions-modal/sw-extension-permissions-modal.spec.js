import { shallowMount, createLocalVue } from '@vue/test-utils';

import swExtensionPermissionsModal from 'src/module/sw-extension/component/sw-extension-permissions-modal';
import 'src/app/component/base/sw-button';

Shopware.Component.register('sw-extension-permissions-modal', swExtensionPermissionsModal);

async function createWrapper(propsData) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-extension-permissions-modal'), {
        localVue,
        propsData: {
            ...propsData,
        },
        mocks: {
            $t: (...args) => JSON.stringify([...args]),
            $tc: (...args) => JSON.stringify([...args]),
        },
        stubs: {
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-modal': {
                props: ['title'],
                // eslint-disable-next-line max-len
                template: '<div><div class="sw-modal__title">{{ title }}</div><slot/><slot name="modal-footer"></slot></div>',
            },
            'sw-extension-permissions-details-modal': true,
            'sw-icon': true,
        },
    });
}

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/component/sw-extension-permissions-modal', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}],
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have the correct title, discription and icon', async () => {
        wrapper = await createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}],
            },
        });

        expect(wrapper.find('.sw-modal__title').text()).toBe(JSON.stringify([
            'sw-extension-store.component.sw-extension-permissions-modal.title',
            1,
            {
                extensionLabel: 'Sample Extension Label',
            },
        ]));

        expect(wrapper.find('.sw-extension-permissions-modal__description').text()).toBe(JSON.stringify([
            'sw-extension-store.component.sw-extension-permissions-modal.description',
            1,
            { extensionLabel: 'Sample Extension Label' },
        ]));

        expect(wrapper.find('.sw-extension-permissions-modal__image')
            .attributes().src).toBe('administration/static/img/extension-store/permissions.svg');
    });

    it('should display two detail links and open the correct detail page', async () => {
        wrapper = await createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}],
            },
        });

        const category = wrapper.findAll('.sw-extension-permissions-modal__category');

        expect(category.at(0).find('.sw-extension-permissions-modal__category-label').text()).toBe(JSON.stringify(
            ['entityCategories.product.title'],
        ));

        expect(category.at(0).find('.sw-button__content').text()).toBe(JSON.stringify(
            ['sw-extension-store.component.sw-extension-permissions-modal.textEntities'],
        ));

        // open details modal
        await category.at(0).find('.sw-button__content').trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('product');
        expect(wrapper.vm.showDetailsModal).toBe(true);

        // close details modal
        wrapper.vm.closeDetailsModal();
        expect(wrapper.vm.selectedEntity).toBe('');
        expect(wrapper.vm.showDetailsModal).toBe(false);

        expect(category.at(1).find('.sw-extension-permissions-modal__category-label').text()).toBe(JSON.stringify(
            ['entityCategories.promotion.title'],
        ));

        expect(category.at(1).find('.sw-button__content').text()).toBe(JSON.stringify(
            ['sw-extension-store.component.sw-extension-permissions-modal.textEntities'],
        ));

        // open details modal
        await category.at(1).find('.sw-button__content').trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('promotion');
        expect(wrapper.vm.showDetailsModal).toBe(true);
    });

    [
        ['http://www.google.com'],
        ['http://www.google.com', 'https://www.facebook.com'],
        ['http://www.google.com', 'https://www.facebook.com', 'https://www.amazon.com'],
    ].forEach(domains => {
        it(`should display domains hint with domain length of ${domains.length}`, async () => {
            wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains: domains,
            });

            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.domainHint');
        });
    });

    [
        ['http://www.google.com'],
        ['http://www.google.com', 'https://www.facebook.com'],
        ['http://www.google.com', 'https://www.facebook.com', 'https://www.amazon.com'],
    ].forEach(domains => {
        it('should display category domains', async () => {
            wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            // check for show domains entry
            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.domains');

            // check for button text
            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.showDomains');
        });
    });

    [
        [],
        null,
        undefined,
    ].forEach(domains => {
        it(`should not display domains hint when prop domains contains ${domains}`, async () => {
            wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            expect(wrapper.text()).not.toContain('sw-extension-store.component.sw-extension-permissions-modal.domainHint');
        });
    });


    [
        [],
        null,
        undefined,
    ].forEach(domains => {
        it('should not display category domains', async () => {
            wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            expect(wrapper.text()).not.toContain('sw-extension-store.component.sw-extension-permissions-modal.showDomains');
        });
    });
});
