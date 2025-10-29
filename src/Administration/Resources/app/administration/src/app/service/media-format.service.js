/**
 * @sw-package discovery
 */

/**
 * List of video formats that are playable in most browsers
 * @type {string[]}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const PLAYABLE_VIDEO_FORMATS = [
    'video/mp4',
    'video/ogg',
    'video/webm',
];

/**
 * List of audio formats that are playable in most browsers
 * @type {string[]}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const PLAYABLE_AUDIO_FORMATS = [
    'audio/mp3',
    'audio/mpeg',
    'audio/ogg',
    'audio/wav',
];

/**
 * Combined list of all playable media formats
 * @type {string[]}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const PLAYABLE_MEDIA_FORMATS = [
    ...PLAYABLE_VIDEO_FORMATS,
    ...PLAYABLE_AUDIO_FORMATS,
];

/**
 * Check if a given mime type is a playable media format
 * @param {string} mimeType
 * @returns {boolean}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isPlayableMediaFormat(mimeType) {
    return PLAYABLE_MEDIA_FORMATS.includes(mimeType);
}

/**
 * Check if a given mime type should show an unsupported format warning
 * @param {string} mimeType
 * @returns {boolean}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function shouldShowUnsupportedFormatWarning(mimeType) {
    if (!mimeType) {
        return false;
    }

    const mimeTypeGroup = mimeType.split('/')[0];
    return (mimeTypeGroup === 'video' || mimeTypeGroup === 'audio') && !isPlayableMediaFormat(mimeType);
}
