import { ACTION } from '../constant/flow.constant';

const { Application } = Shopware;

Application.addServiceProvider('flowService', () => {
    return {
        getActionModalName,
    };
});

function getActionModalName(actionName) {
    if (!actionName) return '';

    let componentName = '';
    switch (actionName) {
        case ACTION.ADD_RULE: {
            componentName = 'sw-flow-create-rule-modal';
            break;
        }

        default: {
            componentName = '';
            break;
        }
    }

    return componentName;
}
