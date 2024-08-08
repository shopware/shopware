import './sw-media-quickinfo-metadata-item.scss';
import { h } from 'vue';

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    functional: true,

    compatConfig: Shopware.compatConfig,

    render(createElement, context) {
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
    },

    props: {
        labelName: {
            required: true,
            type: String,
        },
    },
};
