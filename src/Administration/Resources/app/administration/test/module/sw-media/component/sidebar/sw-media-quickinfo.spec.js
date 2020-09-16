import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';
import 'src/module/sw-media/component/sidebar/sw-media-quickinfo';
import 'src/module/sw-media/component/sw-media-collapse';

const { Mixin } = Shopware;

const itemMock = (options = {}) => {
    return {
        getEntityName: () => { return 'media'; },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        avatarUser: {},
        categories: [],
        productManufacturers: [],
        productMedia: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],
        ...options
    };
};

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-media-quickinfo'), {
        localVue,
        mocks: {
            $tc: () => {},
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve();
                    }
                })
            },
            mediaService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            mixins: [
                Mixin.getByName('notification'),
                Mixin.getByName('media-sidebar-modal-mixin'),
                Mixin.getByName('placeholder')
            ]
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-alert': true,
            'sw-icon': true,
            'sw-media-collapse': {
                template: `
                    <div class="sw-media-quickinfo">
                        <slot name="content"></slot>
                    </div>`
            },
            'sw-media-quickinfo-metadata-item': true,
            'sw-media-preview-v2': true,
            'sw-media-tag': true,
            'sw-custom-field-set-renderer': true
        },
        propsData: {
            item: itemMock(),
            editable: true
        }
    });
}

describe('module/sw-media/components/sw-media-quickinfo', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'media.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).toContain('sw-media-sidebar__quickaction--disabled');
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'media.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).not.toContain('sw-media-sidebar__quickaction--disabled');
    });
});

