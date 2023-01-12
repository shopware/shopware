import FlowBuilderService from 'src/module/sw-flow/service/flow-builder.service';
import { ACTION } from 'src/module/sw-flow/constant/flow.constant';

describe('module/sw-flow/service/flow-builder.service.js', () => {
    const service = new FlowBuilderService();

    it('should have the correct modal name for action name', async () => {
        const expected = 'sw-flow-generate-document-modal';
        const modalName = service.getActionModalName(ACTION.GENERATE_DOCUMENT);

        expect(modalName).toEqual(expected);
    });

    it('should get action title correctly based on action name', async () => {
        let expected = {
            value: ACTION.MAIL_SEND,
            icon: 'regular-envelope',
            label: 'sw-flow.actions.mailSend'
        };

        let actionTitle = service.getActionTitle(ACTION.MAIL_SEND);

        expect(actionTitle).toEqual(expected);

        expected = {
            value: ACTION.SET_ORDER_STATE,
            icon: 'regular-shopping-bag-alt',
            label: 'sw-flow.actions.setOrderState'
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
            label: 'sw-flow.actions.addTag'
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
            { name: 'action.stop.flow', requirements: [], extensions: [] }
        ];

        const allowedAware = [
            'Shopware\\Core\\Framework\\Event\\SalesChannelAware',
            'Shopware\\Core\\Framework\\Event\\OrderAware',
            'Shopware\\Core\\Framework\\Event\\MailAware',
            'Shopware\\Core\\Framework\\Event\\CustomerAware'
        ];

        const expected = [
            { label: 'Order', value: 'order' },
            { label: 'Customer', value: 'customer' }
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
});
