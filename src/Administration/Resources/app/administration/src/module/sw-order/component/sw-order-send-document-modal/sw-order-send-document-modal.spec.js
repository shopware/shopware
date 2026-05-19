/* eslint-disable sw-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import uuid from 'test/_helper_/uuid';
import EntityCollection from 'src/core/data/entity-collection.data';
import Entity from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import { DOCUMENT_MAIL_TEMPLATE_MAPPING } from './index';
import { DOCUMENT_TYPES } from '../../order.types';

/**
 * @sw-package checkout
 */

const mockOrder = {
    id: uuid.get('orderId0'),
    languageId: uuid.get('languageId0'),
    orderCustomer: {
        email: 'test@shopware.com',
        firstName: 'Test',
        lastName: 'Tester',
    },
    salesChannelId: uuid.get('salesChannelId0'),
};

const mockOrderWithoutCustomerName = {
    id: uuid.get('orderId1'),
    languageId: uuid.get('languageId1'),
    orderCustomer: {
        email: 'test@shopware.com',
    },
    salesChannelId: uuid.get('salesChannelId1'),
};

const mockDocuments = [
    {
        config: {
            documentNumber: '1000',
        },
        documentType: {
            name: 'Cancellation invoice',
            technicalName: 'storno',
        },
        documentNumber: 1000,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('storno'),
        deepLinkCode: '12345',
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
        documentA11yMediaFile: { id: '123456', fileExtension: 'html' },
    },
    {
        config: {
            documentNumber: '1001',
        },
        documentType: {
            name: 'Credit note',
            technicalName: 'credit_note',
        },
        documentNumber: 1001,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('credit_note'),
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
    },
    {
        config: {
            documentNumber: '1002',
        },
        documentType: {
            name: 'Invoice note',
            technicalName: 'invoice',
        },
        documentNumber: 1002,
        createdAt: '2024-01-23T14:00:00.000+00:00',
        id: uuid.get('invoice'),
        documentMediaFile: { id: '1235', fileExtension: 'pdf' },
    },
];

const makeDocument = (technicalName) => ({
    id: uuid.get(technicalName),
    documentNumber: 1000,
    documentType: { name: technicalName, technicalName },
    config: { documentNumber: '1000' },
    createdAt: '2024-01-23T14:00:00.000+00:00',
});

const makeMailTemplate = (technicalName, overrides = {}) => ({
    id: uuid.get(technicalName),
    subject: `${technicalName} subject`,
    contentHtml: '',
    mailTemplateType: { name: technicalName, technicalName },
    ...overrides,
});

const mockUnknownDocument = {
    config: {
        documentNumber: '1003',
    },
    documentType: {
        name: 'Cancellation invoice',
        technicalName: 'unknown',
    },
    documentNumber: 1003,
    createdAt: '2024-01-23T14:00:00.000+00:00',
    id: uuid.get('unknown'),
};

const mockMailTemplates = [
    {
        id: uuid.get('cancellation_mail'),
        name: 'Test email 1',
        description: 'Test email description 1',
        mailTemplateType: {
            name: 'Cancellation invoice',
            technicalName: 'cancellation_mail',
        },
        contentHtml: '<div>Cancellation email template content.</div>\n',
        subject: 'Nex document for your order',
        media: new EntityCollection('/mail-template-media', 'mail-template-media', null, null, [
            new Entity(
                'mail-template-media-id',
                'mail-template-media',
                {
                    id: 'mail-template-media-is',
                    languageId: Shopware.Context.api.languageId,
                    media: new Entity('media-id', 'media', { id: 'media-id' }, []),
                },
                [],
            ),
        ]),
    },
    {
        id: uuid.get('delivery_mail'),
        name: 'Test email 2',
        description: 'Test email description 2',
        mailTemplateType: {
            name: 'Delivery note',
            technicalName: 'delivery_mail',
        },
        contentHtml: '<div>Delivery email template content.</div>\n',
        subject: 'Some other template subject',
    },
    {
        id: null,
        name: 'Test email 3',
        description: 'Test email description 3',
        mailTemplateType: {
            name: 'Invoice note',
            technicalName: 'invoice',
        },
        contentHtml: '<div>Delivery email template content.</div>\n',
        subject: 'And another template subject',
    },
    {
        id: uuid.get('personalized_order_mail'),
        name: 'Test email 4',
        description: 'Test email description 4',
        mailTemplateType: {
            name: 'Invoice note',
            technicalName: 'invoice_mail',
            templateData: {
                order: {
                    ...mockOrderWithoutCustomerName,
                    orderCustomer: {
                        email: 'personal@ema.il',
                        firstName: 'Personal',
                        lastName: 'Data',
                    },
                },
            },
        },
        contentHtml: '<div>{{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}}</div>\n',
        subject: 'Personal data from order',
    },
];

