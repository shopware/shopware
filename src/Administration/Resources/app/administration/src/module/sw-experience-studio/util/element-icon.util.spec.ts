/**
 * @sw-package discovery
 */

import { getLocalElementIcon, getMeteorElementIcon } from './element-icon.util';

describe('Experience Studio element icons', () => {
    it('resolves a bundled illustration and its compact Meteor counterpart', () => {
        expect(getLocalElementIcon('local:media-image')).toEqual({
            assetPath: '/administration/administration/static/img/experience-studio/element-icons/media-image.svg',
            meteorIcon: 'regular-image',
        });
        expect(getMeteorElementIcon('local:media-image')).toBe('regular-image');
    });

    it.each(['regular-image', 'regular-star'])('preserves the configured Meteor icon %s', (icon) => {
        expect(getLocalElementIcon(icon)).toBeNull();
        expect(getMeteorElementIcon(icon)).toBe(icon);
    });

    it.each([
        null,
        '',
        'local:missing',
        'local:constructor',
        'local:__proto__',
        'local:../../image',
    ])('%s falls back without resolving an arbitrary asset', (icon) => {
        expect(getLocalElementIcon(icon)).toBeNull();
        expect(getMeteorElementIcon(icon)).toBe('regular-square');
        expect(getMeteorElementIcon(icon, 'bars-square-s')).toBe('bars-square-s');
    });
});
