import { Component, Mixin } from 'src/core/shopware';
import template from './swag-speech.html.twig';
import './swag-speech.less';

Component.register('swag-speech', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            text: 'Hello world!',
            activeVoice: null,
            voices: []
        };
    },

    created() {
        speechSynthesis.onvoiceschanged = () => {
            this.voices = window.speechSynthesis.getVoices();
            this.activeVoice = this.voices.find((voice) => {
                return voice.default;
            });
        };
    },

    methods: {
        onSayText() {
            const synth = window.speechSynthesis;
            const utterance = new SpeechSynthesisUtterance(this.text);
            utterance.lang = this.activeVoice.lang;
            utterance.voice = this.activeVoice;

            this.createNotification();

            synth.speak(utterance);
        },

        createNotification() {
            this.createNotificationSuccess({
                title: 'Info',
                message: 'The text should be read now'
            });
        },

        onChangeLanguage(event) {
            const lang = event.target.value;
            this.activeVoice = this.voices.find((voice) => {
                return voice.lang === lang;
            });
        }
    }
});