const mockRepositoryFactory = (entity, mailTemplates) => {
    if (entity === 'mail_template') {
        return {
            search: jest.fn((criteria) => {
                const typeFilter = criteria?.filters.find(
                    (filter) => filter.field === 'mailTemplateType.technicalName' && !filter.value.includes('|'),
                );

                const filtered = typeFilter
                    ? mailTemplates.filter((template) => template.mailTemplateType?.technicalName === typeFilter.value)
                    : mailTemplates;

                return Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null, filtered, filtered.length));
            }),
            get: jest.fn((value) => Promise.resolve(mailTemplates.filter((mailTemplate) => mailTemplate.id === value)[0])),
        };
    }
    return {};
};

const defaultProps = {
    order: mockOrder,
    document: mockDocuments[0],
};

const replaceTemplateVariables = (template = '', variables = {}) => {
    if (Object.keys(variables).length === 0) {
        return template;
    }

    return template.replace(/\{\{(.*?)}}/g, (match, p1) => {
        const keys = p1.trim().split('.');
        return keys.reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : ''), variables);
    });
};

async function createWrapper(props = defaultProps, sendingSucceds = true, mailTemplates = mockMailTemplates) {
    const previewMailTemplate = jest.fn((mailTemplateId) => {
        const mailTemplate = mailTemplates.find((template) => template.id === mailTemplateId) ?? mailTemplates[0];
        const entities = {
            order: props.order,
            salesChannel: props.order.salesChannel,
        };

        return Promise.resolve({
            contentHtml: {
                content: replaceTemplateVariables(mailTemplate.contentHtml, entities),
            },
        });
    });
    const getDataAndSendMailTemplate = jest.fn(() => {
        return sendingSucceds ? Promise.resolve({ size: 1 }) : Promise.reject();
    });

    return mount(await wrapTestComponent('sw-order-send-document-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-description-list': await wrapTestComponent('sw-description-list'),
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-product-variant-info': true,
                'router-link': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-time-ago': await wrapTestComponent('sw-time-ago'),
            },
            provide: {
                repositoryFactory: {
                    create(entity) {
                        return mockRepositoryFactory(entity, mailTemplates);
                    },
                },
                mailService: {
                    previewMailTemplate,
                    getDataAndSendMailTemplate,
                },
            },
        },
        props,
    });
}

