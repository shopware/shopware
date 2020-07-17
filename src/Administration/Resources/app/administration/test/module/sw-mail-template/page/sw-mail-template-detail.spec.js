import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-mail-template/page/sw-mail-template-detail';
import EntityCollection from 'src/core/data-new/entity-collection.data';

const { StateDeprecated, DataDeprecated } = Shopware;
const LanguageStore = DataDeprecated.LanguageStore;

const mailTemplateMock = {
    id: 'ed3866445dd744bb9e0f88f8f340141f',
    media: []
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

describe('modules/sw-mail-template/page/sw-mail-template-detail', () => {
    let wrapper;
    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-mail-template-detail'), {
            provide: {
                repositoryFactory: {
                    create: () => repositoryMockFactory()
                },
                mailService: {},
                entityMappingService: {
                    getEntityMapping: () => []
                }
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $router: { replace: () => {} },
                $route: { params: { id: Shopware.Utils.createId() } }
            },
            stubs: {
                'sw-page': true
            }
        });

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

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should be able to add an item to the attachment', () => {
        wrapper.setData({ mailTemplateMedia: [] });
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be unable to add an item to the attachment exist this item', () => {
        wrapper.vm.createNotificationInfo = jest.fn();
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-mail-template.list.errorMediaItemDuplicated'
        });

        wrapper.vm.createNotificationInfo.mockRestore();
    });

    it('should be success to get media columns', () => {
        expect(wrapper.vm.getMediaColumns().length).toBe(1);
    });

    it('should be success to upload an attachment', () => {
        wrapper.setData({
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

    it('should be return if the user upload duplicated the attachment', () => {
        wrapper.setData({ mailTemplate: mailTemplateMock });
        const mediaLengthBeforeTest = wrapper.vm.mailTemplate.media.length;

        expect(wrapper.vm.successfulUpload({ targetId: '30c0082ccb03494799b42f22c7fa07d9' })).toBeUndefined();
        expect(wrapper.vm.mailTemplate.media.length).toBe(mediaLengthBeforeTest);
    });

    it('should be able to delete media', () => {
        wrapper.setData({
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
});
