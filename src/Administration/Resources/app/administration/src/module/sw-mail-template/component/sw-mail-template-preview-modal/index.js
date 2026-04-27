import template from './sw-mail-template-preview-modal.html.twig';
import './sw-mail-template-preview-modal.scss';

/**
 * @sw-package after-sales
 *
 * @private
 */
export default {
    template,

    emits: [
        'modal-close',
    ],

    props: {
        mailPreview: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    methods: {
        buildPreviewHtmlDocument(htmlContent) {
            return `<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta
            http-equiv="Content-Security-Policy"
            content="default-src 'none'; style-src 'unsafe-inline'; img-src data: blob: cid:; font-src data:;"
        >
        <meta name="referrer" content="no-referrer">
        <style>
            html, body {
                margin: 0;
                padding: 0;
                background: #fff;
                color: #1f2d3d;
                font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: 14px;
                line-height: 1.5;
                overflow-wrap: anywhere;
            }
        </style>
    </head>
    <body>${htmlContent ?? ''}</body>
</html>`;
        },
    },
};
