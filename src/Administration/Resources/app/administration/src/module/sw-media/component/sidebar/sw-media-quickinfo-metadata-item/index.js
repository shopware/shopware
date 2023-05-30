import './sw-media-quickinfo-metadata-item.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    functional: true,

    render(createElement, context) {
        const title = createElement(
            'dt',
            {
                class: [
                    context.data.staticClass,
                    {
                        'sw-media-quickinfo-metadata-item__term': true,
                        ...context.data.class,
                    },
                ],
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
};
