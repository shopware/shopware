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
            icon: 'default-communication-envelope',
            label: 'sw-flow.actions.mailSend'
        };

        let actionTitle = service.getActionTitle(ACTION.MAIL_SEND);

        expect(actionTitle).toEqual(expected);

        expected = {
            value: ACTION.SET_ORDER_STATE,
            icon: 'default-shopping-plastic-bag',
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
            icon: 'default-action-tags',
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
});
