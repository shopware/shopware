<template>
    <div class="deprecation-warning" v-if="component.meta.deprecated || component.deprecations">
        <p>
            <div class="warning-headline">
                <sw-icon name="regular-exclamation-triangle" />
                <strong>Warning</strong>
            </div>

            <div class="content">
                <div class="first-line">
                    The component state changes for version {{ component.meta.deprecated || component.deprecations.version }}
                    <template v-if="component && component.deprecations && component.deprecations.comment">
                        – {{ component.deprecations.comment }}
                    </template>
                </div>

                <nuxt-link to="/faq" style="margin-top: 6px;">
                    More information
                </nuxt-link>
            </div>
        </p>
    </div>
</template>

<script>
export default {
    props: {
        component: {
            type: Object,
            required: true
        }
    }
};
</script>

<style lang="scss" scoped>
    .deprecation-warning {
        margin-bottom: 24px;
        padding: 6px 24px;
        border-radius: 4px;
        background-color: #fff3e0;
        color: #000;
        border: 1px solid #e65100;

        a {
            color: #1d8fe1;
            text-decoration: none;
        }

        p {
            font-size: 1em;
        }

        .warning-headline {
            margin-bottom: 12px;
            display: flex;
            align-items: center;

            strong {
                margin-left: 8px;
            }
        }

        .content {
            display: flex;
            flex-direction: column;
        }
    }
</style>