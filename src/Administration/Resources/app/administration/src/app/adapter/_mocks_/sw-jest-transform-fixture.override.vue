<script setup>
import { computed } from 'vue';

const previousState = useSwPreviousState();
const count = computed(() => previousState.count.value + 1);

swDefineOverride({
    count,
});
</script>
