import './sw-media-quickinfo-metadata-item.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template: `
        <dt :class="$attrs.class" class="sw-media-quickinfo-metadata-item__term">
            {{ this.labelName }}:
        </dt>
        <dd :class="$attrs.class"  class="sw-media-quickinfo-metadata-item__description">
            <slot/>
        </dd>
    `,

    props: {
        labelName: {
            required: true,
            type: String,
        },
    },
};
