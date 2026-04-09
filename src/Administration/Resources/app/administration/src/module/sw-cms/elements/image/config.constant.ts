const COMMON_MEDIA_CONFIG = {
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
    ariaLabel: {
        source: 'static',
        value: null,
    },
};

const IMAGE_DEFAULT_CONFIG = {
    ...COMMON_MEDIA_CONFIG,
    url: {
        source: 'static',
        value: null,
    },
    newTab: {
        source: 'static',
        value: false,
    },
    fetchPriorityHigh: {
        source: 'static',
        value: false,
    },
    isDecorative: {
        source: 'static',
        value: false,
    },
};

const VIDEO_DEFAULT_CONFIG = {
    ...COMMON_MEDIA_CONFIG,
    autoPlay: {
        source: 'static',
        value: false,
    },
    muted: {
        source: 'static',
        value: true,
    },
    loop: {
        source: 'static',
        value: false,
    },
    playsInline: {
        source: 'static',
        value: false,
    },
    showControls: {
        source: 'static',
        value: true,
    },
    showCover: {
        source: 'static',
        value: false,
    },
};

/**
 * @private
 * @sw-package discovery
 */
export { COMMON_MEDIA_CONFIG, IMAGE_DEFAULT_CONFIG, VIDEO_DEFAULT_CONFIG };
