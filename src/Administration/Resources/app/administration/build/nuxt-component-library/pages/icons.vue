<template>
    <div class="icon-set">
        <h1>Icon set</h1>

        <p class="icon-set__introduction is--xl">
            This is an overview of all icons which are available in the shopware administration.
            <nuxt-link to="/components/sw-icon">How to use the icon component</nuxt-link>.
        </p>

        <div class="icon-set__item-wrapper">
            <div v-for="icon in currentIcons" :key="icon" class="icon-set__item">
                <div class="icon-set__item-holder">
                    <sw-icon :name="icon" :multicolor="isMulticolor(icon)"></sw-icon>
                </div>
                <div class="icon-set__item-name">{{ icon }}</div>
            </div>
        </div>
    </div>
</template>

<script>
    import iconComponents from 'src/app/assets/icons/icons';
    const iconNames = iconComponents.map((comp) => comp.name);

    export default {

        head() {
            return {
                title: 'Icon set'
            };
        },

        data() {
            return {
                currentIcons: []
            }
        },

        created() {
            this.currentIcons = this.icons;
        },

        methods: {
            isMulticolor(iconName) {
                return iconName.includes('multicolor');
            }
        },

        computed: {
            icons() {
                return iconNames.map((iconName) => {
                    return iconName.replace('icons-', '');
                });
            }
        }
    };
</script>

<style lang="scss">
    .icon-set {
        .icon-set__introduction {
            margin-bottom: 30px;

            a {
                color: #189EFF;
                text-decoration: none;

                &:hover {
                    text-decoration: underline;
                }
            }
        }

        .icon-set__item-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            grid-gap: 30px;
        }

        .icon-set__item {
            text-align: center;
        }

        .sw-icon {
            margin-bottom: 10px;
            color: #142432;
            width: auto;
            height: auto;

            svg {
                width: auto;
                height: auto;
            }
        }

        .icon-set__item-name {
            font-size: 14px;
            padding: 10px;
        }
    }
</style>
