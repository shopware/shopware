import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-mail-template/page/sw-mail-template-detail';
import EntityCollection from 'src/core/data-new/entity-collection.data';

const { StateDeprecated, DataDeprecated } = Shopware;
const LanguageStore = DataDeprecated.LanguageStore;

const mailTemplateMock = {
    id: 'ed3866445dd744bb9e0f88f8f340141f',
    media: [],
    isNew: () => false
};

const mediaMock = [
    {
        id: '88uy773yd1ssd299si1d837dy1ud628',
        mailTemplateId: 'ed3866445dd744bb9e0f88f8f340141f',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        mediaId: '1svd4de52e6924d70ya5u75cd7ze4gd01',
        position: 0
    },
    {
        id: 'ad3466455ed794bb9e0f28s8g3701s1z',
        mailTemplateId: 'ed3866445dd744bb9e0f88f8f340141f',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        mediaId: '30c0082ccb03494799b42f22c7fa07d9',
        position: 0
    }
];

const mailTemplateMediaMock = {
    id: '30c0082ccb03494799b42f22c7fa07d9',
    userId: 'bc249402e55e4dd0b24f7e40e0a66d87',
    mediaFolderId: 'b1e13948a7c845dab6ef566097558cc2',
    mimeType: 'image/jpeg',
    fileExtension: 'jpg',
    fileName: 'untitled-3-15870000742491754447780',
    fileSize: 792866
};

const repositoryMockFactory = () => {
    return {
        search: () => Promise.resolve({}),
        get: (resolve = null) => {
            if (resolve === 'mailTemplateMediaTestId') {
                return Promise.resolve(mailTemplateMediaMock);
            }

            return Promise.resolve(mailTemplateMock);
        },
        create: () => {
            return {
                mailTemplateId: {},
                languageId: {},
                mediaId: {},
                position: 0
            };
        }
    };
};

const createWrapper = (privileges = []) => {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-mail-template-detail'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => repositoryMockFactory()
            },
            mailService: {},
            entityMappingService: {
                getEntityMapping: () => []
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        },
        mocks: {
            $tc: (translationPath) => translationPath,
            $router: { replace: () => {} },
            $route: { params: { id: Shopware.Utils.createId() } },
            $device: {
                getSystemKey: () => 'CTRL'
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-language-info': true,
            'sw-entity-single-select': true,
            'sw-entity-multi-select': true,
            'sw-field': true,
            'sw-text-field': true,
            'sw-context-menu-item': true,
            'sw-code-editor': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-sidebar': {
                template: '<div><slot></slot></div>'
            },
            'sw-sidebar-item': {
                template: '<div><slot></slot></div>'
            },
            'sw-sidebar-media-item': {
                template: '<div><slot name="context-menu-items"></slot></div>'
            }
        }
    });
};

