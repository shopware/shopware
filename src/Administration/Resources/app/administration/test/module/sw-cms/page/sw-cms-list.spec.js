import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/page/sw-cms-list';
import 'src/module/sw-cms/component/sw-cms-list-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/data-grid/sw-data-grid';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {});
    localVue.filter('date', v => v);

    return shallowMount(Shopware.Component.build('sw-cms-list'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-tabs': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-field': {
                template: '<div></div>'
            },
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-pagination': {
                template: '<div></div>'
            },
            'sw-cms-list-item': Shopware.Component.build('sw-cms-list-item'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-popover': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu': {
                template: '<div><slot></slot></div>'
            },
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
            'sw-media-modal-v2': {
                template: '<div class="sw-media-modal-v2-mock"></div>'
            },
            'sw-button': true,
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'router-link': true,
            'sw-data-grid-skeleton': true
        },
        mocks: {
            $tc: (value) => value,
            $te: () => true,
            $router: { replace: () => {} },
            $route: { query: '' },
            $device: {
                onResize: () => {}
            }
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve() })
            },
            feature: {
                isActive: () => true
            }
        }
    });
}

describe('module/sw-cms/page/sw-cms-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the media modal when user clicks on edit preview image', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeFalsy();

        await wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-preview')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeTruthy();

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');
        expect(mediaModal.classes()).toContain('sw-media-modal-v2-mock');
    });

    it('should show a disabled create new button', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should show an enabled create new button', async () => {
        const wrapper = createWrapper([
            'cms.creator'
        ]);

        await wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should show disabled context fields in data grid view', async () => {
        const wrapper = createWrapper();

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled edit context fields in data grid view', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(false);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled duplicate context fields in data grid view', async () => {
        const wrapper = createWrapper([
            'cms.creator'
        ]);

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(false);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled delete context fields in data grid view', async () => {
        const wrapper = createWrapper([
            'cms.deleter'
        ]);

        await wrapper.find('.sw-cms-list__actions-mode')
            .trigger('click');

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-data-grid__actions-menu')
            .trigger('click');

        const contextMenuItemEdit = wrapper.find('.sw-cms-list__context-menu-item-edit');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list__context-menu-item-duplicate');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list__context-menu-item-delete');

        expect(contextMenuItemEdit.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should show disabled context fields in normal view', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled preview context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.editor'
        ]);

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(false);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled duplicate context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.creator'
        ]);

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(false);
        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should show enabled delete context field in normal view', async () => {
        const wrapper = createWrapper([
            'cms.deleter'
        ]);

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        const contextMenuItemPreview = wrapper.find('.sw-cms-list-item__option-preview');
        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');
        const contextMenuItemDuplicate = wrapper.find('.sw-cms-list-item__option-duplicate');

        expect(contextMenuItemPreview.props().disabled).toBe(true);
        expect(contextMenuItemDuplicate.props().disabled).toBe(true);
        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });

    it('should disable the delete menu item when the layout got assigned to at least one product', async () => {
        const wrapper = createWrapper(
            'cms.deleter'
        );

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [{}],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button').trigger('click');

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(true);
    });

    it('should enable the delete menu item when the layout do not belong to any product', async () => {
        const wrapper = createWrapper(
            'cms.deleter'
        );

        await wrapper.setData({
            isLoading: false,
            pages: [
                {
                    id: '1a',
                    sections: [],
                    categories: [],
                    products: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        await wrapper.find('.sw-cms-list-item--0 .sw-context-button__button').trigger('click');

        const contextMenuItemDelete = wrapper.find('.sw-cms-list-item__option-delete');

        expect(contextMenuItemDelete.props().disabled).toBe(false);
    });
});
