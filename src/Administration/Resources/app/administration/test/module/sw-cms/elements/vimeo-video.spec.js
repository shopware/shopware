import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/vimeo-video/config';

describe('modules/sw-cms/elements/vimeo-video', () => {
    const vimeoComponent = Shopware.Component.build('sw-cms-el-config-vimeo-video');

    it('should get the video ID from the vimeo link', async () => {
        const shortenLink = vimeoComponent.methods.shortenLink('https://vimeo.com/255024952');

        expect(shortenLink).toBe('255024952');
    });

    it('should get the video ID from the vimeo link with a timestamp', async () => {
        const shortenLink = vimeoComponent.methods.shortenLink('https://vimeo.com/282340616#t=120s');

        expect(shortenLink).toBe('282340616');
    });
});
