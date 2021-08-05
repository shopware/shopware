import './sw-media-quickinfo-metadata-item.scss';

Shopware.Component.register('sw-media-quickinfo-metadata-item', {
    functional: true,

    render(createElement, context) {
        const title = createElement(
            'dt',
            {
                class: 'sw-media-quickinfo-metadata-item__term',
            },
            [
                `${context.props.labelName}:`,
            ],
        );

        const description = createElement(
            'dd',
            {
                class: [
                    context.data.staticClass,
                    {
                        'sw-media-quickinfo-metadata-item__description': true,
                    },
                ],
            },
            [
                context.children,
            ],
        );

        return [title, description];
    },

    props: {
        labelName: {
            required: true,
            type: String,
        },
    },
});
