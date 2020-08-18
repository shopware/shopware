import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-detail-menu';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-category-detail-menu'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-switch-field': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-text-editor': true
        },
        mocks: {
            $tc: v => v
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            openMediaSidebar: () => {},
            repositoryFactory: {}
        },
        propsData: {
            category: {
                getEntityName: () => {}
            }
        }
    });
}

describe('src/module/sw-category/component/sw-category-detail-menu', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should enable the visibility switch field when the acl privilege is missing', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBeUndefined();
    });
    it('should disable the visibility switch field when the acl privilege is missing', () => {
        const wrapper = createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBe('true');
    });
    it('should enable the media upload', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');

        expect(mediaUpload.attributes().disabled).toBeUndefined();
    });
    it('should disable the media upload', () => {
        const wrapper = createWrapper();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');

        expect(mediaUpload.attributes().disabled).toBe('true');
    });
    it('should enable the text editor for the description', () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const textEditor = wrapper.find('sw-text-editor-stub');

        expect(textEditor.attributes().disabled).toBeUndefined();
    });
    it('should disable the text editor for the description', () => {
        const wrapper = createWrapper();

        const textEditor = wrapper.find('sw-text-editor-stub');

        expect(textEditor.attributes().disabled).toBe('true');
    });
});
