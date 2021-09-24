import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-import-export-edit-profile-field-indicators.html.twig';
import './sw-import-export-edit-profile-field-indicators.scss';

const { Component } = Shopware;

/**
 * @internal (flag:FEATURE_NEXT_15998)
 */
Component.register('sw-import-export-edit-profile-field-indicators', {
    template,

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('profile',
            [
                'delimiter',
                'enclosure',
            ]),

        supportedDelimiter() {
            return [
                {
                    value: '^',
                    label: this.$tc('sw-import-export.profile.caretsLabel'),
                },
                {
                    value: ',',
                    label: this.$tc('sw-import-export.profile.commasLabel'),
                },
                {
                    value: ';',
                    label: this.$tc('sw-import-export.profile.semicolonLabel'),
                },
            ];
        },

        supportedEnclosures() {
            return [
                {
                    value: '"',
                    label: this.$tc('sw-import-export.profile.doubleQuoteLabel'),
                },
            ];
        },
    },
});
