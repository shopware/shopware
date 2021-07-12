const { Application } = Shopware;

Application.addServiceProvider('flowBuilderService', () => {
    return flowBuilderService();
});

export default function flowBuilderService() {
    const $icon = {
        addTag: 'default-action-tags',
        removeTag: 'default-action-tags',
        mailSend: 'default-communication-envelope',
        setOrderState: 'default-shopping-plastic-bag',
        generateDocument: 'default-documentation-file',
        callWebhook: 'default-web-link',
        stopFlow: 'default-basic-x-circle',
    };

    const $labelSnippet = {
        addTag: 'sw-flow.actions.addTag',
        removeTag: 'sw-flow.actions.removeTag',
        mailSend: 'sw-flow.actions.mailSend',
        setOrderState: 'sw-flow.actions.setOrderState',
        generateDocument: 'sw-flow.actions.generateDocument',
        callWebhook: 'sw-flow.actions.callWebhook',
        stopFlow: 'sw-flow.actions.stopFlow',
    };

    return {
        getActionTitle,
        getActionModalName,
    };

    function getActionTitle(actionName) {
        if (!actionName) {
            return null;
        }

        if (actionName.includes('tag')) {
            if (actionName.includes('add')) {
                return {
                    value: actionName,
                    icon: $icon.addTag,
                    label: $labelSnippet.addTag,
                };
            }

            if (actionName.includes('remove')) {
                return {
                    value: actionName,
                    icon: $icon.removeTag,
                    label: $labelSnippet.removeTag,
                };
            }
        }

        let keyName = '';
        actionName.split('.').forEach((key, index) => {
            if (!index) {
                return;
            }

            if (index === 1) {
                keyName = key;
                return;
            }

            keyName = keyName + key.charAt(0).toUpperCase() + key.slice(1);
        });

        return {
            value: actionName,
            icon: $icon[keyName],
            label: $labelSnippet[keyName],
        };
    }

    function getActionModalName(actionName) {
        if (!actionName) {
            return '';
        }

        return `${actionName.replace(/\./g, '-').replace('action', 'sw-flow')}-modal`;
    }
}
