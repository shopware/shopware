import { Component } from 'src/core/shopware';

Component.override('swag-speech', {
    template: '',

    methods: {
        onSayText() {
            this.$super.onSayText();
            alert(this.text);
        }
    }
});
