import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'youtube-video',
    label: 'sw-cms.elements.youtubeVideo.label',
    component: 'sw-cms-el-youtube-video',
    configComponent: 'sw-cms-el-config-youtube-video',
    previewComponent: 'sw-cms-el-preview-youtube-video',
    defaultConfig: {
        videoID: {
            source: 'static',
            value: '',
            required: true,
        },
        autoPlay: {
            source: 'static',
            value: false,
        },
        loop: {
            source: 'static',
            value: false,
        },
        showControls: {
            source: 'static',
            value: true,
        },
        start: {
            source: 'static',
            value: null,
        },
        end: {
            source: 'static',
            value: null,
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        advancedPrivacyMode: {
            source: 'static',
            value: true,
        },
        needsConfirmation: {
            source: 'static',
            value: false,
        },
        previewMedia: {
            source: 'static',
            value: null,
            entity: {
                name: 'media',
            },
        },
    },
});
