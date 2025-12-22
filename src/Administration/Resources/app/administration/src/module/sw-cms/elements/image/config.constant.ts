const IMAGE_DEFAULT_CONFIG = {
    media: {
        source: 'static',
        value: null,
        required: true,
        entity: {
            name: 'media',
        },
    },
    displayMode: {
        source: 'static',
        value: 'standard',
    },
    url: {
        source: 'static',
        value: null,
    },
    ariaLabel: {
        source: 'static',
        value: null,
    },
    newTab: {
        source: 'static',
        value: false,
    },
    minHeight: {
        source: 'static',
        value: '340px',
    },
    verticalAlign: {
        source: 'static',
        value: 'center',
    },
    horizontalAlign: {
        source: 'static',
        value: 'center',
    },
    isDecorative: {
        source: 'static',
        value: false,
    },
    fetchPriorityHigh: {
        source: 'static',
        value: false,
    },
};

/**
 * @private
 * @sw-package discovery
 */
export default IMAGE_DEFAULT_CONFIG;
