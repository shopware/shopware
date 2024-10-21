/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const mailTemplateTypeMock = {
    id: '6666673yd1ssd299si1d837dy1ud628',
    name: 'Type name',
    contentHtml: '',
};

const mailTemplateMock = {
    id: 'ed3866445dd744bb9e0f88f8f340141f',
    media: [],
    mailTemplateType: mailTemplateTypeMock,
    isNew: () => false,
};

const refsMock = {
    htmlEditor: {
        defineAutocompletion: jest.fn(),
    },
    plainEditor: {
        defineAutocompletion: jest.fn(),
    },
};

const mediaMock = [
    {
        id: '88uy773yd1ssd299si1d837dy1ud628',
        mailTemplateId: 'ed3866445dd744bb9e0f88f8f340141f',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        mediaId: '1svd4de52e6924d70ya5u75cd7ze4gd01',
        position: 0,
    },
    {
        id: 'ad3466455ed794bb9e0f28s8g3701s1z',
        mailTemplateId: 'ed3866445dd744bb9e0f88f8f340141f',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        mediaId: '30c0082ccb03494799b42f22c7fa07d9',
        position: 0,
    },
];

const mailTemplateMediaMock = {
    id: '30c0082ccb03494799b42f22c7fa07d9',
    userId: 'bc249402e55e4dd0b24f7e40e0a66d87',
    mediaFolderId: 'b1e13948a7c845dab6ef566097558cc2',
    mimeType: 'image/jpeg',
    fileExtension: 'jpg',
    fileName: 'untitled-3-15870000742491754447780',
    fileSize: 792866,
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
                position: 0,
            };
        },
    };
};

class SyntaxValidationTemplateError extends Error {
    response = {
        data: {
            errors: [
                {
                    detail: 'Ooops, syntax eror',
                },
            ],
        },
    };
}

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-mail-template-detail', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => repositoryMockFactory(),
                },
                mailService: {
                    testMailTemplate: jest.fn(() => Promise.resolve()),
                    buildRenderPreview: jest.fn(() => Promise.reject(new SyntaxValidationTemplateError())),
                },
                entityMappingService: {
                    getEntityMapping: () => [],
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            mocks: {
                $route: { params: { id: Shopware.Utils.createId() } },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>`,
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>',
                },
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button', {
                    sync: true,
                }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-entity-single-select': true,
                'sw-entity-multi-select': true,
                'sw-textarea-field': true,
                'sw-modal': true,
                'sw-text-field': true,
                'sw-context-menu-item': true,
                'sw-code-editor': {
                    props: [
                        'disabled',
                    ],
                    template: '<input type="text" class="sw-code-editor" :disabled="disabled" />',
                    methods: {
                        defineAutocompletion() {},
                    },
                },
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated'),
                'icons-regular-products-s': {
                    template: '<div class="sw-mail-template-detail__copy_icon" @click="$emit(\'click\')"></div>',
                },
                'sw-tree': await wrapTestComponent('sw-tree'),
                'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                'sw-tree-input-field': await wrapTestComponent('sw-tree-input-field'),
                'sw-confirm-field': true,
                'sw-loader': true,
                'sw-vnode-renderer': true,
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`,
                },
                'sw-sidebar': {
                    template: '<div><slot></slot></div>',
                },
                'sw-sidebar-item': {
                    template: '<div><slot></slot></div>',
                },
                'sw-sidebar-media-item': {
                    template: '<div><slot name="context-menu-items"></slot></div>',
                },
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-media-preview': true,
                'router-link': true,
                'sw-checkbox-field': true,
                'sw-context-button': true,
            },
        },
    });
}

