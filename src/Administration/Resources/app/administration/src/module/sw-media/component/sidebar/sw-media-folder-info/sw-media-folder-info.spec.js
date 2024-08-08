/**
 * @package content
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';

const { Mixin } = Shopware;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-media-folder-info', { sync: true }), {
        props: {
            mediaFolder: {
                id: 'jest',
                name: 'Test folder',
                getEntityName: () => 'media_folder',
            },
            editable: false,
        },
        global: {
            mixins: [
                Mixin.getByName('media-sidebar-modal-mixin'),
            ],
            provide: {
                mediaService: {},
            },
            stubs: {
                'sw-media-collapse': true,
                'sw-media-quickinfo-metadata-item': true,
                'sw-icon': true,
                'sw-confirm-field': true,
                'sw-media-modal-folder-settings': true,
                'sw-media-modal-folder-dissolve': true,
                'sw-media-modal-move': true,
                'sw-media-modal-delete': true,
            },
        },
    });
}

describe('src/module/sw-media/component/sidebar/sw-media-folder-info', () => {
    it('should not have error class by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': false,
        });
    });

    it('should have error class while having folder name error', async () => {
        Shopware.State.dispatch('error/addApiError', {
            expression: 'media_folder.jest.name',
            error: {
                code: 'some-error-code',
            },
        });
        const wrapper = await createWrapper(true);

        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': true,
        });
    });
});
