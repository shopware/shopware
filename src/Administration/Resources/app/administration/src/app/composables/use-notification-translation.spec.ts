/**
 * @sw-package framework
 */
import useNotificationTranslation from './use-notification-translation';
import type { NotificationType } from '../store/notification.store';

const t = jest.fn();
const te = jest.fn();
const sanitize = jest.fn();

jest.mock('vue-i18n', () => ({
    useI18n: () => ({ t, te }),
}));

// `sanitize` is referenced lazily inside the arrow: the jest.mock factory is hoisted above the const,
// so touching it eagerly would hit the temporal dead zone.
jest.mock('src/core/helper/sanitizer.helper', () => ({
    __esModule: true,
    default: { sanitize: (html: string, config: object): string => sanitize(html, config) as string },
}));

describe('src/app/composables/use-notification-translation', () => {
    beforeEach(() => {
        t.mockReset();
        te.mockReset();
        sanitize.mockReset();
        t.mockImplementation((key: string) => `translated:${key}`);
        sanitize.mockImplementation((html: string) => `clean:${html}`);
    });

    it('getTranslatedTitle returns "" for a missing title', () => {
        const { getTranslatedTitle } = useNotificationTranslation();

        expect(getTranslatedTitle({ title: '' } as NotificationType)).toBe('');
        expect(te).not.toHaveBeenCalled();
    });

    it('getTranslatedTitle translates a known snippet key and passes plain strings through', () => {
        const { getTranslatedTitle } = useNotificationTranslation();

        te.mockReturnValue(true);
        expect(getTranslatedTitle({ title: 'a.key' } as NotificationType)).toBe('translated:a.key');

        te.mockReturnValue(false);
        expect(getTranslatedTitle({ title: 'Plain title' } as NotificationType)).toBe('Plain title');
    });

    it('getTranslatedMessage returns "" for a missing message', () => {
        const { getTranslatedMessage } = useNotificationTranslation();

        expect(getTranslatedMessage({ message: '' } as NotificationType)).toBe('');
        expect(sanitize).not.toHaveBeenCalled();
    });

    it('getTranslatedMessage translates then sanitizes with the allow-list', () => {
        const { getTranslatedMessage } = useNotificationTranslation();
        te.mockReturnValue(true);

        expect(getTranslatedMessage({ message: 'a.key' } as NotificationType)).toBe('clean:translated:a.key');
        expect(sanitize).toHaveBeenCalledWith('translated:a.key', {
            ALLOWED_TAGS: [
                'a',
                'b',
                'i',
                'u',
                'strong',
                'em',
                'br',
            ],
            ALLOWED_ATTR: [
                'href',
                'target',
            ],
        });
    });
});
