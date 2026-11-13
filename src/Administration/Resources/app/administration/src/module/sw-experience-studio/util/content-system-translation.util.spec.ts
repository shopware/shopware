import { translateContentSystemText } from './content-system-translation.util';

describe('translateContentSystemText', () => {
    const messages: Record<string, string> = { 'plugin.label': 'Bild' };
    const exists = (key: string) => key in messages;
    const translate = (key: string) => messages[key];

    it('uses the explicit translation and preserves the fallback when it is missing', () => {
        expect(translateContentSystemText('plugin.label', 'Image', exists, translate)).toBe('Bild');
        expect(translateContentSystemText('plugin.missing', 'Image', exists, translate)).toBe('Image');
        expect(translateContentSystemText(undefined, 'Image', exists, translate)).toBe('Image');
    });

    it('uses the current locale each time it resolves the label', () => {
        expect(translateContentSystemText('plugin.label', 'Image', exists, translate)).toBe('Bild');
        messages['plugin.label'] = 'Image';
        expect(translateContentSystemText('plugin.label', 'Image', exists, translate)).toBe('Image');
    });
});
