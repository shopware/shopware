// eslint-disable-next-line max-len
import SwTextEditorToolbarButtonCmsDataMappingButton from 'src/app/component/meteor-wrapper/mt-text-editor/sw-text-editor-toolbar-button-cms-data-mapping';
import { generateJSON } from '@tiptap/core';
import Document from '@tiptap/extension-document';
import Bold from '@tiptap/extension-bold';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Heading from '@tiptap/extension-heading';

import template from './sw-cms-el-config-text.html.twig';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        availableDataMappings() {
            let mappings = [];

            Object.entries(Shopware.Store.get('cmsPage').currentMappingTypes).forEach((entry) => {
                const [
                    type,
                    value,
                ] = entry;

                if (type === 'string') {
                    mappings = [
                        ...mappings,
                        ...value,
                    ];
                }
            });

            return mappings;
        },

        customTextEditorButtons() {
            return [
                SwTextEditorToolbarButtonCmsDataMappingButton(() => this.availableDataMappings),
            ];
        },

        alignmentOptions() {
            return [
                {
                    id: 1,
                    value: 'flex-start',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignTop'),
                },
                {
                    id: 2,
                    value: 'center',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignCenter'),
                },
                {
                    id: 3,
                    value: 'flex-end',
                    label: this.$tc('sw-cms.elements.general.config.label.verticalAlignBottom'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('text');
        },

        onBlur(content) {
            this.emitChanges(content);
        },

        onInput(content) {
            this.emitChanges(content);
        },

        transformHtmlContentToJson(content) {
            return JSON.stringify(generateJSON(content, [
                Document,
                Paragraph,
                Text,
                Heading,
                Bold,
            ]));
        },

        emitChanges(content) {
            if (content !== this.element.config.content.value) {
                this.element.config.content.value = content;
                this.element.config.content.contentSchema = this.transformHtmlContentToJson(content);

                this.$emit('element-update', this.element);
            }
        },
    },
};
