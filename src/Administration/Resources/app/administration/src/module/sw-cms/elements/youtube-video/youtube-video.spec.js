import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/youtube-video/config';

describe('modules/sw-cms/elements/youtube-video', () => {
    const youtubeComponent = Shopware.Component.build('sw-cms-el-config-youtube-video');

    it('should get the ID from the share link', async () => {
        const shortLink = youtubeComponent.methods.shortenLink('https://youtu.be/Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the share link with starting point', async () => {
        const shortLink = youtubeComponent.methods.shortenLink('https://youtu.be/Bey4XXJAqS8?t=1');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the url', async () => {
        const shortLink = youtubeComponent.methods.shortenLink('https://www.youtube.com/watch?v=Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should get the ID from the url with starting point', async () => {
        const shortLink = youtubeComponent.methods.shortenLink('https://www.youtube.com/watch?v=Bey4XXJAqS8');

        expect(shortLink).toBe('Bey4XXJAqS8');
    });

    it('should convert time to url format', async () => {
        const convertedTime = youtubeComponent.methods.convertTimeToUrlFormat('20:33');

        expect(convertedTime.minutes).toBe(20);
        expect(convertedTime.seconds).toBe(33);
        expect(convertedTime.string).toBe(1233);
    });

    it('should set a fallback value if user types no valid time', async () => {
        const userInput = youtubeComponent.methods.convertTimeToUrlFormat('help');

        expect(userInput.seconds).toBe(0);
        expect(userInput.minutes).toBe(0);
        expect(userInput.string).toBe(0);
    });

    it('should convert time to input format', async () => {
        const convertedTime = youtubeComponent.methods.convertTimeToInputFormat(2077);

        expect(convertedTime.seconds).toBe(37);
        expect(convertedTime.minutes).toBe(34);
        expect(convertedTime.string).toBe('34:37');
    });

    it('should set a fallback value if user types no valid time', async () => {
        const userInput = youtubeComponent.methods.convertTimeToInputFormat('aaaahhhhh');

        expect(userInput.seconds).toBe(0);
        expect(userInput.minutes).toBe(0);
        expect(userInput.string).toBe('0:00');
    });
});
