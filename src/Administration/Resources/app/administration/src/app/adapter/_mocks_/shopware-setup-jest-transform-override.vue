<script setup sw-override="sw-jest-transform-fixture">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const count = computed(() => previousState.count.value + 1);

swDefineOverride({
    count,
});
</script>
