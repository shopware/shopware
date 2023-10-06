import './sw-media-quickinfo-metadata-item.scss';
import { h } from 'vue';

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    functional: true,

    render(createElement, context) {
        // Vue 3 render method (which can't use createElement and context)
        if (window._features_.VUE3) {
            const title = h(
                'dt',
                {
                    class: [
                        context.data.class,
                        {
                            'sw-media-quickinfo-metadata-item__term': true,
                        },
                    ],
                },
                `${context.props.labelName}:`,
            );

            const description = h(
                'dd',
                {
                    class: [
                        context.data.class,
                        {
                            'sw-media-quickinfo-metadata-item__description': true,
                        },
                    ],
                },
                context.children.default(),
            );

            return [title, description];
        }

        /**
         * Vue2 render method
         */
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
