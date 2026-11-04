/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import template from './sw-settings-webhook-secret-modal.html.twig';
import './sw-settings-webhook-secret-modal.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    emits: ['modal-close'],

    mixins: [Mixin.getByName('notification')],

    props: {
        webhook: {
            type: Object as PropType<Entity<'webhook'>>,
            required: true,
        },
    },

    data(): {
        isOpen: boolean;
        isSaving: boolean;
        secret: string;
    } {
        return {
            isOpen: true,
            isSaving: false,
            secret: '',
        };
    },

    computed: {
        webhookRepository(): Repository<'webhook'> {
            return this.repositoryFactory.create('webhook');
        },

        canSave(): boolean {
            return this.secret.trim() !== '';
        },
    },

    methods: {
        generateSecret(): void {
            const bytes = crypto.getRandomValues(new Uint8Array(32));

            this.secret = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        },

        async onSave(): Promise<void> {
            const webhook = this.webhookRepository.create(Shopware.Context.api, this.webhook.id);
            webhook._isNew = false;
            webhook.secret = this.secret;

            this.isSaving = true;

            try {
                await this.webhookRepository.save(webhook);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-webhook.secret.messageSaveError', { name: this.webhook.name }),
                });

                return;
            } finally {
                this.isSaving = false;
            }

            this.createNotificationSuccess({
                message: this.$t('sw-settings-webhook.secret.messageSaveSuccess', { name: this.webhook.name }),
            });
            this.$emit('modal-close');
        },

        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('modal-close');
            }
        },
    },
});
