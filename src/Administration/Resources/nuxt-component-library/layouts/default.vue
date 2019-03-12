<template>
    <div class="page-wrap">
        <aside class="sidebar--main">
            <div class="logo">
                <nuxt-link to="/">
                    <img src="~/assets/img/logo-white.svg" alt="Shopware Component library logo" width="120px" height="120px">
                </nuxt-link>
            </div>
            <nav class="navigation--main">
                <input type="text" v-model="searchTerm" class="search-query" autocomplete="off" spellcheck="false" placeholder="Search">
                <ul class="nav-tree--category">
                    <li class="nav-tree--main-entry" v-for="mainEntry in menu" :key="mainEntry.name">
                        <span class="nav-tree--main-entry-headline">{{ mainEntry.name }} components</span>

                        <ul class="nav-tree--sub-entries" v-if="mainEntry.children.length > 0">
                            <li class="nav-tree--sub-entry" v-for="subEntry in mainEntry.children" :key="subEntry.name">
                                <nuxt-link :to="'/components/' + subEntry.name" class="nav--link">
                                    &lt;{{ subEntry.name }}&gt;
                                </nuxt-link>
                            </li>
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
    .search-query {
        display: block;
        font-size: 16px;
        height: 40px;
        padding: 5px 50px;
        margin: 0 20px;
        border-radius: 30px;
        color: #fff;
        font-weight: 400;
        background: rgba(205,221,247,0.15);
        font-family: 'Brandon';
    }

    .search-query:focus {
        outline: none;
    }
</style>

<script>
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

    methods: {
        onSearch(event) {
            const value = event.target.value;
            if (value.length > 0) {
                this.$router.replace({ path: this.$route.path, query: { q: value }});
            } else {
                this.$router.replace({ path: this.$route.path });
            }
            this.searchTerm = value;

            this.menu = this.getMenuStructure();
        },
        getMenuStructure() {
            return this.$filesInfo.reduce((accumulator, item) => {
                // Ignore the component when it's marked as private
                if (item.source.meta.hasOwnProperty('private') && item.source.meta.private === true) {
                    return accumulator;
                }

                if (!item.source.name.includes(this.searchTerm)) {
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
                    type: item.type,
                });

                return accumulator;
            }, {});
        }
    }
}
</script>