<template>
  <!--
    NavTab.vue — Single tab pill with status indicator dot
    Status dot colours:
      ✅ is_calculated + !needs_recalculation  → green
      ⚠️ needs_recalculation                  → amber
      💾 is_saved + !is_calculated            → blue
      ○  none                                  → grey (no dot)
  -->
  <component
    :is="href ? 'a' : 'button'"
    :href="href"
    class="nav-tab"
    :class="{ 'nav-tab--active': active, 'nav-tab--accent': accent }"
    style="white-space:nowrap; flex-shrink:0;"
  >
    <!-- Status dot -->
    <span
      v-if="statusDot"
      class="status-dot"
      :class="`status-dot--${statusDot}`"
      :title="statusTitle"
    />
    {{ label }}
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label:  { type: String,  required: true },
  href:   { type: String,  default: null },
  active: { type: Boolean, default: false },
  accent: { type: Boolean, default: false },
  // status: { is_saved, is_calculated, needs_recalculation } or null
  status: { type: Object,  default: null },
})

const statusDot = computed(() => {
  if (!props.status) return null
  const { is_saved, is_calculated, needs_recalculation } = props.status
  if (is_calculated && !needs_recalculation) return 'green'
  if (needs_recalculation) return 'amber'
  if (is_saved) return 'blue'
  return null
})

const statusTitle = computed(() => {
  if (!props.status) return ''
  const { is_calculated, needs_recalculation, is_saved } = props.status
  if (is_calculated && !needs_recalculation) return 'Calculated & up to date'
  if (needs_recalculation) return '⚠️ General Assumptions changed — recalculation needed'
  if (is_saved) return 'Saved, not yet calculated'
  return ''
})
</script>

<style scoped>
.nav-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #94B8D0;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
  border: 1px solid transparent;
  background: transparent;
}
.nav-tab:hover {
  background: #0C1829;
  color: #F1F5F9;
}
.nav-tab--active {
  background: #0C3D6B;
  border: 1px solid #1490A8;
  color: #48C4D8;
}
.nav-tab--accent.nav-tab--active {
  background: #0C3D6B;
  border: 1px solid #1490A8;
  color: #48C4D8;
}
.nav-tab--accent:hover {
  background: #0C3D6B;
  color: #F1F5F9;
}

/* Status dots */
.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-dot--green { background: #22c55e; }
.status-dot--amber { background: #f59e0b; }
.status-dot--blue  { background: #48C4D8; }
</style>