import { createLocalVue, mount } from '@vue/test-utils';
import 'src/module/sw-manufacturer/page/sw-manufacturer-detail';


function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return mount(Shopware.Component.build('sw-manufacturer-detail'), {
        localVue,
        data() {
            return {
                isLoading: false,
                manufacturer: {
                    mediaId: null,
                    link: 'https://google.com/doodles',
                    name: 'What does it means?(TM)',
                    description: null,
                    customFields: null,
                    apiAlias: null,
                    id: 'id'
                }
            };
        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>'
            },
            'sw-media-upload-v2': {
                props: ['disabled'],
                template: '<div></div>'
            },
            'sw-text-editor': {
                template: '<div class="sw-text-editor"/>'
            },
            'sw-card': {
                template: '<div class="sw-card"><slot /></div>'
            },
            'sw-field': {
                template: '<div class="sw-field"/>'
            },
            'sw-card-view': {
                template: '<div><slot /></div>'
            },
            'sw-upload-listener': true,
            'sw-button-process': true,
            'sw-language-info': true,
            'sw-empty-state': true,
            'sw-container': true,
            'sw-button': true,
            'sw-loader': true
        },
        provide: {
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            stateStyleDataProviderService: {},
            repositoryFactory: {
                create: () => (
                    {
                        search: () => Promise.resolve([]),
                        get: () => Promise.resolve([]),
                        create: () => {}
                    })
            }
        },
        mocks: {
            $tc: v => v,
            $route: {},
            $router: {
                replace: () => {}
            },
            $device: {
                getSystemKey: () => {}
            }
        },
        propsData: {
            manufacturerId: 'id'
        }
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save edit', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.editor'
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-manufacturer-detail__save-action');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-manufacturer-detail__save-action');
        expect(addButton.attributes().disabled).toBeTruthy();
    });


    it('should be able to edit the manufacturer', async () => {
        const wrapper = createWrapper([
            'product_manufacturer.editor'
        ]);
        await wrapper.vm.$nextTick();


        const logoUpload = wrapper.find('.sw-manufacturer-detail__logo-upload');
        expect(logoUpload.exists()).toBeTruthy();
        expect(logoUpload.props().disabled).toBeFalsy();

        const elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toEqual(2);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBeUndefined());


        const textEditor = wrapper.find('.sw-text-editor');
        expect(textEditor.exists()).toBeTruthy();
        expect(textEditor.attributes().disabled).toBeUndefined();
    });

    it('should not be able to edit the manufacture', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const logoUpload = wrapper.find('.sw-manufacturer-detail__logo-upload');
        expect(logoUpload.exists()).toBeTruthy();
        expect(logoUpload.props().disabled).toBeTruthy();

        const elements = wrapper.findAll('.sw-field');
        expect(elements.wrappers.length).toEqual(2);
        elements.wrappers.forEach(el => expect(el.attributes().disabled).toBe('disabled'));

        const textEditor = wrapper.find('.sw-text-editor');
        expect(textEditor.exists()).toBeTruthy();
        expect(textEditor.attributes().disabled).toBeTruthy();
    });
});