describe('src/module/sw-order/component/sw-order-send-document-modal', () => {
    it('should display the correct order and document information', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const descriptionListElements = wrapper.findAll('.sw-description-list > dd');
        expect(descriptionListElements[0].text()).toBe(String(mockDocuments[0].documentNumber));
        expect(descriptionListElements[1].text()).toBe(String(mockDocuments[0].documentType.name));
        expect(descriptionListElements[2].text()).toBe('23/01/2024, 14:00');

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(
            mockMailTemplates[0].mailTemplateType.name,
        );

        const textFields = wrapper.findAllComponents('.mt-text-field');
        expect(textFields[0].props('modelValue')).toBe(String(mockOrder.orderCustomer.email));
        expect(textFields[1].props('modelValue')).toBe(mockMailTemplates[0].subject);
    });

    it('should display mail template select', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mailTemplateSelect = wrapper.find('.sw-order-send-document-modal__mail-template-select');
        expect(mailTemplateSelect.exists()).toBe(true);

        await mailTemplateSelect.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-result__result-item-description').text()).toBe(mockMailTemplates[0].description);
    });

    it('should truncate mail template description', async () => {
        const wrapper = await createWrapper(defaultProps, true, [
            {
                ...mockMailTemplates[0],
                description: 'swag'.repeat(50),
            },
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection').trigger('click');
        await flushPromises();

        const text = wrapper.find('.sw-select-result__result-item-description').text();
        expect(text).toHaveLength(160);
        expect(text.endsWith('...')).toBe(true);
    });

    it('should display the email content preview from preview endpoint', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.mailService.previewMailTemplate).toHaveBeenCalledWith(
            mockMailTemplates[0].id,
            {
                order: mockOrder.id,
                salesChannel: mockOrder.salesChannelId,
            },
            {
                a11yDocuments: [
                    {
                        documentId: mockDocuments[0].id,
                        deepLinkCode: mockDocuments[0].deepLinkCode,
                        fileExtension: 'html',
                    },
                ],
            },
            mockOrder.salesChannelId,
            true,
            true,
            {
                ...Shopware.Context.api,
                languageId: mockOrder.languageId,
            },
        );

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(mockMailTemplates[0].contentHtml);
    });

    it('should display the email content preview for an order without customer name data', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrderWithoutCustomerName,
        });
        await flushPromises();

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(mockMailTemplates[0].contentHtml);
    });

    it('should replace mail template data with order data', async () => {
        const wrapper = await createWrapper(
            {
                ...defaultProps,
                document: mockDocuments[2],
            },
            true,
            [mockMailTemplates[3]],
        );
        await flushPromises();

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(
            replaceTemplateVariables(mockMailTemplates[3].contentHtml, defaultProps),
        );
    });

    it('should update the email template information when changing the email template', async () => {
        const altCancellationTemplate = makeMailTemplate('cancellation_mail', {
            id: uuid.get('alt-cancellation-mail'),
            contentHtml: '<div>Alt cancellation email template content.</div>\n',
            subject: 'Alt cancellation subject',
        });

        const wrapper = await createWrapper(defaultProps, true, [
            mockMailTemplates[0],
            altCancellationTemplate,
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection-input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(
            altCancellationTemplate.mailTemplateType.name,
        );

        const textFields = wrapper.findAllComponents('.mt-text-field');
        expect(textFields[1].props('modelValue')).toBe(altCancellationTemplate.subject);

        const previewContent = wrapper.find('.sw-order-send-document-modal__email-content');
        expect(previewContent.element.innerHTML).toBe(altCancellationTemplate.contentHtml);
    });

    it('should emit the modal closing message', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentSendModal.labelClose').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not search the mailTemplateRepository for a not configured document type on loading', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrder,
            document: mockUnknownDocument,
        });
        await flushPromises();

        expect(wrapper.vm.mailTemplateRepository.search).toHaveBeenCalledTimes(0);
    });

    it('should not try to set the mailTemplateId, subject and content, when not finding a mail template', async () => {
        const wrapper = await createWrapper({
            ...defaultProps,
            order: mockOrder,
            document: mockDocuments[1],
        });
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[0].text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[1].text()).toBe('');
        expect(wrapper.find('.sw-order-send-document-modal__email-content').text()).toBe('');
    });

    it('should not try to load the subject and content of a mail template with missing mail template', async () => {
        const wrapper = await createWrapper(defaultProps, true, [
            mockMailTemplates[0],
            makeMailTemplate('cancellation_mail', { subject: 'Alt cancellation subject' }),
            makeMailTemplate('cancellation_mail', { id: null }),
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select__selection-input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--2').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[0].text()).toBe('');
        expect(wrapper.findAll('.mt-text-field .mt-field__hint-wrapper')[1].text()).toBe('');
        expect(wrapper.find('.sw-order-send-document-modal__email-content').text()).toBe('');
    });

    it('should send an email without an error', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentCard.labelSendDocument').trigger('click');
        await flushPromises();

        expect(wrapper.vm.mailService.getDataAndSendMailTemplate).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.mailService.getDataAndSendMailTemplate).toHaveBeenLastCalledWith(
            {
                recipients: {
                    [mockOrder.orderCustomer.email]:
                        `${mockOrder.orderCustomer.firstName} ${mockOrder.orderCustomer.lastName}`,
                },
                salesChannelId: mockOrder.salesChannelId,
                mediaIds: [mockMailTemplates[0].media.first().media.id],
                subject: mockMailTemplates[0].subject,
                senderMail: mockMailTemplates[0].senderMail,
                senderName: mockMailTemplates[0].senderName ?? mockMailTemplates[0].translated?.senderName,
                documentIds: [mockDocuments[0].id],
                testMode: false,
                mailTemplateId: mockMailTemplates[0].id,
                entities: {
                    order: mockOrder.id,
                    salesChannel: mockOrder.salesChannelId,
                },
                templateData: {
                    a11yDocuments: [
                        {
                            documentId: mockDocuments[0].id,
                            deepLinkCode: mockDocuments[0].deepLinkCode,
                            fileExtension: 'html',
                        },
                    ],
                },
            },
            {
                ...Shopware.Context.api,
                languageId: mockOrder.languageId,
            },
        );
        expect(wrapper.emitted('document-sent')).toHaveLength(1);
    });

    it('should show an error when the email sending fails', async () => {
        const wrapper = await createWrapper(
            {
                ...defaultProps,
                order: mockOrder,
                document: mockDocuments[0],
            },
            false,
        );
        wrapper.vm.createNotificationError = jest.fn();
        await flushPromises();

        await wrapper.findByText('button', 'sw-order.documentCard.labelSendDocument').trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
        expect(wrapper.emitted('document-sent')).toBeUndefined();
    });

    it('should load the link with a11y documents', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.a11yDocuments).toHaveLength(1);
        expect(wrapper.vm.a11yDocuments[0]).toEqual({
            documentId: mockDocuments[0].id,
            deepLinkCode: '12345',
            fileExtension: 'html',
        });
    });

    describe('auto select mail template by document type', () => {
        it('should have a mapping entry for every DOCUMENT_TYPES value', () => {
            Object.values(DOCUMENT_TYPES).forEach((docType) => {
                expect(DOCUMENT_MAIL_TEMPLATE_MAPPING).toHaveProperty(docType);
            });
        });

        it.each([
            [
                'invoice',
                'invoice_mail',
            ],
            [
                'delivery_note',
                'delivery_mail',
            ],
            [
                'credit_note',
                'credit_note_mail',
            ],
            [
                'storno',
                'cancellation_mail',
            ],
            [
                'zugferd_invoice',
                'invoice_mail',
            ],
            [
                'zugferd_embedded_invoice',
                'invoice_mail',
            ],
            [
                'zugferd_credit_note',
                'credit_note_mail',
            ],
            [
                'zugferd_embedded_credit_note',
                'credit_note_mail',
            ],
            [
                'zugferd_cancellation_invoice',
                'cancellation_mail',
            ],
            [
                'zugferd_embedded_cancellation_invoice',
                'cancellation_mail',
            ],
        ])('should map document type "%s" to mail template type "%s"', (docType, mailTemplateType) => {
            expect(DOCUMENT_MAIL_TEMPLATE_MAPPING[docType]).toBe(mailTemplateType);
        });

        it.each([
            [
                'zugferd_invoice',
                'invoice_mail',
            ],
            [
                'zugferd_embedded_invoice',
                'invoice_mail',
            ],
            [
                'zugferd_credit_note',
                'credit_note_mail',
            ],
            [
                'zugferd_cancellation_invoice',
                'cancellation_mail',
            ],
        ])('should auto select correct template for %s document', async (docType, mailType) => {
            const document = makeDocument(docType);
            const template = makeMailTemplate(mailType);

            const wrapper = await createWrapper({ ...defaultProps, document }, true, [template]);

            await flushPromises();

            expect(wrapper.vm.mailTemplateId).toBe(template.id);
            expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(mailType);
        });
    });
});
