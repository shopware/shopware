import FlowBuilderService from 'src/module/sw-flow/service/flow-builder.service';
import { ACTION } from 'src/module/sw-flow/constant/flow.constant';

describe('module/sw-flow/service/flow-builder.service.js', () => {
    const service = new FlowBuilderService();
    const data = {
        appActions: [{
            label: 'Telegram send message',
            name: 'telegram.send.message',
            swIcon: 'default-communication-speech-bubbles',
            requirements: ['customerAware', 'orderAware'],
            config: [
                {
                    name: 'password',
                    label: {},
                    type: 'password',
                },
                {
                    name: 'singleSelect',
                    label: {},
                    type: 'single-select',
                    options: ['2', '3'],
                },
                {
                    name: 'datetime',
                    label: {},
                    type: 'datetime',
                },
                {
                    name: 'colorpicker',
                    label: {},
                    type: 'colorpicker',
                },
            ],
        }],
        customerGroups: [
            {
                id: '123',
                translated: {
                    name: 'customer name',
                },
            },
        ],
        customFieldSets: [
            {
                id: '123',
                translated: {
                    name: 'customer name',
                },
                config: {
                    label: 'customFieldSets',
                },
            },
        ],
        customFields: [
            {
                id: '123',
                translated: {
                    name: 'customer name',
                },
                config: {
                    label: 'customFieldSets',
                },
            },
        ],
        stateMachineState: [
            {
                technicalName: 'order',
                stateMachine: {
                    technicalName: 'order.state',
                },
                translated: {
                    name: 'translated',
                },
            },
            {
                technicalName: 'order_delivery',
                stateMachine: {
                    technicalName: 'order_delivery.state',
                },
                translated: {
                    name: 'translated',
                },
            },
            {
                technicalName: 'order_transaction',
                stateMachine: {
                    technicalName: 'order_transaction.state',
                },
                translated: {
                    name: 'translated',
                },
            },
        ],
        documentTypes: [
            {
                technicalName: 'mail',
                translated: {
                    name: 'translated',
                },
            },
        ],
        mailTemplates: [{
            id: 'mailTemplate_id',
            mailTemplateType: {
                name: 'name',
                description: 'description',
            },
        }],
    };

    const translator = {
        $tc: (snippet) => {
            return snippet;
        },
        getInlineSnippet: (snippet) => {
            return snippet;
        },
    };

    it('should have the correct modal name for action name', async () => {
        const expected = 'sw-flow-generate-document-modal';
        const modalName = service.getActionModalName(ACTION.GENERATE_DOCUMENT);

        expect(modalName).toEqual(expected);
    });

    it('should get action title correctly based on action name', async () => {
        let expected = {
            value: ACTION.MAIL_SEND,
            icon: 'regular-envelope',
            label: 'sw-flow.actions.mailSend',
        };

        let actionTitle = service.getActionTitle(ACTION.MAIL_SEND);

        expect(actionTitle).toEqual(expected);

        expected = {
            value: ACTION.SET_ORDER_STATE,
            icon: 'regular-shopping-bag-alt',
            label: 'sw-flow.actions.setOrderState',
        };

        actionTitle = service.getActionTitle(ACTION.SET_ORDER_STATE);

        expect(actionTitle).toEqual(expected);
    });

    it('should have the correct action type for action name', async () => {
        const expected = 'action.add.entity.tag';
        const modalName = service.mapActionType(ACTION.ADD_ORDER_TAG);

        expect(modalName).toEqual(expected);
    });

    it('should get action title correctly based on duplicated action', async () => {
        const expected = {
            value: ACTION.ADD_ORDER_TAG,
            icon: 'regular-tag',
            label: 'sw-flow.actions.addTag',
        };

        const actionTitle = service.getActionTitle(ACTION.ADD_ORDER_TAG);

        expect(actionTitle).toEqual(expected);
    });

    it('should get available entities correctly based on action name and actions list', async () => {
        const actions = [
            { name: 'action.add.order.tag', requirements: ['Shopware\\Core\\Framework\\Event\\OrderAware'], extensions: [] },
            { name: 'action.add.customer.tag', requirements: ['Shopware\\Core\\Framework\\Event\\CustomerAware'], extensions: [] },
            { name: 'action.remove.customer.tag', requirements: ['Shopware\\Core\\Framework\\Event\\CustomerAware'], extensions: [] },
            { name: 'action.remove.order.tag', requirements: ['Shopware\\Core\\Framework\\Event\\OrderAware'], extensions: [] },
            { name: 'action.mail.send', requirements: ['Shopware\\Core\\Framework\\Event\\MailAware'], extensions: [] },
            { name: 'action.stop.flow', requirements: [], extensions: [] },
        ];

        const allowedAware = [
            'Shopware\\Core\\Framework\\Event\\SalesChannelAware',
            'Shopware\\Core\\Framework\\Event\\OrderAware',
            'Shopware\\Core\\Framework\\Event\\MailAware',
            'Shopware\\Core\\Framework\\Event\\CustomerAware',
        ];

        const expected = [
            { label: 'Order', value: 'order' },
            { label: 'Customer', value: 'customer' },
        ];

        const entities = service.getAvailableEntities(ACTION.ADD_ORDER_TAG, actions, allowedAware, ['tags']);

        expect(entities).toEqual(expected);
    });

    it('should be able to rearrange array objects', async () => {
        const sequences = [
            { id: '900a915617054a5b8acbfda1a35831fa', parentId: 'd2b3a82c22284566b6a56fb47d577bfd' },
            { id: 'eb342595680d42edbf05e8a953b70fc8', parentId: '944dd8656af44ab982598edb6ad41d58' },
            { id: 'aa25ec634f474d87a5598b6a90d038ec', parentId: 'f1beccf9c40244e6ace2726d2afc476c' },
            { id: '944dd8656af44ab982598edb6ad41d58', parentId: 'e4b79d717a684f589257ece332504b96' },
            { id: 'd2b3a82c22284566b6a56fb47d577bfd', parentId: null },
            { id: 'f1beccf9c40244e6ace2726d2afc476c', parentId: '900a915617054a5b8acbfda1a35831fa' },
            { id: 'c81366118ba64359895bb412602ef8a8', parentId: null },
            { id: 'e4b79d717a684f589257ece332504b96', parentId: null },
        ];

        const result = service.rearrangeArrayObjects(sequences);
        result.forEach(item => {
            delete item.children;
        });

        expect(result).toEqual([
            { id: 'd2b3a82c22284566b6a56fb47d577bfd', parentId: null },
            { id: '900a915617054a5b8acbfda1a35831fa', parentId: 'd2b3a82c22284566b6a56fb47d577bfd' },
            { id: 'f1beccf9c40244e6ace2726d2afc476c', parentId: '900a915617054a5b8acbfda1a35831fa' },
            { id: 'aa25ec634f474d87a5598b6a90d038ec', parentId: 'f1beccf9c40244e6ace2726d2afc476c' },
            { id: 'c81366118ba64359895bb412602ef8a8', parentId: null },
            { id: 'e4b79d717a684f589257ece332504b96', parentId: null },
            { id: '944dd8656af44ab982598edb6ad41d58', parentId: 'e4b79d717a684f589257ece332504b96' },
            { id: 'eb342595680d42edbf05e8a953b70fc8', parentId: '944dd8656af44ab982598edb6ad41d58' },
        ]);
    });

    it('should be able to return empty string with empty action name', async () => {
        const sequence = {
            actionName: '',
            config: {},
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('');
    });

    it('should be able to show description of app action', async () => {
        const sequence = {
            actionName: 'telegram.send.message',
            config: {
                bool: true,
                checkbox: true,
                colorpicker: '#c98888',
                content: 'Hello',
                date: '2023-03-25T00:00:00.000Z',
                datetime: '2023-03-23T12:00:00.000Z',
                float: 5,
                int: 1000,
                multiSelect: ['2', '3', '5'],
                password: 'shopware',
                singleSelect: '3',
                textEditor: 'editor',
                textarea: 'area',
                url: 'https://google.com',
            },
        };

        const description = service.getActionDescriptions(data, sequence, translator);
        const keyFields = Object.keys(sequence.config);
        keyFields.forEach(key => {
            expect(description.includes(key)).toBeTruthy();
        });
    });

    it('should be able to show stop flow description', () => {
        const sequence = {
            actionName: 'action.stop.flow',
            config: {},
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('sw-flow.actions.textStopFlowDescription');
    });

    it('should be able to show customer status description', () => {
        const sequence = {
            actionName: 'action.change.customer.status',
            config: {},
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('sw-flow.modals.customerStatus.inactive');
    });

    it('should be able to show customer affiliate description', () => {
        const sequence = {
            actionName: 'action.add.customer.affiliate.and.campaign.code',
            config: {
                affiliateCode: {
                    upsert: 'a',
                    value: 'value',
                },
                campaignCode: {
                    upsert: 'a',
                    value: 'value',
                },
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('sw-flow.actions.labelTo<br>sw-flow.actions.labelAffiliateCode<br>sw-flow.actions.labelCampaignCode');
    });

    it('should be able to show change customer status description', () => {
        const sequence = {
            actionName: 'action.change.customer.group',
            config: {
                customerGroupId: '123',
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('customer name');
    });

    it('should be able to show customer custom field description', () => {
        const sequence = {
            actionName: 'action.set.customer.custom.field',
            config: {
                customFieldSetId: '123',
                customFieldId: '123',
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toContain('sw-flow.actions.labelCustomFieldSet<br>sw-flow.actions.labelCustomField');
    });

    it('should be able to show order status description', () => {
        const sequence = {
            actionName: 'action.set.order.state',
            config: {
                order: 'order',
                order_delivery: 'order_delivery',
                order_transaction: 'order_transaction',
            },
        };

        const render = `sw-flow.modals.status.labelOrderStatus: translated<br>
                sw-flow.modals.status.labelDeliveryStatus: translated
            <br>sw-flow.modals.status.labelPaymentStatus: translated<br>sw-flow.modals.status.forceTransition: global.default.no`;
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toContain(render);
    });

    it('should be able to show generate document description', () => {
        const sequence = {
            actionName: 'action.generate.document',
            config: {
                documentTypes: [{
                    documentType: 'mail',
                }],
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('translated');
    });

    it('should be able to send mail flow description', () => {
        const sequence = {
            actionName: 'action.mail.send',
            config: {
                mailTemplateId: 'mailTemplate_id',
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toBe('sw-flow.actions.labelTemplate');
    });

    it('should be able to default action flow description', () => {
        const sequence = {
            actionName: 'action.default',
            config: {
                addEntityTag: 'sw-flow.actions.addTag',
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toContain('sw-flow.actions.addTag');
    });

    it('should be able to render granted download access action description', () => {
        const sequence = {
            actionName: 'action.grant.download.access',
            config: {
                value: true,
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toContain('sw-flow.actions.downloadAccessLabel.granted');
    });

    it('should be able to render revoked download access action description', () => {
        const sequence = {
            actionName: 'action.grant.download.access',
            config: {
                value: false,
            },
        };
        const description = service.getActionDescriptions(data, sequence, translator);
        expect(description).toContain('sw-flow.actions.downloadAccessLabel.revoked');
    });
});
