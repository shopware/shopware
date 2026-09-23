<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const label = computed(() => 'overridden');

function persist(value: string) {
    previousState.persisted.value.push(`override:${value}`);
}

swDefineOverride({
    label,
    persist,
});
</script>
