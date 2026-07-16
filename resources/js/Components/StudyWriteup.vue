<template>
  <!-- ── Trigger Button ── -->
  <button type="button" @click="open = true"
    :class="[
      'flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-all',
      hasContent
        ? 'bg-emerald-900/40 border-emerald-600/60 text-emerald-300 hover:bg-emerald-900/60'
        : 'bg-gray-800 border-gray-700 text-gray-300 hover:bg-gray-700'
    ]">
    <!-- Pencil icon -->
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
    </svg>
    Write-up
    <!-- Green dot if content exists -->
    <span v-if="hasContent" class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0"></span>
  </button>

  <!-- ── Slide-in Panel (Teleport to body) ── -->
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition name="fade">
      <div v-if="open"
        class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm"
        @click="closePanel"/>
    </Transition>

    <!-- Panel -->
    <Transition name="slide">
      <div v-if="open"
        class="fixed top-0 right-0 bottom-0 z-50 w-full max-w-2xl bg-gray-900 border-l border-gray-700 flex flex-col shadow-2xl"
        @click.stop>

        <!-- Panel Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800 flex-shrink-0">
          <div>
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                :style="{ backgroundColor: accentColor + '33', border: '1px solid ' + accentColor + '66' }">
                <span class="text-sm">{{ stepIcon }}</span>
              </div>
              <div>
                <h2 class="text-white font-semibold text-base">{{ stepLabel }} — Write-up</h2>
                <p class="text-gray-500 text-xs mt-0.5">{{ studyName }}</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <!-- Language toggle -->
            <div class="flex items-center bg-gray-800 border border-gray-700 rounded-lg overflow-hidden text-xs">
              <button type="button" @click="lang = 'en'"
                :class="['px-3 py-1.5 font-medium transition-colors', lang === 'en' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white']">
                EN
              </button>
              <button type="button" @click="lang = 'ar'"
                :class="['px-3 py-1.5 font-medium transition-colors', lang === 'ar' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white']">
                AR
              </button>
            </div>
            <button type="button" @click="closePanel"
              class="text-gray-500 hover:text-white transition-colors p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Panel Body — scrollable -->
        <div class="flex-1 overflow-y-auto">

          <!-- Auto Summary Section -->
          <div v-if="summaryRows.length > 0 || summaryChartData" class="px-6 py-4 border-b border-gray-800">
            <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-3">
              📊 Auto Summary — {{ stepLabel }}
            </p>

            <!-- Summary Table -->
            <div v-if="summaryRows.length > 0" class="overflow-x-auto rounded-lg border border-gray-700 mb-4">
              <table class="w-full text-xs">
                <thead class="bg-gray-800 border-b border-gray-700">
                  <tr>
                    <th v-for="col in summaryColumns" :key="col.key"
                      :class="['py-2 px-3 font-semibold text-gray-400 uppercase tracking-wide', col.align === 'right' ? 'text-right' : 'text-left']">
                      {{ col.label }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, ri) in summaryRows" :key="ri"
                    class="hover:bg-gray-800/40">
                    <td v-for="col in summaryColumns" :key="col.key"
                      :class="['py-2 px-3', col.align === 'right' ? 'text-right' : 'text-left', col.highlight ? 'font-semibold text-white' : 'text-gray-300']">
                      {{ row[col.key] }}
                    </td>
                  </tr>
                </tbody>
                <!-- Totals row -->
                <tfoot v-if="summaryTotals" class="border-t-2 border-gray-600 bg-gray-800/60">
                  <tr>
                    <td v-for="col in summaryColumns" :key="col.key"
                      :class="['py-2 px-3 font-bold text-xs', col.align === 'right' ? 'text-right' : 'text-left']"
                      :style="col.totalColor ? { color: col.totalColor } : { color: '#9ca3af' }">
                      {{ summaryTotals[col.key] ?? '' }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Category breakdown pills -->
            <div v-if="categoryBreakdown.length > 0" class="grid grid-cols-2 gap-2">
              <div v-for="cat in categoryBreakdown" :key="cat.label"
                class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 flex items-center justify-between">
                <span class="text-gray-400 text-xs">{{ cat.label }}</span>
                <span class="font-semibold text-xs" :style="{ color: cat.color }">{{ cat.value }}</span>
              </div>
            </div>
          </div>

          <!-- Write-up Text Area -->
          <div class="px-6 py-4">
            <div class="flex items-center justify-between mb-2">
              <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest">
                ✍️ Your Analysis
              </p>
              <!-- Save status indicator -->
              <span v-if="saveStatus === 'saving'" class="text-xs text-gray-500 flex items-center gap-1">
                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Saving...
              </span>
              <span v-else-if="saveStatus === 'saved'" class="text-xs text-emerald-400 flex items-center gap-1">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                </svg>
                Saved
              </span>
            </div>

            <textarea
              v-model="localText"
              @input="onInput"
              :dir="lang === 'ar' ? 'rtl' : 'ltr'"
              :placeholder="lang === 'ar'
                ? `اكتب تحليلك لقسم ${stepLabel} هنا...`
                : `Write your analysis for the ${stepLabel} section here...\n\nTip: Mention key observations, risks, assumptions, and recommendations.`"
              rows="16"
              class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm leading-relaxed resize-none focus:outline-none focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
              :class="lang === 'ar' ? 'text-right font-arabic' : ''"
            ></textarea>

            <p class="text-gray-600 text-xs mt-2 text-right">{{ localText.length }} characters</p>
          </div>
        </div>

        <!-- Panel Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-800 flex-shrink-0 bg-gray-900">
          <p class="text-gray-600 text-xs">Auto-saved as you type</p>
          <div class="flex items-center gap-3">
            <button type="button" @click="clearText"
              v-if="localText"
              class="text-xs text-gray-500 hover:text-red-400 transition-colors">
              Clear
            </button>
            <button type="button" @click="closePanel"
              class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
              Done
            </button>
          </div>
        </div>

      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps({
  // Identity
  companyId:  { type: [Number, String], required: true },
  studyId:    { type: [Number, String], required: true },
  studyName:  { type: String, default: '' },
  stepKey:    { type: String, required: true }, // e.g. 'manpower', 'expenses'
  stepLabel:  { type: String, required: true }, // e.g. 'Manpower Plan'
  stepIcon:   { type: String, default: '📝' },
  accentColor:{ type: String, default: '#3b82f6' },

  // Existing saved text
  savedText:  { type: String, default: '' },

  // Summary data (passed in by parent)
  summaryColumns:    { type: Array, default: () => [] },
  summaryRows:       { type: Array, default: () => [] },
  summaryTotals:     { type: Object, default: null },
  categoryBreakdown: { type: Array, default: () => [] },
})

// ── State ──────────────────────────────────────────────────────────────
const open       = ref(false)
const lang       = ref('en')
const localText  = ref(props.savedText ?? '')
const saveStatus = ref('') // 'saving' | 'saved' | ''
let   saveTimer  = null

const hasContent = computed(() => localText.value.trim().length > 0)

// Sync if parent updates savedText after mount
watch(() => props.savedText, (val) => {
  if (val && !localText.value) localText.value = val
})

// ── Auto-save ──────────────────────────────────────────────────────────
function onInput() {
  saveStatus.value = 'saving'
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => saveWriteup(), 1200)
}

function apiFetch(url, body) {
  const xsrf = document.cookie.split('; ')
    .find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
  return fetch(url, {
    method:      'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'Accept':        'application/json',
      'X-XSRF-TOKEN':  xsrf ? decodeURIComponent(xsrf) : '',
    },
    body: JSON.stringify(body),
  })
}

async function saveWriteup() {
  try {
    await apiFetch(
      `/portfolio-companies/${props.companyId}/financial-studies/${props.studyId}/writeup`,
      { step: props.stepKey, text: localText.value, lang: lang.value }
    )
    saveStatus.value = 'saved'
    setTimeout(() => { saveStatus.value = '' }, 2000)
  } catch (e) {
    console.error('Writeup save failed', e)
    saveStatus.value = ''
  }
}

function closePanel() {
  // Save before closing if there's unsaved content
  if (saveStatus.value === 'saving') {
    clearTimeout(saveTimer)
    saveWriteup()
  }
  open.value = false
}

function clearText() {
  if (confirm('Clear the write-up for this section?')) {
    localText.value = ''
    saveWriteup()
  }
}
</script>

<style scoped>
/* Slide-in animation */
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

/* Fade backdrop */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>