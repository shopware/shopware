import template from './sw-review-reply.html.twig';
import './sw-review-reply.scss';

const { date } = Shopware.Utils.format;

const { Component } = Shopware;

Component.register('sw-review-reply', {
    template,

    props: {
        reply: {
            type: Object,
            required: true
        },

        producerName: {
            type: String,
            required: true
        }
    },

    computed: {
        creationDate() {
            return this.reply.creationDate !== null ? date(this.reply.creationDate) : null;
        }
    }
});
