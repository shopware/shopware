<template>
    <div class="page--homepage">
        <h1>Welcome to the Shopware Component library</h1>
    
        <p class="is--xl">
            Explore the look and feel of components to speed up your app development. The Shopware Administration got
            break
        </p>
    </div>
</template>

<script>
export default {
    data() {
        return {
            title: 'Homepage'
        };
    },

    head() {
        return {
            title: this.title
        };
    }
}
</script>