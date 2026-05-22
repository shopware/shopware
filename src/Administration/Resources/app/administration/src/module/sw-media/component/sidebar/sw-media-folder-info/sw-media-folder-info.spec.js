/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-media/mixin/media-sidebar-modal.mixin';

const { Mixin } = Shopware;

async function createWrapper({ editable = false } = {}) {
    return mount(await wrapTestComponent('sw-media-folder-info', { sync: true }), {
        props: {
            mediaFolder: {
                id: 'jest',
                name: 'Test folder',
                createdAt: '2026-05-21T10:17:00.000+00:00',
                getEntityName: () => 'media_folder',
            },
            editable,
        },
        global: {
            mixins: [
                Mixin.getByName('media-sidebar-modal-mixin'),
            ],
            provide: {
                mediaService: {},
                repositoryFactory: {
                    create: () => ({
                        save: jest.fn(),
                    }),
                },
                acl: {
                    can: () => true,
                },
            },
            stubs: {
                'sw-media-collapse': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'sw-media-quickinfo-metadata-item': {
                    template: '<div><slot></slot></div>',
                },
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
        Shopware.Store.get('error').addApiError({
            expression: 'media_folder.jest.name',
            error: {
                code: 'some-error-code',
            },
        });

        const wrapper = await createWrapper();

        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': true,
        });
    });

    it('should not duplicate metadata labels inside editable fields', async () => {
        const wrapper = await createWrapper({ editable: true });

        const editableMetadataFields = wrapper.findAll('sw-confirm-field-stub');

        expect(editableMetadataFields).toHaveLength(1);
        expect(editableMetadataFields[0].attributes('label')).toBeUndefined();
        expect(editableMetadataFields[0].attributes('aria-label')).toBeTruthy();
    });
});