describe('modules/sw-mail-template/page/sw-mail-template-detail', () => {
    let wrapper;

    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should be able to add an item to the attachment', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ mailTemplateMedia: [] });
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be unable to add an item to the attachment exist this item', async () => {
        wrapper = await createWrapper();
        wrapper.vm.createNotificationInfo = jest.fn();
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-mail-template.list.errorMediaItemDuplicated',
        });

        wrapper.vm.createNotificationInfo.mockRestore();
    });

    it('should be success to get media columns', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm.getMediaColumns()).toHaveLength(1);
    });

    it('should be success to upload an attachment', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            mailTemplate: {
                media: new EntityCollection(
                    '/media',
                    'media',
                    null,
                    { isShopwareContext: true },
                    mediaMock,
                    mediaMock.length,
                    null,
                ),
            },
        });
        wrapper.vm.successfulUpload({ targetId: 'mailTemplateMediaTestId' });

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be return if the user upload duplicated the attachment', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ mailTemplate: mailTemplateMock });
        const mediaLengthBeforeTest = wrapper.vm.mailTemplate.media.length;

        expect(
            wrapper.vm.successfulUpload({
                targetId: '30c0082ccb03494799b42f22c7fa07d9',
            }),
        ).toBeUndefined();
        expect(wrapper.vm.mailTemplate.media).toHaveLength(mediaLengthBeforeTest);
    });

    it('should be able to delete media', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            mailTemplateMedia: [mailTemplateMediaMock],
            mailTemplate: {
                media: new EntityCollection(
                    '/media',
                    'media',
                    null,
                    { isShopwareContext: true },
                    mediaMock,
                    mediaMock.length,
                    null,
                ),
            },
        });

        wrapper.vm.successfulUpload({ targetId: 'mailTemplateMediaTestId' });

        wrapper.vm.onSelectionChanged({
            '30c0082ccb03494799b42f22c7fa07d9': { mailTemplateMediaMock },
        });

        const hasMediaBeforeTest = wrapper.vm.mailTemplate.media.some(
            (media) => media.id === 'ad3466455ed794bb9e0f28s8g3701s1z',
        );
        expect(hasMediaBeforeTest).toBeTruthy();

        wrapper.vm.onDeleteSelectedMedia();

        expect(wrapper.vm.mailTemplate.media).toHaveLength(mailTemplateMock.media.length);
        const hasMediaAfterTest = wrapper.vm.mailTemplate.media.some(
            (media) => media.id === 'ad3466455ed794bb9e0f28s8g3701s1z',
        );
        expect(hasMediaAfterTest).toBeFalsy();
    });

    it('all fields should be disabled without edit permission', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: false,
            mailTemplateMedia: [mailTemplateMediaMock],
        });

        [
            {
                selector: wrapper.find('.sw-mail-template-detail__save-action'),
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: {
                    wrappers: wrapper.findAll('sw-textarea-field-stub'),
                },
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: { wrappers: wrapper.findAll('.sw-code-editor') },
                attribute: 'disabled',
                expect: '',
            },
            {
                selector: {
                    wrappers: wrapper.findAll('sw-context-menu-item-stub'),
                },
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: wrapper.find('sw-entity-single-select-stub'),
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: wrapper.find('sw-media-upload-v2-stub'),
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: { wrappers: wrapper.findAll('sw-text-field-stub') },
                attribute: 'disabled',
                expect: 'true',
            },
            {
                selector: wrapper.find('.sw-mail-template-detail__attachments-info-grid'),
                attribute: 'show-selection',
                expect: undefined,
            },
        ].forEach((element) => {
            if (!Array.isArray(element.selector.wrappers)) {
                element.selector = { wrappers: [element.selector] };
            }

            element.selector.wrappers.forEach((el) => {
                expect(el.attributes()[element.attribute]).toBe(element.expect);
            });
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'sw-privileges.tooltip.warning',
            disabled: false,
            showOnDisabledElements: true,
        });
    });

    it('all fields should be enabled with edit permission', async () => {
        wrapper = await createWrapper(['mail_templates.editor']);
        await wrapper.setData({
            mailTemplateMedia: [mailTemplateMediaMock],
            isLoading: false,
        });
        await flushPromises();

        [
            {
                selector: wrapper.find('.sw-mail-template-detail__save-action'),
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: {
                    wrappers: wrapper.findAll('sw-textarea-field-stub'),
                },
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: { wrappers: wrapper.findAll('.sw-code-editor') },
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: {
                    wrappers: wrapper.findAll('sw-context-menu-item-stub'),
                },
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: wrapper.find('sw-entity-single-select-stub'),
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: wrapper.find('sw-media-upload-v2-stub'),
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: { wrappers: wrapper.findAll('sw-text-field-stub') },
                attribute: 'disabled',
                expect: undefined,
            },
            {
                selector: wrapper.find('.sw-mail-template-detail__attachments-info-grid'),
                attribute: 'show-selection',
                expect: 'true',
            },
        ].forEach((element) => {
            if (!Array.isArray(element.selector.wrappers)) {
                element.selector = { wrappers: [element.selector] };
            }

            element.selector.wrappers.forEach((el) => {
                expect(el.attributes()[element.attribute]).toBe(element.expect);
            });
        });

        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });

    it('should not be able to show preview if html content is empty', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({ mailTemplate: mailTemplateTypeMock });

        const sidebarItem = wrapper.find('[icon=regular-eye]');

        expect(sidebarItem.attributes().disabled).toBeTruthy();
    });

    it('should not be able to send test mails when values are missing', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeDefined();
    });

    it('should be able to send test mails when values are filled', async () => {
        wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                // eslint-disable-next-line max-len
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();

        await sendTestMail.trigger('click');

        expect(wrapper.vm.mailService.testMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            wrapper.vm.mailTemplate,
            expect.anything(),
            '1a2b3c',
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );
    });

    it('should be able to send test mails when only inherited values are filled', async () => {
        wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: undefined,
                contentPlain: undefined,
                // eslint-disable-next-line max-len
                contentHtml: undefined,
                senderName: undefined,
                translated: {
                    subject: 'Your order with {{ salesChannel.name }} is partially paid',
                    contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                    // eslint-disable-next-line max-len
                    contentHtml:
                        '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                    senderName: '{{ salesChannel.name }}',
                },
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();

        await sendTestMail.trigger('click');

        expect(wrapper.vm.mailService.testMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            wrapper.vm.mailTemplate,
            expect.anything(),
            '1a2b3c',
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );
    });

    it('should copy variables to clipboard', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: () => new Promise(() => {}),
            },
        });

        const clipboardSpy = jest.spyOn(navigator.clipboard, 'writeText');

        wrapper = await createWrapper();

        const spyOnCopyVariable = jest.spyOn(wrapper.vm, 'onCopyVariable');

        wrapper.vm.addVariables([
            {
                id: 'order',
                name: 'order',
                childCount: 1,
                parentId: null,
                afterId: null,
            },
            {
                id: 'salesChannel',
                name: 'salesChannel',
                childCount: 1,
                parentId: null,
                afterId: null,
            },
        ]);

        wrapper.vm.mailTemplateType = {
            availableEntities: true,
            templateData: {
                order: {
                    deleveries: {
                        trackingCodes: {},
                    },
                },
            },
        };

        await flushPromises();

        const icon = await wrapper.find('.sw-mail-template-detail__copy_icon');
        await icon.trigger('click');

        await flushPromises();

        expect(spyOnCopyVariable).toHaveBeenCalled();
        expect(clipboardSpy).toHaveBeenCalled();
    });

    it('should have schema in variables', async () => {
        wrapper = await createWrapper();

        const spyIsToManyAssociationVariable = jest.spyOn(wrapper.vm, 'isToManyAssociationVariable');

        wrapper.vm.addVariables([
            {
                id: 'order',
                schema: 'order',
                name: 'order',
                childCount: 2,
                parentId: null,
                afterId: null,
            },
        ]);

        wrapper.vm.mailTemplateType = {
            availableEntities: true,
            templateData: {
                order: {
                    deleveries: {
                        trackingCodes: {},
                    },
                },
            },
        };

        await flushPromises();
        const icon = await wrapper.find('.icon--regular-chevron-right-xxs');
        await icon.trigger('click');

        expect(spyIsToManyAssociationVariable).toHaveBeenCalled();
    });

    it('should replace variables in html content when send mail test', async () => {
        wrapper = await createWrapper(['api_send_email']);

        const spyMailPreviewContent = jest.spyOn(wrapper.vm, 'mailPreviewContent');

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                // eslint-disable-next-line max-len
                contentHtml:
                    '{{ order.deliveries.first.stateMachineState.translated.name }} {{ order.deliveries.at(1).trackingCodes.0 }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        const sendTestMail = wrapper.find('.sw-mail-template-detail__send-test-mail');
        await sendTestMail.trigger('click');

        const contentHtmlAfterReplace =
            '{{ order.deliveries.0.stateMachineState.translated.name }} {{ order.deliveries.1.trackingCodes.0 }},<br/><br/>';
        const mailTemplate = { ...wrapper.vm.mailTemplate };
        mailTemplate.contentHtml = contentHtmlAfterReplace;

        expect(spyMailPreviewContent).toHaveBeenCalled();
        expect(wrapper.vm.mailService.testMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            mailTemplate,
            expect.anything(),
            '1a2b3c',
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );
    });

    it('should get specific error notification if using preview function with invalid template', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                // eslint-disable-next-line max-len
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
                mailTemplateTypeId: 'typeId',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        const previewSidebarButton = wrapper.findComponent('.sw-mail-template-detail__show-preview-sidebar');

        expect(previewSidebarButton.attributes().disabled).toBe('true');
        await previewSidebarButton.vm.$emit('click');

        await flushPromises();

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-mail-template.general.notificationSyntaxValidationErrorMessage',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should get general error notification if using preview function with invalid template', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                // eslint-disable-next-line max-len
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
                mailTemplateTypeId: 'typeId',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });
        wrapper.vm.mailService.buildRenderPreview = jest.fn(() => Promise.reject(new Error('Oops')));

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        const previewSidebarButton = wrapper.findComponent('.sw-mail-template-detail__show-preview-sidebar');

        expect(previewSidebarButton.attributes().disabled).toBe('true');
        await previewSidebarButton.vm.$emit('click');

        await flushPromises();

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should get error notification if using test mail function with invalid template', async () => {
        wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                // eslint-disable-next-line max-len
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();
        wrapper.vm.mailService.testMailTemplate = jest.fn(() => Promise.resolve({ size: 0 }));

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await sendTestMail.trigger('click');

        expect(wrapper.vm.mailService.testMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            wrapper.vm.mailTemplate,
            expect.anything(),
            '1a2b3c',
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage',
        });

        wrapper.vm.createNotificationError.mockRestore();
        await flushPromises();
    });

    it('should render mail template type name as language info description', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({ $refs: refsMock });
        await flushPromises();

        expect(wrapper.find('sw-language-info-stub').exists()).toBe(true);
        expect(wrapper.find('sw-language-info-stub').attributes('entity-description')).toBe(mailTemplateTypeMock.name);
    });

    it('should disable send test mail button when acl permission not set', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: undefined,
                contentPlain: undefined,
                // eslint-disable-next-line max-len
                contentHtml: undefined,
                senderName: undefined,
                translated: {
                    subject: 'Your order with {{ salesChannel.name }} is partially paid',
                    contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                    // eslint-disable-next-line max-len
                    contentHtml:
                        '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                    senderName: '{{ salesChannel.name }}',
                },
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeDefined();
    });

    it('should display an error notification when the mail template type is missing', async () => {
        wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        wrapper.vm.mailTemplateRepository.get = jest.fn().mockResolvedValue({
            ...mailTemplateMock,
            mailTemplateType: null,
        });

        await wrapper.vm.loadEntityData();

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: wrapper.vm.$tc('sw-mail-template.general.missingMailTemplateTypeErrorMessage'),
        });

        wrapper.vm.createNotificationError.mockRestore();
    });
});
