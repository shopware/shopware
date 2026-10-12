Shopware.Component.register('sw-image', {
    props: {
        emptyIcon: { type: String, deprecated: { version: 'v6.8.0.0', comment: 'Use "placeholderIcon" instead.' } },
        emptyImagePath: { type: String, deprecated: 'v6.8.0.0' },
        placeholderIcon: { type: String },
        modelValue: { type: String, deprecated: { version: 'v6.8.0.0', comment: 'Use "value" instead.' } },
    },
});
