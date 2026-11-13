/**
 * @sw-package discovery
 */
import crossSellingTemplate from './cross-selling/config/sw-cms-el-config-cross-selling.html.twig';
import formTemplate from './form/config/sw-cms-el-config-form.html.twig';
import imageGalleryTemplate from './image-gallery/config/sw-cms-el-config-image-gallery.html.twig';
import imageSliderTemplate from './image-slider/config/sw-cms-el-config-image-slider.html.twig';
import imageTemplate from './image/config/sw-cms-el-config-image.html.twig';
import productBoxTemplate from './product-box/config/sw-cms-el-config-product-box.html.twig';
import productListingTemplate from './product-listing/config/sw-cms-el-config-product-listing.html.twig';
import productSliderTemplate from './product-slider/config/sw-cms-el-config-product-slider.html.twig';
import videoTemplate from './video/config/sw-cms-el-config-video.html.twig';
import youtubeVideoTemplate from './youtube-video/config/sw-cms-el-config-youtube-video.html.twig';

const requiredConfigFields = [
    'boxLayout',
    'displayMode',
    'navigationArrows',
    'navigationDots',
    'galleryPosition',
    'type',
];

const optionalConfigFields = [
    'verticalAlign',
    'horizontalAlign',
    'alignment',
];

function findSelects(template, tagName) {
    return [...template.matchAll(new RegExp(`<${tagName}\\b[^>]*>`, 'g'))].map(([tag]) => tag);
}

function configField(tag) {
    return tag.match(/v-model(?::[\w-]+)?="element\.config\.(\w+)\.(?:value|source)"/)?.[1];
}

describe('module/sw-cms/elements/required-config-selects', () => {
    it.each([
        ['cross-selling', crossSellingTemplate, 4],
        ['form', formTemplate, 2],
        ['image-gallery', imageGalleryTemplate, 8],
        ['image-slider', imageSliderTemplate, 6],
        ['image', imageTemplate, 1],
        ['product-box', productBoxTemplate, 2],
        ['product-listing', productListingTemplate, 2],
        ['product-slider', productSliderTemplate, 6],
        ['video', videoTemplate, 1],
        ['youtube-video', youtubeVideoTemplate, 1],
    ])('should not offer to clear required selects of the %s element', (element, template, expectedCount) => {
        const requiredSelects = findSelects(template, 'mt-select').filter((tag) =>
            requiredConfigFields.includes(configField(tag)),
        );

        expect(requiredSelects).toHaveLength(expectedCount);
        requiredSelects.forEach((tag) => {
            expect(tag).toContain(':hide-clearable-button="true"');
        });
    });

    it('should keep alignment selects clearable', () => {
        const alignmentSelects = [
            imageGalleryTemplate,
            imageSliderTemplate,
            imageTemplate,
            productBoxTemplate,
            productSliderTemplate,
            videoTemplate,
        ]
            .flatMap((template) => findSelects(template, 'mt-select'))
            .filter((tag) => optionalConfigFields.includes(configField(tag)));

        expect(alignmentSelects.length).toBeGreaterThan(0);
        alignmentSelects.forEach((tag) => {
            expect(tag).not.toContain('hide-clearable-button');
        });
    });

    it('should not offer to clear the product assignment type of the product slider', () => {
        const assignmentTypeSelects = findSelects(productSliderTemplate, 'sw-single-select').filter(
            (tag) => configField(tag) === 'products',
        );

        expect(assignmentTypeSelects).toHaveLength(2);
        assignmentTypeSelects.forEach((tag) => {
            expect(tag).not.toContain('show-clearable-button');
        });
    });
});
