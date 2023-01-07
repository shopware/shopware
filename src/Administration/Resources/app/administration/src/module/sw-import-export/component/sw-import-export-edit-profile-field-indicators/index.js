/**
 * @package system-settings
 */
import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-import-export-edit-profile-field-indicators.html.twig';
import './sw-import-export-edit-profile-field-indicators.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors(
            'profile',
            [
                'delimiter',
                'enclosure',
            ],
        ),

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
};
