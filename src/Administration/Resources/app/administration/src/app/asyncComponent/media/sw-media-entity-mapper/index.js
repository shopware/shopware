/**
 * @private
 * @package content
 */
export default {
    functional: true,

    render(createElement, context) {
        function mapEntity() {
            const entityMapping = {
                media: 'sw-media-media-item',
                media_folder: 'sw-media-folder-item',
            };
            return entityMapping[context.props.item.getEntityName()];
        }

        if (window._features_?.VUE3) {
            Object.assign(context.data, context.props);

            return createElement(
                mapEntity(),
                context.data,
                context.slots().default,
            );
        }

        Object.assign(context.data.attrs, context.props);
        return createElement(
            mapEntity(),
            context.data,
            context.slots().default,
        );
    },

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return !!value.getEntityName();
            },
        },
    },
};
