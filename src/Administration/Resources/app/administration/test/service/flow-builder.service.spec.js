import FlowBuilderService from 'src/module/sw-flow/service/flow-builder.service';
import { ACTION } from 'src/module/sw-flow/constant/flow.constant';

describe('module/sw-flow/service/rule-condition.service.js', () => {
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
});
