<!--
  Fix for audit finding F-6 (first increment).

  This exact label/value/sub card markup — a rounded card with an uppercase
  colored label, a large bold value, and a muted subtext line — repeats
  roughly 25+ times through Dashboard.vue alone (the Portfolio KPI strip
  below is one of several places using this identical shape). Pulling it out
  into its own presentational component means every future change to "what a
  KPI card looks like" happens in one 20-line file instead of needing to be
  copy-pasted correctly into 25+ places by hand — and it shrinks the parent
  file by the same amount every place it gets adopted.

  Deliberately kept PURE PRESENTATIONAL: props in, nothing else. It has no
  business logic, no store/state access, and touches none of Dashboard.vue's
  chart instances or data-fetching — which is exactly why it's safe to
  extract on its own, unlike the tab sections themselves (those hold shared
  chart-lifecycle and fetch logic that genuinely needs a running dev server
  to refactor safely — see the accompanying audit note on F-6 scope).
-->
<template>
  <div class="rounded-xl p-4" style="background: var(--fv-bg-card); border: 1px solid var(--fv-border);">
    <p class="text-xs font-semibold uppercase tracking-widest mb-1" :style="`color: ${color};`">{{ label }}</p>
    <p class="text-2xl font-bold" style="color: var(--fv-text-primary);">{{ value }}</p>
    <p class="text-xs mt-1" style="color: var(--fv-text-muted);">{{ sub }}</p>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  sub:   { type: String, default: '' },
  color: { type: String, default: 'var(--fv-text-muted)' },
})
</script>
