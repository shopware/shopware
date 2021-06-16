/**
 * @private
 */
Shopware.Component.register('sw-media-entity-mapper', {
    functional: true,

    render(createElement, context) {
        function mapEntity() {
            const entityMapping = {
                media: 'sw-media-media-item',
                media_folder: 'sw-media-folder-item',
            };
            return entityMapping[context.props.item.getEntityName()];
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
});
