import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/component/sw-ratings/sw-extension-review-reply';

describe('src/module/sw-extension/component/sw-ratings/sw-extension-review-reply', () => {
    /** @type Wrapper */
    let wrapper;

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-extension-review-reply'), {
            propsData: {
                producerName: 'Howard Wolowitz',
                reply: {
                    text: 'Lorem ipsum dolor sit amet.',
                    creationDate: {
                        date: '2021-01-11 08:10:08.000000',
                        timezone_type: 1,
                        timezone: '+01:00'
                    }
                }
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the extension creator name', () => {
        wrapper = createWrapper();
        const creatorName = wrapper.find('.sw-extension-review-reply__producer-name').text();

        expect(creatorName).toBe('Howard Wolowitz');
    });

    it('should display the actual content of the reply', () => {
        wrapper = createWrapper();
        const replyContent = wrapper.find('.sw-extension-review-reply__text').text();

        expect(replyContent).toBe('Lorem ipsum dolor sit amet.');
    });
});
