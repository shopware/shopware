import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';
import swMediaFolderInfo from 'src/module/sw-media/component/sidebar/sw-media-folder-info';

const { Mixin } = Shopware;

Shopware.Component.register('sw-media-folder-info', swMediaFolderInfo);

async function createWrapper(privileges = [], options = {}) {
    return shallowMount(await Shopware.Component.build('sw-media-folder-info'), {
        provide: {
            mediaService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            mixins: [
                Mixin.getByName('media-sidebar-modal-mixin'),
            ],
        },
        propsData: {
            mediaFolder: {
                name: 'Test folder',
                getEntityName: () => 'media_folder',
            },
            editable: false,
        },
        stubs: {
            'sw-media-collapse': true,
            'sw-media-quickinfo-metadata-item': true,
        },
        ...options,
    });
}

describe('src/module/sw-media/component/sidebar/sw-media-folder-info', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not have error class by default', async () => {
        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': false,
        });
    });

    it('should have error class while having folder name error', async () => {
        const component = await createWrapper([], {
            computed: {
                mediaFolderNameError: () => 'Error',
            }
        });

        expect(component.vm.nameItemClasses).toStrictEqual({
            'has--error': true,
        });

        component.destroy();
    });
});
