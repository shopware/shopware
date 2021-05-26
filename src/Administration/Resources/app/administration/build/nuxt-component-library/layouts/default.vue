<template>
    <div class="page-wrap">
        <aside class="sidebar--main">
            <div class="logo">
                <nuxt-link to="/">
                    <div class="logo--inner" v-html="logoSvg"></div>
                </nuxt-link>
            </div>
            <nav class="navigation--main">
                <div class="search-wrapper">
                    <input type="text" v-model="searchTerm" class="search-query" autocomplete="off" spellcheck="false" placeholder="Search">
                </div>
                <ul class="nav-tree--category">
                    <li class="nav-tree--main-entry" v-for="mainEntry in menu" :key="mainEntry.name">
                        <span class="nav-tree--main-entry-headline">{{ mainEntry.name }} components</span>

                        <ul class="nav-tree--sub-entries" v-if="mainEntry.children.length > 0">
                            <li class="nav-tree--sub-entry" v-for="subEntry in mainEntry.children" :key="subEntry.name">
                                <nuxt-link :to="'/components/' + subEntry.name" class="nav--link">
                                    {{ subEntry.readableName }}
                                </nuxt-link>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-tree--main-entry">
                        <span class="nav-tree--main-entry-headline">Cheat Sheets</span>
                        <ul class="nav-tree--sub-entries">
                            <nuxt-link to="/icons/" class="nav--link">
                                Icon set
                            </nuxt-link>
                        </ul>
                    </li>
                </ul>
            </nav>
        </aside>

        <section class="content--main">
            <nuxt></nuxt>
        </section>
    </div>
</template>

<style>
    .search-wrapper {
        padding: 0 40px;
    }

    .logo {
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .logo--inner {
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }

    .logo--inner svg {
        height: 100%;
        width: 100%;
    }

    .search-query {
        display: block;
        font-size: 16px;
        height: 40px;
        padding: 5px 20px;
        width: 100%;
        border-radius: 30px;
        color: #fff;
        font-weight: 400;
        background: rgba(205,221,247,0.15);
        font-family: 'Brandon', Arial, Helvetica, sans-serif;
        outline: none;
        border: none;
    }

    .search-query:focus {
        outline: none;
    }
</style>

<script>
import logo from '~/assets/img/component-library-logo.svg';

export default {
    data() {
        return {
            menu: null,
            searchTerm: this.$route.query.q || ''
        };
    },

    created() {
        this.menu = this.getMenuStructure();
    },

    watch: {
        searchTerm() {
            const value = event.target.value;
            if (value.length > 0) {
                this.$router.replace({ path: this.$route.path, query: { q: value }});
            } else {
                this.$router.replace({ path: this.$route.path });
            }
            this.menu = this.getMenuStructure();
        }
    },

    computed: {
        logoSvg() {
            return logo;
        }
    },

    methods: {
        getMenuStructure() {
            return this.$filesInfo.reduce((accumulator, item) => {
                // skip meteor components
                if (!item || !item || !item.path || item.path.includes('meteor') || item.source.readableName === undefined) {
                    return accumulator;
                }

                // Ignore the component when it's marked as private
                if (item.source.meta.hasOwnProperty('private') && item.source.meta.private === true) {
                    return accumulator;
                }

                if (!item.source.readableName.toLowerCase().includes(this.searchTerm.toLowerCase())) {
                    return accumulator;
                }

                if (!accumulator.hasOwnProperty(item.type)) {
                    accumulator[item.type] = {
                        name: item.type,
                        children: []
                    };
                }

                accumulator[item.type].children.push({
                    name: item.source.name,
                    readableName: item.source.readableName,
                    type: item.type,
                });

                return accumulator;
            }, {});
        }
    }
}
</script>