describe('modules/sw-mail-template/page/sw-mail-template-detail', () => {
    let wrapper;
    beforeEach(() => {
        wrapper = createWrapper();

        const languageStore = new LanguageStore(
            'languageService',
            DataDeprecated.EntityProxy,
            Shopware.Utils.createId()
        );
        StateDeprecated.registerStore('language', languageStore);
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to add an item to the attachment', async () => {
        await wrapper.setData({ mailTemplateMedia: [] });
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be unable to add an item to the attachment exist this item', async () => {
        wrapper.vm.createNotificationInfo = jest.fn();
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-mail-template.list.errorMediaItemDuplicated'
        });

        wrapper.vm.createNotificationInfo.mockRestore();
    });

    it('should be success to get media columns', async () => {
        expect(wrapper.vm.getMediaColumns().length).toBe(1);
    });

    it('should be success to upload an attachment', async () => {
        await wrapper.setData({
            mailTemplate: {
                media: new EntityCollection(
                    '/media',
                    'media',
                    null,
                    { isShopwareContext: true },
                    mediaMock,
                    mediaMock.length,
                    null
                )
            }
        });
        wrapper.vm.successfulUpload({ targetId: 'mailTemplateMediaTestId' });

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be return if the user upload duplicated the attachment', async () => {
        await wrapper.setData({ mailTemplate: mailTemplateMock });
        const mediaLengthBeforeTest = wrapper.vm.mailTemplate.media.length;

        expect(wrapper.vm.successfulUpload({ targetId: '30c0082ccb03494799b42f22c7fa07d9' })).toBeUndefined();
        expect(wrapper.vm.mailTemplate.media.length).toBe(mediaLengthBeforeTest);
    });

    it('should be able to delete media', async () => {
        await wrapper.setData({
            mailTemplateMedia: [mailTemplateMediaMock]
        });

        wrapper.vm.successfulUpload({ targetId: 'mailTemplateMediaTestId' });

        wrapper.vm.onSelectionChanged({
            '30c0082ccb03494799b42f22c7fa07d9': { mailTemplateMediaMock }
        });

        const hasMediaBeforeTest = wrapper.vm.mailTemplate.media
            .some((media) => media.id === 'ad3466455ed794bb9e0f28s8g3701s1z');
        expect(hasMediaBeforeTest).toBeTruthy();

        wrapper.vm.onDeleteSelectedMedia();

        expect(wrapper.vm.mailTemplate.media.length).toBe(mailTemplateMock.media.length);
        const hasMediaAfterTest = wrapper.vm.mailTemplate.media
            .some((media) => media.id === 'ad3466455ed794bb9e0f28s8g3701s1z');
        expect(hasMediaAfterTest).toBeFalsy();
    });

    it('all fields should be disabled without edit permission', async () => {
        wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.setData({ mailTemplateMedia: [mailTemplateMediaMock] });
        await wrapper.vm.$nextTick();

        [
            { selector: wrapper.find('.sw-mail-template-detail__save-action'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.findAll('sw-field-stub'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.findAll('sw-code-editor-stub'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.findAll('sw-context-menu-item-stub'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.find('sw-entity-single-select-stub'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.find('sw-media-upload-v2-stub'), attribute: 'disabled', expect: 'true' },
            { selector: wrapper.find('sw-text-field-stub'), attribute: 'disabled', expect: 'true' },
            {
                selector: wrapper.find('.sw-mail-template-detail__attachments-info-grid'),
                attribute: 'showselection',
                expect: undefined
            }
        ].forEach(element => {
            if (element.selector.length > 1) {
                element.selector.wrappers.forEach(el => {
                    expect(el.attributes()[element.attribute]).toBe(element.expect);
                });
            } else {
                expect(element.selector.attributes()[element.attribute]).toBe(element.expect);
            }
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true
        });
    });

    it('all fields should be enabled with edit permission', async () => {
        wrapper = createWrapper(['mail_templates.editor']);
        await wrapper.vm.$nextTick();

        wrapper.setData({ mailTemplateMedia: [mailTemplateMediaMock] });
        await wrapper.vm.$nextTick();

        [
            { selector: wrapper.find('.sw-mail-template-detail__save-action'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.findAll('sw-field-stub'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.findAll('sw-code-editor-stub'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.findAll('sw-context-menu-item-stub'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.find('sw-entity-single-select-stub'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.find('sw-media-upload-v2-stub'), attribute: 'disabled', expect: undefined },
            { selector: wrapper.find('sw-text-field-stub'), attribute: 'disabled', expect: undefined },
            {
                selector: wrapper.find('.sw-mail-template-detail__attachments-info-grid'),
                attribute: 'showselection',
                expect: 'true'
            }
        ].forEach(element => {
            if (element.selector.length > 1) {
                element.selector.wrappers.forEach(el => {
                    expect(el.attributes()[element.attribute]).toBe(element.expect);
                });
            } else {
                expect(element.selector.attributes()[element.attribute]).toBe(element.expect);
            }
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light'
        });
    });
});
