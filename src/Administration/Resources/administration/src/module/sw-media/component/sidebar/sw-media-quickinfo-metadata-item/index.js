import { Component } from 'src/core/shopware';
import './sw-media-quickinfo-metadata-item.less';

Component.register('sw-media-quickinfo-metadata-item', {
    functional: true,

    props: {
        labelName: {
            required: true,
            type: String
        },
        truncated: {
            required: false,
            default: true,
            type: Boolean
        }
    },

    render(createElement, context) {
        const title = createElement(
            'dt',
            {
                class: 'sw-media-quickinfo-metadata-item__term'
            },
            [
                `${context.props.labelName}:`
            ]
        );

        const description = createElement(
            'dd',
            {
                class: [
                    context.data.staticClass,
                    {
                        'sw-media-quickinfo-metadata-item__description': true,
                        'is--truncated': context.props.truncated
                    }
                ]
            },
            [
                context.children
            ]
        );

        return [title, description];
    }
});
