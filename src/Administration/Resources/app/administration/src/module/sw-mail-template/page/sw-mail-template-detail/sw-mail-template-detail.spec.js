/**
 * @sw-package after-sales
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

const repositoryMockFactory = (entity) => {
    if (entity === 'sales_channel') {
        return {
            search: () => Promise.resolve({}),
            get: () =>
                Promise.resolve({
                    id: '1a2b3c',
                    name: 'Storefront',
                    mailHeaderFooter: {
                        translated: {
                            headerHtml: '<div>Header</div>',
                            footerHtml: '<div>Footer</div>',
                            headerPlain: 'Header plain',
                            footerPlain: 'Footer plain',
                        },
                    },
                    languages: new EntityCollection(
                        '/language',
                        'language',
                        null,
                        {},
                        [
                            {
                                id: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                            },
                        ],
                        1,
                    ),
                }),
        };
    }
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

function createSimulationResponse(mailTemplateContent) {
    return Object.fromEntries(
        Object.entries(mailTemplateContent).map(
            ([
                key,
                content,
            ]) => [
                key,
                {
                    type: 'success',
                    content,
                },
            ],
        ),
    );
}

async function createWrapper(privileges = []) {
    const simulateMailTemplate = jest.fn((mailTemplateContent) =>
        Promise.resolve(createSimulationResponse(mailTemplateContent)),
    );

    return mount(await wrapTestComponent('sw-mail-template-detail', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: repositoryMockFactory,
                },
                mailService: {
                    simulateMailTemplate,
                    sendMailTemplate: jest.fn(() => Promise.resolve({ size: 1 })),
                    loadAvailableVariables: jest.fn(() => Promise.resolve({})),
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
                businessEventService: {
                    getBusinessEvents: jest.fn(() =>
                        Promise.resolve([
                            {
                                name: 'checkout.order.placed',
                                aware: ['mailAware'],
                                data: {},
                            },
                        ]),
                    ),
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
                'mt-card': {
                    template: '<div><slot></slot></div>',
                },
                'mt-button': {
                    props: ['disabled'],
                    template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                },
                'mt-text-field': {
                    props: ['disabled'],
                    template: '<input type="text" :disabled="disabled" />',
                },
                'mt-textarea': {
                    props: ['disabled'],
                    template: '<textarea :disabled="disabled"></textarea>',
                },
                'mt-select': {
                    template: '<div><slot name="hint"></slot></div>',
                },
                'mt-banner': {
                    template: '<div><slot></slot></div>',
                },
                'mt-icon': true,
                'sw-vnode-renderer': await wrapTestComponent('sw-vnode-renderer', { sync: true }),
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-entity-single-select': true,
                'sw-entity-multi-select': true,
                'sw-textarea-field': true,
                'sw-modal': true,
                'sw-mail-template-preview-modal': {
                    template: '<div class="sw-mail-template-preview-modal-stub"></div>',
                },
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
                'sw-tree': await wrapTestComponent('sw-tree'),
                'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                'sw-tree-input-field': await wrapTestComponent('sw-tree-input-field'),
                'sw-confirm-field': true,
                'sw-loader': true,
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
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should be able to add an item to the attachment', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ mailTemplateMedia: [] });
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.mailTemplate.media.some((media) => media.mediaId === mailTemplateMediaMock.id)).toBeTruthy();
    });

    it('should be unable to add an item to the attachment exist this item', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.createNotificationInfo = jest.fn();
        wrapper.vm.onAddItemToAttachment(mailTemplateMediaMock);

        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-mail-template.list.errorMediaItemDuplicated',
        });

        wrapper.vm.createNotificationInfo.mockRestore();
    });

    it('should be success to get media columns', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.getMediaColumns()).toHaveLength(1);
    });

    it('should be success to upload an attachment', async () => {
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper(['mail_templates.editor']);
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
        const wrapper = await createWrapper();

        await wrapper.setData({ mailTemplate: mailTemplateTypeMock });

        const previewButton = wrapper.find('.sw-mail-template-detail__show-preview-sidebar button');

        expect(previewButton.attributes().disabled).toBeDefined();
    });

    it('should render a sales channel select in the preview sidebar', async () => {
        const wrapper = await createWrapper();

        const previewSalesChannelSelect = wrapper.find('[name="sw-field--previewSalesChannelId"]');

        expect(previewSalesChannelSelect.exists()).toBe(true);
    });

    it('should use one shared sales channel value for send and preview', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            testMailSalesChannelId: 'sales-channel-id-1',
        });

        expect(wrapper.vm.testMailSalesChannelId).toBe('sales-channel-id-1');
    });

    it('should send the selected preview sales channel id when simulating', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            testMailSalesChannelId: 'sales-channel-id',
            triggerEvents: [
                {
                    name: 'checkout.order.placed',
                },
            ],
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        await flushPromises();
        await wrapper.vm.simulateMailPreview();

        expect(wrapper.vm.mailService.simulateMailTemplate).toHaveBeenCalledWith(
            expect.objectContaining({
                subject: undefined,
                senderName: undefined,
                contentHtml: undefined,
                contentPlain: undefined,
                headerHtml: '<div>Header</div>',
                footerHtml: '<div>Footer</div>',
                headerPlain: 'Header plain',
                footerPlain: 'Footer plain',
            }),
            'checkout.order.placed',
            'sales-channel-id',
        );
    });

    it('should not be able to send test mails when values are missing', async () => {
        const wrapper = await createWrapper();

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
        const wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();

        await sendTestMail.trigger('click');
        await flushPromises();

        expect(wrapper.vm.mailService.simulateMailTemplate).toHaveBeenCalledWith(
            {
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                senderName: '{{ salesChannel.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                headerHtml: '<div>Header</div>',
                footerHtml: '<div>Footer</div>',
                headerPlain: 'Header plain',
                footerPlain: 'Footer plain',
            },
            'checkout.order.placed',
            '1a2b3c',
        );
        expect(wrapper.vm.mailService.sendMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            'foo@bar.com',
            expect.objectContaining({
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                senderName: '{{ salesChannel.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
            }),
            expect.anything(),
            '1a2b3c',
            true,
            [],
            {},
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );
        expect(wrapper.vm.mailPreview).toBeNull();
    });

    it('should be able to send test mails when only inherited values are filled', async () => {
        const wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: undefined,
                contentPlain: undefined,
                contentHtml: undefined,
                senderName: undefined,
                translated: {
                    subject: 'Your order with {{ salesChannel.name }} is partially paid',
                    contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                    contentHtml:
                        '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                    senderName: '{{ salesChannel.name }}',
                },
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();

        await sendTestMail.trigger('click');
        await flushPromises();

        expect(wrapper.vm.mailService.sendMailTemplate).toHaveBeenCalledWith(
            'foo@bar.com',
            'foo@bar.com',
            expect.objectContaining({
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                senderName: '{{ salesChannel.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
            }),
            expect.anything(),
            '1a2b3c',
            true,
            [],
            {},
            undefined,
            '6666673yd1ssd299si1d837dy1ud628',
        );
    });

    it('should copy variables to clipboard', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: () => Promise.resolve(),
            },
        });

        const clipboardSpy = jest.spyOn(navigator.clipboard, 'writeText');

        const wrapper = await createWrapper();
        const spyOnCopyVariable = jest.spyOn(wrapper.vm, 'onCopyVariable');

        await wrapper.vm.onCopyVariable('order.orderNumber');

        await flushPromises();

        expect(spyOnCopyVariable).toHaveBeenCalled();
        expect(clipboardSpy).toHaveBeenCalled();
    });

    it('should load variable schemas from the backend', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.mailService.loadAvailableVariables = jest.fn(() =>
            Promise.resolve({
                orderNumber: {
                    fieldName: 'orderNumber',
                    hasChildren: false,
                },
            }),
        );

        await wrapper.setData({
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        wrapper.vm.loadAvailableVariables('order');
        await flushPromises();

        expect(wrapper.vm.mailService.loadAvailableVariables).toHaveBeenCalledWith('checkout.order.placed', 'order');
        expect(wrapper.vm.availableVariables['order.orderNumber']).toEqual({
            id: 'order.orderNumber',
            schema: 'order.orderNumber',
            name: 'orderNumber',
            childCount: 0,
            parentId: 'order',
            afterId: null,
        });
    });

    it('should send the original template content to the simulator when sending a test mail', async () => {
        const wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.deliveries.first.stateMachineState.translated.name }} {{ order.deliveries.at(1).trackingCodes.0 }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.find('.sw-mail-template-detail__send-test-mail');
        await sendTestMail.trigger('click');
        await flushPromises();

        expect(wrapper.vm.mailService.simulateMailTemplate).toHaveBeenCalledWith(
            {
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                senderName: '{{ salesChannel.name }}',
                contentHtml:
                    '{{ order.deliveries.first.stateMachineState.translated.name }} {{ order.deliveries.at(1).trackingCodes.0 }},<br/><br/>',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                headerHtml: '<div>Header</div>',
                footerHtml: '<div>Footer</div>',
                headerPlain: 'Header plain',
                footerPlain: 'Footer plain',
            },
            'checkout.order.placed',
            '1a2b3c',
        );
    });

    it('should normalize preview errors when simulation returns an invalid template result', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
                mailTemplateTypeId: 'typeId',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        wrapper.vm.mailService.simulateMailTemplate = jest.fn(() =>
            Promise.resolve({
                subject: {
                    type: 'success',
                    content: 'Rendered subject',
                },
                senderName: {
                    type: 'success',
                    content: 'Rendered sender',
                },
                contentPlain: {
                    type: 'success',
                    content: 'Rendered plain',
                },
                contentHtml: {
                    type: 'error',
                    content: 'Twig syntax error: unexpected end of template.',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'unexpected end of template.',
                },
            }),
        );

        await wrapper.vm.onClickShowPreview();

        expect(wrapper.vm.mailPreview.contentHtml.errorTitle).toBe('Twig syntax error');
        expect(wrapper.vm.mailPreview.contentHtml.errorMessage).toBe('unexpected end of template.');
    });

    it('should identify header and footer simulation errors separately in the preview', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Subject',
                contentPlain: 'Body plain',
                contentHtml: '<div>Body html</div>',
                senderName: 'Sender',
                mailTemplateTypeId: 'typeId',
            },
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        wrapper.vm.mailService.simulateMailTemplate = jest.fn(() =>
            Promise.resolve({
                subject: {
                    type: 'success',
                    content: 'Rendered subject',
                },
                senderName: {
                    type: 'success',
                    content: 'Rendered sender',
                },
                headerPlain: {
                    type: 'success',
                    content: 'Rendered header plain',
                },
                contentPlain: {
                    type: 'success',
                    content: 'Rendered body plain',
                },
                footerPlain: {
                    type: 'error',
                    content: 'Twig syntax error: plain footer failed.',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'plain footer failed.',
                },
                headerHtml: {
                    type: 'error',
                    content: 'Twig syntax error: html header failed.',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'html header failed.',
                },
                contentHtml: {
                    type: 'success',
                    content: 'Rendered body html',
                },
                footerHtml: {
                    type: 'success',
                    content: 'Rendered footer html',
                },
            }),
        );

        await wrapper.vm.onClickShowPreview();

        expect(wrapper.vm.mailPreview.headerHtml.errorTitle).toBe('Twig syntax error');
        expect(wrapper.vm.mailPreview.headerHtml.errorMessage).toBe('html header failed.');
        expect(wrapper.vm.mailPreview.footerPlain.errorTitle).toBe('Twig syntax error');
        expect(wrapper.vm.mailPreview.footerPlain.errorMessage).toBe('plain footer failed.');
    });

    it('should reset preview when simulation request fails', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
                mailTemplateTypeId: 'typeId',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });
        wrapper.vm.mailService.simulateMailTemplate = jest.fn(() => Promise.reject(new SyntaxValidationTemplateError()));

        await wrapper.vm.onClickShowPreview();

        expect(wrapper.vm.mailPreview).toBeNull();
    });

    it('should get error notification if using test mail function with invalid template', async () => {
        const wrapper = await createWrapper(['api_send_email']);

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();
        wrapper.vm.mailService.simulateMailTemplate = jest.fn(() =>
            Promise.resolve({
                subject: {
                    type: 'error',
                    content: 'Twig syntax error: unexpected end of template.',
                },
            }),
        );

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await sendTestMail.trigger('click');
        await flushPromises();

        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-mail-template.general.notificationGeneralSyntaxValidationErrorMessage',
        });
        expect(wrapper.vm.mailService.sendMailTemplate).not.toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
        await flushPromises();
    });

    it('should render mail template type name as language info description', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ $refs: refsMock });
        await flushPromises();

        expect(wrapper.find('sw-language-info-stub').exists()).toBe(true);
        expect(wrapper.find('sw-language-info-stub').attributes('entity-description')).toBe(mailTemplateTypeMock.name);
    });

    it('should disable send test mail button when acl permission not set', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: undefined,
                contentPlain: undefined,
                contentHtml: undefined,
                senderName: undefined,
                translated: {
                    subject: 'Your order with {{ salesChannel.name }} is partially paid',
                    contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                    contentHtml:
                        '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                    senderName: '{{ salesChannel.name }}',
                },
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeDefined();
    });

    it('should display an error notification when the mail template type is missing', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        wrapper.vm.mailTemplateRepository.get = jest.fn().mockResolvedValue({
            ...mailTemplateMock,
            mailTemplateType: null,
        });

        await wrapper.vm.loadEntityData();

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: wrapper.vm.$t('sw-mail-template.general.missingMailTemplateTypeErrorMessage'),
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should display an notification if content language is not assigned to selected sales channel', async () => {
        const wrapper = await createWrapper(['api_send_email']);
        const originalLanguageId = Shopware.Context.api.languageId;

        await wrapper.setData({
            mailTemplate: {
                ...mailTemplateTypeMock,
                subject: 'Your order with {{ salesChannel.name }} is partially paid',
                contentPlain: 'the status of your order at {{ salesChannel.translated.name }}',
                contentHtml:
                    '{{ order.orderCustomer.salutation.translated.letterName }} {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/><br/>',
                senderName: '{{ salesChannel.name }}',
            },
            testerMail: 'foo@bar.com',
            isLoading: false,
            testMailSalesChannelId: '1a2b3c',
            triggerEvent: {
                name: 'checkout.order.placed',
            },
        });

        const sendTestMail = wrapper.findComponent('.sw-mail-template-detail__send-test-mail');

        expect(sendTestMail.attributes().disabled).toBeUndefined();

        Shopware.Context.api.languageId = 'foo';
        await sendTestMail.trigger('click');

        expect(wrapper.vm.showLanguageNotAssignedToSalesChannelWarning).toBeTruthy();

        Shopware.Context.api.languageId = originalLanguageId;
    });

    it('should not render copy icon if variable has children', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            triggerEvent: {
                name: 'checkout.order.placed',
            },
            availableVariables: {
                order: {
                    id: 'order',
                    schema: 'order',
                    name: 'order',
                    childCount: 2,
                    parentId: null,
                    afterId: null,
                },
                'order.orderNumber': {
                    id: 'order.orderNumber',
                    schema: 'order.orderNumber',
                    name: 'orderNumber',
                    childCount: 0,
                    parentId: 'order',
                    afterId: null,
                },
                'order.price': {
                    id: 'order.price',
                    schema: 'order.price',
                    name: 'price',
                    childCount: 1,
                    parentId: 'order',
                    afterId: null,
                },
                'order.price.totalPrice': {
                    id: 'order.price.totalPrice',
                    schema: 'order.price.totalPrice',
                    name: 'totalPrice',
                    childCount: 0,
                    parentId: 'order.price',
                    afterId: null,
                },
            },
        });
        await flushPromises();

        await wrapper.find('[aria-label="order"] .sw-tree-item__toggle').trigger('click');
        await flushPromises();

        await wrapper.find('[aria-label="price"] .sw-tree-item__toggle').trigger('click');
        await flushPromises();

        const labels = wrapper.findAll('.sw-tree-item__label');
        expect(labels).toHaveLength(4);

        expect(labels.at(0).text()).toBe('order');
        expect(labels.at(1).text()).toBe('orderNumber');
        expect(labels.at(2).text()).toBe('price');
        expect(labels.at(3).text()).toBe('totalPrice');

        const copyIcons = wrapper.findAll('.sw-mail-template-detail__copy_icon');
        expect(copyIcons).toHaveLength(2);
    });
});
