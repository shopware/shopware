import { shallowMount, createLocalVue } from '@vue/test-utils';

import 'src/module/sw-extension/component/sw-extension-permissions-modal';
import 'src/app/component/base/sw-button';

function createWrapper({ permissions, extensionLabel, actionLabel }) {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);

    return shallowMount(Shopware.Component.build('sw-extension-permissions-modal'), {
        localVue,
        propsData: {
            permissions,
            extensionLabel,
            actionLabel
        },
        mocks: {
            $t: (...args) => JSON.stringify([...args]),
            $tc: (...args) => JSON.stringify([...args])
        },
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-modal': {
                props: ['title'],
                // eslint-disable-next-line max-len
                template: '<div><div class="sw-modal__title">{{ title }}</div><slot/><slot name="modal-footer"></slot></div>'
            },
            'sw-extension-permissions-details-modal': true,
            'sw-icon': true
        }
    });
}

describe('sw-extension-permissions-modal', () => {
    /** @type Wrapper */
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}]
            }
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have the correct title, discription and icon', () => {
        wrapper = createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}]
            }
        });

        expect(wrapper.find('.sw-modal__title').text()).toBe(JSON.stringify([
            'sw-extension-store.component.sw-extension-permissions-modal.title', {
                extensionLabel: 'Sample Extension Label'
            }
        ]));

        expect(wrapper.find('.sw-extension-permissions-modal__description').text()).toBe(JSON.stringify([
            'sw-extension-store.component.sw-extension-permissions-modal.description',
            { extensionLabel: 'Sample Extension Label' }
        ]));

        expect(wrapper.find('.sw-extension-permissions-modal__image')
            .attributes().src).toBe('/administration/static/img/extension-store/permissions.svg');
    });

    it('should display two detail links and open the correct detail page', () => {
        wrapper = createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}]
            }
        });

        const category = wrapper.findAll('.sw-extension-permissions-modal__category');

        expect(category.at(0).find('.sw-extension-permissions-modal__category-label').text()).toBe(JSON.stringify(
            ['entityCategories.product.title']
        ));

        expect(category.at(0).find('.sw-button__content').text()).toBe(JSON.stringify(
            ['sw-extension-store.component.sw-extension-permissions-modal.textEntities']
        ));

        // open details modal
        category.at(0).find('.sw-button__content').trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('product');
        expect(wrapper.vm.showDetailsModal).toBe(true);

        // close details modal
        wrapper.vm.closeDetailsModal();
        expect(wrapper.vm.selectedEntity).toBe('');
        expect(wrapper.vm.showDetailsModal).toBe(false);

        expect(category.at(1).find('.sw-extension-permissions-modal__category-label').text()).toBe(JSON.stringify(
            ['entityCategories.promotion.title']
        ));

        expect(category.at(1).find('.sw-button__content').text()).toBe(JSON.stringify(
            ['sw-extension-store.component.sw-extension-permissions-modal.textEntities']
        ));

        // open details modal
        category.at(1).find('.sw-button__content').trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('promotion');
        expect(wrapper.vm.showDetailsModal).toBe(true);
    });
});
