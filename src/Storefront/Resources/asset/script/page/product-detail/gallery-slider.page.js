import { tns } from 'tiny-slider/src/tiny-slider.module';

export default class GallerySlider {

    /**
    * Constructor.
    */
    constructor() {
        this._init();
    }

    _init() {
        tns({
            container: '#gallery',
            items: 1,
            mode: 'gallery',
            navContainer: '#customize-thumbnails',
            navAsThumbnails: true,
            autoHeight: true
        });
    }

}