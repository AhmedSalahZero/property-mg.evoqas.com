<template>
  <div
    v-if="show && property"
    class="fixed inset-0 flex items-center justify-center z-[1000] px-4"
    style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);"
    @click.self="$emit('close')"
  >
    <div
      class="rounded-2xl p-6 w-full max-w-md shadow-2xl"
      style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);"
    >
      <div class="flex items-start justify-between gap-3 mb-4">
        <div>
          <h3 class="fv-text-primary font-bold text-base">Descriptions</h3>
          <p class="text-xs fv-text-muted mt-0.5">{{ property.property_name }}</p>
        </div>
        <button
          type="button"
          class="fv-action-btn flex-shrink-0"
          aria-label="Close"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <div v-if="tags.length" class="flex flex-wrap gap-2">
        <span
          v-for="t in tags"
          :key="t.id"
          class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium"
          style="background:rgba(20,144,168,0.15); border:1px solid rgba(20,144,168,0.3); color:#48C4D8;"
        >
          {{ t.name }}
        </span>
      </div>
      <p v-else class="text-sm fv-text-muted text-center py-6">
        No descriptions added for this property yet.
      </p>

      <div class="mt-5 flex justify-end">
        <button
          type="button"
          class="px-4 py-2 rounded-lg text-sm font-semibold text-white cursor-pointer"
          style="background:var(--fv-blue,#1490A8); border:none;"
          @click="$emit('close')"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  property: { type: Object, default: null },
})

defineEmits(['close'])

const tags = computed(() => props.property?.tags || [])
</script>
