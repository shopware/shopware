<template>
    <div class="page--homepage">
        <h1>Welcome to the Shopware Component library</h1>
    
        <p class="is--xl">
            Lorem ipsum dolor sit, amet consectetur adipisicing elit. Molestias nulla quaerat maiores, natus aspernatur iusto quia odit esse eligendi dolorum, minima pariatur. Unde dolor sapiente et, esse harum commodi consequatur.
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