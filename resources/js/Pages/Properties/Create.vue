<template>
  <AuthenticatedLayout>
    <div class="p-6" style="background:var(--fv-bg); min-height:100vh;">

      <!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('company.properties.index', company.id)"
          class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
          style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);"
          onmouseover="this.style.borderColor='#1490A8'" onmouseout="this.style.borderColor='var(--fv-border)'">
          <svg class="w-4 h-4 fv-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </Link>
        <div>
          <h1 class="text-xl font-bold fv-text-primary">Add New Property</h1>
          <p class="text-xs fv-text-muted">{{ company.name }}</p>
        </div>
      </div>

      <!-- ── FLASH ERROR ────────────────────────────────────────────────── -->
      <div v-if="Object.keys(errors).length"
        class="mb-5 px-4 py-3 rounded-xl text-sm"
        style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#f87171;">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="space-y-0.5 list-disc list-inside">
          <li v-for="(msg, field) in errors" :key="field">{{ msg }}</li>
        </ul>
      </div>

      <!-- ══════════════════════════════════════════════════════════════
           STEP 1 — NATURE SELECTOR
      ══════════════════════════════════════════════════════════════════ -->
      <div class="fv-card mb-5">
        <h2 class="text-sm font-bold fv-text-muted uppercase tracking-widest mb-4">Property Nature</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <button v-for="n in natures" :key="n.value"
            type="button"
            @click="form.nature = n.value; resetUnits()"
            class="rounded-xl p-4 flex flex-col items-center gap-2 text-center transition-all duration-150 border-2"
            :style="form.nature === n.value
              ? `border-color:${n.color}; background:${n.color}14;`
              : 'border-color:var(--fv-border); background:rgba(11,26,48,0.4);'">
            <span class="text-2xl">{{ n.icon }}</span>
            <span class="text-sm font-semibold"
              :style="form.nature === n.value ? `color:${n.color};` : 'color:var(--fv-text-muted,#6B96B8);'">
              {{ n.label }}
            </span>
            <span class="text-xs leading-snug"
              :style="form.nature === n.value ? 'color:var(--fv-text,#E2E8F0);' : 'color:var(--fv-text-muted,#6B96B8);'">
              {{ n.desc }}
            </span>
          </button>
        </div>
      </div>

      <!-- Form content — shown once nature is selected -->
      <div v-if="form.nature">

        <!-- ══════════════════════════════════════════════════════════════
             SECTION A — PARENT / PROPERTY DETAILS
        ══════════════════════════════════════════════════════════════════ -->
        <div class="fv-card mb-5">
          <div class="flex items-center gap-2 mb-5">
            <div class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white"
              style="background:var(--fv-blue,#1490A8);">A</div>
            <h2 class="text-sm font-bold fv-text-primary">
              {{ isUnit ? 'Property Details' : natureLabel(form.nature) + ' Details' }}
            </h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Property Name -->
            <div class="lg:col-span-2">
              <label class="fv-label">Property Name <span class="text-red-400">*</span></label>
              <input v-model="form.property_name" class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                placeholder="e.g. Nile Tower — Unit 5B" />
              <p v-if="errors.property_name" class="err-msg">{{ errors.property_name }}</p>
            </div>

            <!-- Ownership -->
            <div>
              <label class="fv-label">Ownership <span class="text-red-400">*</span></label>
              <select v-model="form.ownership" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                <option value="">— Select —</option>
                <option v-for="o in ownershipOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
              <p v-if="errors.ownership" class="err-msg">{{ errors.ownership }}</p>
            </div>

            <!-- Property Code -->
            <div>
              <label class="fv-label">Property Code
                <span class="fv-text-muted text-xs">(auto if blank)</span>
              </label>
              <input v-model="form.property_code" class="fv-input w-full rounded-lg px-3 py-2 text-sm font-mono"
                placeholder="e.g. UNT-001A" />
              <p v-if="errors.property_code" class="err-msg">{{ errors.property_code }}</p>
            </div>

            <!-- Country -->
            <div>
              <label class="fv-label">Country</label>
              <input v-model="form.country" class="fv-input w-full rounded-lg px-3 py-2 text-sm" readonly
                style="opacity:0.6; cursor:not-allowed;" />
            </div>

            <!-- Governorate -->
            <div>
              <label class="fv-label">Governorate</label>
              <select v-model="form.governorate" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                <option value="">— Select —</option>
                <option v-for="g in governorates" :key="g" :value="g">{{ g }}</option>
              </select>
            </div>

            <!-- Province -->
            <div>
              <label class="fv-label">Province / District</label>
              <input v-model="form.province" class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                placeholder="e.g. New Cairo, Maadi, 6th of October" />
            </div>

            <!-- Location — full width -->
            <div class="lg:col-span-2" v-if="isUnit">
              <label class="fv-label">Location / Address</label>
              <input v-model="form.location" class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                placeholder="Street, building number, landmark…" />
            </div>

          </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             SECTION B — FINANCIAL DETAILS (Unit nature only)
        ══════════════════════════════════════════════════════════════════ -->
        <div v-if="isUnit" class="fv-card mb-5">
          <div class="flex items-center gap-2 mb-5">
            <div class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white"
              style="background:var(--fv-gold,#BA7517);">B</div>
            <h2 class="text-sm font-bold fv-text-primary">Financial Details</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Category -->
            <div>
              <label class="fv-label">Category</label>
              <select v-model="form.property_category_id" @change="form.property_type_id = ''"
                class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                <option value="">— Select Category —</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.category_name }}</option>
              </select>
            </div>

            <!-- Type (filtered by category) -->
            <div>
              <label class="fv-label">Type</label>
              <select v-model="form.property_type_id" class="fv-select w-full rounded-lg px-3 py-2 text-sm"
                :disabled="!form.property_category_id">
                <option value="">— Select Type —</option>
                <option v-for="t in typesForCategory(form.property_category_id)" :key="t.id" :value="t.id">
                  {{ t.type_name }}
                </option>
              </select>
            </div>

            <!-- Area + UOM -->
            <div class="flex gap-2">
              <div class="flex-1">
                <label class="fv-label">Area</label>
                <input v-model="form.area" type="number" min="0" step="0.01"
                  class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
              </div>
              <div style="width:7rem;">
                <label class="fv-label">UOM</label>
                <select v-model="form.unit_of_measurement" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                  <option v-for="u in uomOptions" :key="u" :value="u">{{ u }}</option>
                </select>
              </div>
            </div>

            <!-- Acquisition Cost + Currency, Acquisition Date, Book Value,
                 depreciation fields, and Market Value — none of these apply
                 to Usufruct or Managed For Others (not company assets). -->
            <template v-if="!hideAssetFields(form.ownership)">

              <!-- Acquisition Cost + Currency -->
              <div class="flex gap-2">
                <div class="flex-1">
                  <label class="fv-label">Acquisition Cost
                    <span v-if="assetFieldsRequired(form.ownership)" class="text-red-400">*</span>
                  </label>
                  <input v-model="form.acquisition_cost" type="number" min="0" step="0.01"
                    @input="recalcBookValue(form)"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                  <p v-if="errors.acquisition_cost" class="err-msg">{{ errors.acquisition_cost }}</p>
                </div>
                <div style="width:6rem;">
                  <label class="fv-label">Currency</label>
                  <select v-model="form.currency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                    <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                  </select>
                </div>
              </div>

              <!-- Acquisition Date — MM/YYYY PICKER -->
              <div>
                <label class="fv-label">Acquisition Date
                  <span v-if="assetFieldsRequired(form.ownership)" class="text-red-400">*</span>
                </label>
                <input 
                  type="month" 
                  v-model="form.acquisition_date" 
                  class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                />
                <p v-if="errors.acquisition_date" class="err-msg">{{ errors.acquisition_date }}</p>
              </div>

              <!-- Accumulated Depreciation — NOT for land, now BEFORE Book Value -->
              <div v-if="form.nature !== 'land'">
                <label class="fv-label">Accumulated Depreciation</label>
                <input v-model="form.accumulated_depreciation" type="number" min="0" step="0.01"
                  @input="recalcBookValue(form)"
                  class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
              </div>

              <!-- Book Value — auto-calculated: Acquisition Cost − Accumulated Depreciation -->
              <div>
                <label class="fv-label">Book Value <span class="fv-text-muted text-xs">(auto)</span></label>
                <input :value="form.book_value" type="number" readonly
                  class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"
                  style="opacity:0.6; cursor:not-allowed;" />
              </div>

              <!-- Depreciation Duration + Monthly Depreciation — NOT for land -->
              <template v-if="form.nature !== 'land'">
                <div>
                  <label class="fv-label">Depreciation Duration (months)
                    <span v-if="assetFieldsRequired(form.ownership)" class="text-red-400">*</span>
                  </label>
                  <input v-model="form.depreciation_duration_months" type="number"
                    :min="assetFieldsRequired(form.ownership) ? 1 : 0"
                    @input="recalcMonthlyDepreciation(form)"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="e.g. 240" />
                  <p v-if="errors.depreciation_duration_months" class="err-msg">{{ errors.depreciation_duration_months }}</p>
                </div>
                <div>
                  <label class="fv-label">Monthly Depreciation <span class="fv-text-muted text-xs">(auto)</span></label>
                  <input :value="form.monthly_depreciation" type="number" readonly
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"
                    style="opacity:0.6; cursor:not-allowed;" />
                </div>
              </template>

              <!-- No depreciation notice for land -->
              <div v-else class="lg:col-span-3">
                <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm"
                  style="background:rgba(20,184,166,0.07); border:1px solid rgba(20,184,166,0.2); color:#2dd4bf;">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Land does not depreciate — depreciation fields are not applicable.
                </div>
              </div>

            </template>

            <!-- Not a company asset — Usufruct / Managed For Others -->
            <div v-else class="lg:col-span-3">
              <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm"
                style="background:rgba(20,184,166,0.07); border:1px solid rgba(20,184,166,0.2); color:#2dd4bf;">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Usufruct and Managed For Others units are not company assets — acquisition, depreciation, and valuation fields don't apply.
              </div>
            </div>

          </div>

          <!-- ── Market Value Repeater ────────────────────────────────── -->
          <div v-if="!hideAssetFields(form.ownership)" class="mt-6 pt-5" style="border-top:1px solid var(--fv-border);">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm font-semibold fv-text-primary">Current Market Value</p>
                <p class="text-xs fv-text-muted mt-0.5">Record market valuations over time — latest entry = current value.</p>
              </div>
              <button type="button" @click="addMarketValue(form.market_values)"
                class="btn-xs btn-outline flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Entry
              </button>
            </div>
            <div v-if="form.market_values.length" class="space-y-2">
              <div v-for="(mv, idx) in form.market_values" :key="idx"
                class="flex gap-3 items-end p-3 rounded-lg"
                style="background:rgba(11,26,48,0.5); border:1px solid var(--fv-border);">
                <div class="flex-1">
                  <label class="fv-label">Value ({{ form.currency }})</label>
                  <input v-model="mv.market_value" type="number" min="0" step="0.01"
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                </div>
                <div style="width:10rem;">
                  <label class="fv-label">Date</label>
                  <input 
                    type="month" 
                    v-model="mv.value_date" 
                    class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <button type="button" @click="form.market_values.splice(idx, 1)"
                  class="mb-0.5 fv-action-btn fv-action-btn-danger flex-shrink-0">✕</button>
              </div>
            </div>
            <p v-else class="text-xs fv-text-muted py-2">No market value entries yet.</p>
          </div>

        </div>

        <!-- ══════════════════════════════════════════════════════════════
             SECTION B — UNITS REPEATER (Building / Land / Complex)
        ══════════════════════════════════════════════════════════════════ -->
        <div v-if="!isUnit" class="mb-5">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white"
                style="background:var(--fv-gold,#BA7517);">B</div>
              <div>
                <h2 class="text-sm font-bold fv-text-primary">
                  {{ form.nature === 'land' ? 'Slots & Built Units' : 'Units' }}
                </h2>
                <p class="text-xs fv-text-muted">
                  {{ form.nature === 'land'
                    ? 'Add land slots (no depreciation) or built units with full financials.'
                    : 'Add all units inside this ' + natureLabel(form.nature) + '.' }}
                </p>
              </div>
            </div>
            <button type="button" @click="addUnit"
              class="btn-sm btn-teal flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add {{ form.nature === 'land' ? 'Slot / Unit' : 'Unit' }}
            </button>
          </div>

          <!-- Empty state -->
          <div v-if="form.units.length === 0"
            class="rounded-xl py-10 flex flex-col items-center gap-3 text-center"
            style="background:var(--fv-bg-card,#112240); border:2px dashed var(--fv-border);">
            <span class="text-3xl">{{ form.nature === 'land' ? '🌿' : '🏠' }}</span>
            <p class="fv-text-muted text-sm">No units added yet.</p>
            <button type="button" @click="addUnit" class="btn-sm btn-outline">+ Add First Unit</button>
          </div>

          <!-- Unit cards -->
          <div v-else class="space-y-4">
            <div v-for="(unit, idx) in form.units" :key="idx"
              class="rounded-xl overflow-hidden"
              style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border);">

              <!-- Unit card header -->
              <div class="flex items-center gap-3 px-4 py-3 cursor-pointer"
                style="background:rgba(11,26,48,0.5); border-bottom:1px solid var(--fv-border);"
                @click="expandedUnit = expandedUnit === idx ? null : idx">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                  style="background:rgba(20,144,168,0.15); color:#48C4D8; border:1px solid rgba(20,144,168,0.25);">
                  {{ idx + 1 }}
                </div>
                <div class="flex-1">
                  <span class="fv-text-primary font-semibold text-sm">
                    {{ unit.unit_name || 'New Unit' }}
                  </span>
                  <span v-if="form.nature === 'land'" class="ml-2 text-xs px-2 py-0.5 rounded-full"
                    :style="unit.slot_type === 'land_slot'
                      ? 'background:rgba(20,184,166,0.1); color:#2dd4bf;'
                      : 'background:rgba(20,144,168,0.1); color:#48C4D8;'">
                    {{ unit.slot_type === 'land_slot' ? 'Land Slot' : 'Built Unit' }}
                  </span>
                </div>
                <button type="button" @click.stop="form.units.splice(idx, 1)"
                  class="fv-action-btn fv-action-btn-danger">✕</button>
                <svg class="w-4 h-4 fv-text-muted transition-transform duration-200"
                  :class="expandedUnit === idx ? 'rotate-180' : ''"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>

              <!-- Unit card body -->
              <div v-if="expandedUnit === idx" class="p-4">

                <!-- Slot type toggle (land only) -->
                <div v-if="form.nature === 'land'" class="mb-4 flex gap-3">
                  <button type="button"
                    @click="unit.slot_type = 'land_slot'"
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-all"
                    :style="unit.slot_type === 'land_slot'
                      ? 'border-color:#14b8a6; background:rgba(20,184,166,0.12); color:#2dd4bf;'
                      : 'border-color:var(--fv-border); color:var(--fv-text-muted,#6B96B8); background:transparent;'">
                    🌿 Land Slot
                    <span class="block text-xs font-normal opacity-70">No depreciation</span>
                  </button>
                  <button type="button"
                    @click="unit.slot_type = 'built_unit'"
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-all"
                    :style="unit.slot_type === 'built_unit'
                      ? 'border-color:#1490A8; background:rgba(20,144,168,0.12); color:#48C4D8;'
                      : 'border-color:var(--fv-border); color:var(--fv-text-muted,#6B96B8); background:transparent;'">
                    🏗️ Built Unit
                    <span class="block text-xs font-normal opacity-70">With depreciation</span>
                  </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                  <!-- Unit Name -->
                  <div>
                    <label class="fv-label">Unit Name <span class="text-red-400">*</span></label>
                    <input v-model="unit.unit_name" class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                      :placeholder="form.nature === 'land' ? 'e.g. Slot A-1 or Office Block' : 'e.g. Apartment 3B'" />
                  </div>

                  <!-- Unit Code -->
                  <div>
                    <label class="fv-label">Unit Code <span class="fv-text-muted text-xs">(optional)</span></label>
                    <input v-model="unit.unit_code" class="fv-input w-full rounded-lg px-3 py-2 text-sm font-mono"
                      placeholder="e.g. BLD-A-3B" />
                  </div>

                  <!-- Ownership override -->
                  <div>
                    <label class="fv-label">Ownership <span class="fv-text-muted text-xs">(overrides parent)</span></label>
                    <select v-model="unit.ownership" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                      <option value="">— Inherit from parent —</option>
                      <option v-for="o in ownershipOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                  </div>

                  <!-- Category (built_unit only) -->
                  <template v-if="unit.slot_type !== 'land_slot'">
                    <div>
                      <label class="fv-label">Category</label>
                      <select v-model="unit.property_category_id" @change="unit.property_type_id = ''"
                        class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                        <option value="">— Select —</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.category_name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="fv-label">Type</label>
                      <select v-model="unit.property_type_id" class="fv-select w-full rounded-lg px-3 py-2 text-sm"
                        :disabled="!unit.property_category_id">
                        <option value="">— Select —</option>
                        <option v-for="t in typesForCategory(unit.property_category_id)" :key="t.id" :value="t.id">
                          {{ t.type_name }}
                        </option>
                      </select>
                    </div>
                  </template>

                  <!-- Area + UOM -->
                  <div class="flex gap-2">
                    <div class="flex-1">
                      <label class="fv-label">Area</label>
                      <input v-model="unit.area" type="number" min="0" step="0.01"
                        class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                    </div>
                    <div style="width:7rem;">
                      <label class="fv-label">UOM</label>
                      <select v-model="unit.unit_of_measurement" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                        <option v-for="u in uomOptions" :key="u" :value="u">{{ u }}</option>
                      </select>
                    </div>
                  </div>

                  <!-- Acquisition Cost + Currency, Acquisition Date, Book Value,
                       depreciation fields, and Market Value — none of these apply
                       to Usufruct or Managed For Others (not company assets),
                       using this unit's effective ownership (its own override,
                       or inherited from the parent property). -->
                  <template v-if="!hideAssetFields(effectiveOwnership(unit))">

                    <!-- Acquisition Cost + Currency -->
                    <div class="flex gap-2">
                      <div class="flex-1">
                        <label class="fv-label">Acquisition Cost
                          <span v-if="assetFieldsRequired(effectiveOwnership(unit))" class="text-red-400">*</span>
                        </label>
                        <input v-model="unit.acquisition_cost" type="number" min="0" step="0.01"
                          @input="recalcBookValue(unit)"
                          class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                        <p v-if="errors[`units.${idx}.acquisition_cost`]" class="err-msg">{{ errors[`units.${idx}.acquisition_cost`] }}</p>
                      </div>
                      <div style="width:6rem;">
                        <label class="fv-label">Currency</label>
                        <select v-model="unit.currency" class="fv-select w-full rounded-lg px-3 py-2 text-sm">
                          <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                        </select>
                      </div>
                    </div>

                    <!-- Acquisition Date — MM/YYYY PICKER -->
                    <div>
                      <label class="fv-label">Acquisition Date
                        <span v-if="assetFieldsRequired(effectiveOwnership(unit))" class="text-red-400">*</span>
                      </label>
                      <input 
                        type="month" 
                        v-model="unit.acquisition_date" 
                        class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                      />
                      <p v-if="errors[`units.${idx}.acquisition_date`]" class="err-msg">{{ errors[`units.${idx}.acquisition_date`] }}</p>
                    </div>

                    <!-- Accumulated Depreciation — built_unit only, now BEFORE Book Value -->
                    <div v-if="unit.slot_type !== 'land_slot'">
                      <label class="fv-label">Accumulated Depreciation</label>
                      <input v-model="unit.accumulated_depreciation" type="number" min="0" step="0.01"
                        @input="recalcBookValue(unit)"
                        class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                    </div>

                    <!-- Book Value — auto-calculated: Acquisition Cost − Accumulated Depreciation -->
                    <div>
                      <label class="fv-label">Book Value <span class="fv-text-muted text-xs">(auto)</span></label>
                      <input :value="unit.book_value" type="number" readonly
                        class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"
                        style="opacity:0.6; cursor:not-allowed;" />
                    </div>

                    <!-- Depreciation Duration + Monthly Depreciation — built_unit only -->
                    <template v-if="unit.slot_type !== 'land_slot'">
                      <div>
                        <label class="fv-label">Depreciation Duration (months)
                          <span v-if="assetFieldsRequired(effectiveOwnership(unit))" class="text-red-400">*</span>
                        </label>
                        <input v-model="unit.depreciation_duration_months" type="number"
                          :min="assetFieldsRequired(effectiveOwnership(unit)) ? 1 : 0"
                          @input="recalcMonthlyDepreciation(unit)"
                          class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="e.g. 240" />
                        <p v-if="errors[`units.${idx}.depreciation_duration_months`]" class="err-msg">{{ errors[`units.${idx}.depreciation_duration_months`] }}</p>
                      </div>
                      <div>
                        <label class="fv-label">Monthly Depreciation <span class="fv-text-muted text-xs">(auto)</span></label>
                        <input :value="unit.monthly_depreciation" type="number" readonly
                          class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00"
                          style="opacity:0.6; cursor:not-allowed;" />
                      </div>
                    </template>

                    <!-- No depreciation label for land slot -->
                    <div v-else class="sm:col-span-2 lg:col-span-3">
                      <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-xs"
                        style="background:rgba(20,184,166,0.07); border:1px solid rgba(20,184,166,0.2); color:#2dd4bf;">
                        🌿 Land slot — depreciation not applicable.
                      </div>
                    </div>

                  </template>

                  <!-- Not a company asset — Usufruct / Managed For Others -->
                  <div v-else class="sm:col-span-2 lg:col-span-3">
                    <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-xs"
                      style="background:rgba(20,184,166,0.07); border:1px solid rgba(20,184,166,0.2); color:#2dd4bf;">
                      Usufruct and Managed For Others units are not company assets — acquisition, depreciation, and valuation fields don't apply.
                    </div>
                  </div>

                </div>

                <!-- Unit Market Value Repeater -->
                <div v-if="!hideAssetFields(effectiveOwnership(unit))" class="mt-5 pt-4" style="border-top:1px solid var(--fv-border);">
                  <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-semibold fv-text-primary">Market Value History</p>
                    <button type="button" @click="addMarketValue(unit.market_values)"
                      class="btn-xs btn-outline flex items-center gap-1">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                      </svg>
                      Add Entry
                    </button>
                  </div>
                  <div v-if="unit.market_values.length" class="space-y-2">
                    <div v-for="(mv, mIdx) in unit.market_values" :key="mIdx"
                      class="flex gap-3 items-end p-3 rounded-lg"
                      style="background:rgba(11,26,48,0.5); border:1px solid var(--fv-border);">
                      <div class="flex-1">
                        <label class="fv-label">Value</label>
                        <input v-model="mv.market_value" type="number" min="0" step="0.01"
                          class="fv-input w-full rounded-lg px-3 py-2 text-sm" placeholder="0.00" />
                      </div>
                      <div style="width:10rem;">
                        <label class="fv-label">Date</label>
                        <input 
                          type="month" 
                          v-model="mv.value_date" 
                          class="fv-input w-full rounded-lg px-3 py-2 text-sm"
                        />
                      </div>
                      <button type="button" @click="unit.market_values.splice(mIdx, 1)"
                        class="mb-0.5 fv-action-btn fv-action-btn-danger flex-shrink-0">✕</button>
                    </div>
                  </div>
                  <p v-else class="text-xs fv-text-muted">No entries yet.</p>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- ── DESCRIPTION (TAGS) ─────────────────────────────────────── -->
        <div class="fv-card mb-5">
          <div class="flex items-center gap-2 mb-4">
            <div class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white"
              style="background:var(--fv-blue,#1490A8);">D</div>
            <h2 class="text-sm font-bold fv-text-primary">Description</h2>
          </div>
          <TagInput
            :company-id="company.id"
            v-model="descriptionTags"
          />
        </div>

        <!-- ── SUBMIT BAR ─────────────────────────────────────────────── -->
        <div class="sticky bottom-4 flex items-center justify-end gap-3 px-4 py-3 rounded-xl shadow-xl"
          style="background:var(--fv-bg-card,#112240); border:1px solid var(--fv-border); backdrop-filter:blur(8px);">
          <Link :href="route('company.properties.index', company.id)" class="btn-sm btn-ghost">
            Cancel
          </Link>
          <button type="button" @click="submit" :disabled="submitting"
            class="btn-sm btn-teal flex items-center gap-2"
            :style="submitting ? 'opacity:0.6; cursor:not-allowed;' : ''">
            <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            {{ submitting ? 'Saving…' : 'Save Property' }}
          </button>
        </div>

      </div>

      <!-- Placeholder when no nature selected -->
      <div v-else
        class="rounded-2xl py-20 flex flex-col items-center gap-4 text-center"
        style="background:var(--fv-bg-card,#112240); border:2px dashed var(--fv-border);">
        <span class="text-4xl">🏢</span>
        <p class="fv-text-muted">Select a property nature above to begin.</p>
      </div>

    </div>
  </AuthenticatedLayout>
</template>


<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TagInput from '@/Components/TagInput.vue'

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps({
  company:          Object,
  categories:       { type: Array, default: () => [] },
  ownershipOptions: { type: Array, default: () => [] },
  governorates:     { type: Array, default: () => [] },
  uomOptions:       { type: Array, default: () => [] },
  currencyOptions:  { type: Array, default: () => [] },
})

const page = usePage()
const errors = computed(() => page.props.errors || {})

// ── Natures ────────────────────────────────────────────────────────────
const natures = [
  { value: 'unit',     label: 'Unit',     icon: '🏠', color: '#1490A8', desc: 'Standalone apartment, office, or shop' },
  { value: 'building', label: 'Building', icon: '🏗️', color: '#BA7517', desc: 'Multi-unit building with floors' },
  { value: 'land',     label: 'Land',     icon: '🌿', color: '#14b8a6', desc: 'Land parcel — whole or divided into slots' },
  { value: 'complex',  label: 'Complex',  icon: '🏪', color: '#8b5cf6', desc: 'Retail / food / service complex' },
]

// ── Form state ─────────────────────────────────────────────────────────
const form = ref({
  nature:                       '',
  property_name:                '',
  property_code:                '',
  ownership:                    '',
  country:                      'Egypt',
  governorate:                  '',
  province:                     '',
  location:                     '',
  // unit financials
  property_category_id:         '',
  property_type_id:             '',
  area:                         '',
  unit_of_measurement:          'm²',
  acquisition_cost:             '',
  currency:                     'EGP',
  acquisition_date:             '',
  book_value:                   '',
  accumulated_depreciation:     '',
  monthly_depreciation:         '',
  depreciation_duration_months: '',
  market_values:                [],
  // child units
  units: [],
})

const isUnit      = computed(() => form.value.nature === 'unit')
const submitting  = ref(false)
const expandedUnit = ref(null)
const descriptionTags = ref([])

// ── Helpers ────────────────────────────────────────────────────────────
const natureLabel = (n) => ({ unit:'Unit', building:'Building', land:'Land', complex:'Complex' }[n] || n)

// Usufruct and "Managed For Others" units are not company assets — the
// whole acquisition/depreciation/valuation block doesn't apply to them.
const hideAssetFields = (ownership) => ownership === 'usufruct' || ownership === 'managed'

// Only Fully Owned / Owned with Installments require Acquisition Date,
// Acquisition Cost, and a minimum 1-month Depreciation Duration.
const assetFieldsRequired = (ownership) => ownership === 'fully_owned' || ownership === 'installments'

// A child unit's own ownership override, or — if left blank ("inherit
// from parent") — the parent property's ownership. Building/Land/Complex
// parents carry no financials of their own, so a unit that inherits its
// ownership also inherits whether the asset fields apply to it.
const effectiveOwnership = (unit) => unit.ownership || form.value.ownership

// Book Value = Acquisition Cost − Accumulated Depreciation, auto-computed
// whenever either input changes. Also refreshes Monthly Depreciation,
// since that's derived from Book Value.
const recalcBookValue = (target) => {
  const cost    = parseFloat(target.acquisition_cost) || 0
  const accumDep = parseFloat(target.accumulated_depreciation) || 0
  target.book_value = (cost - accumDep).toFixed(2)
  recalcMonthlyDepreciation(target)
}

// Monthly Depreciation = Book Value ÷ Depreciation Duration (months),
// auto-computed. 0 (not an error) when duration is blank or 0.
const recalcMonthlyDepreciation = (target) => {
  const bookValue = parseFloat(target.book_value) || 0
  const duration  = parseFloat(target.depreciation_duration_months) || 0
  target.monthly_depreciation = duration > 0 ? (bookValue / duration).toFixed(2) : '0.00'
}

const typesForCategory = (catId) => {
  if (!catId) return []
  const cat = props.categories.find(c => c.id === catId || c.id === parseInt(catId))
  return cat?.types || []
}

const newUnit = () => ({
  slot_type:                    'built_unit',
  unit_name:                    '',
  unit_code:                    '',
  ownership:                    '',
  location:                     '',
  property_category_id:         '',
  property_type_id:             '',
  area:                         '',
  unit_of_measurement:          'm²',
  acquisition_cost:             '',
  currency:                     'EGP',
  acquisition_date:             '',
  book_value:                   '',
  accumulated_depreciation:     '',
  monthly_depreciation:         '',
  depreciation_duration_months: '',
  market_values:                [],
})

const addUnit = () => {
  form.value.units.push(newUnit())
  expandedUnit.value = form.value.units.length - 1
}

const resetUnits = () => {
  form.value.units = []
  form.value.market_values = []
  expandedUnit.value = null
}

const addMarketValue = (arr) => {
  arr.push({ market_value: '', value_date: '' })
}

// ── Submit ─────────────────────────────────────────────────────────────
const submit = () => {
  submitting.value = true
  router.post(
    route('company.properties.store', props.company.id),
    {
      ...form.value,
      description_tag_ids: descriptionTags.value.map((t) => t.id),
    },
    {
      preserveScroll: true,
      onError:   () => { submitting.value = false },
      onSuccess: () => { submitting.value = false },
    }
  )
}
</script>

<style scoped>
.fv-card {
  background: var(--fv-bg-card, #112240);
  border: 1px solid var(--fv-border, #1B3558);
  border-radius: 0.875rem;
  padding: 1.5rem;
}

.fv-label {
  display: block;
  font-size: 0.75rem;
  color: var(--fv-text-muted, #6B96B8);
  margin-bottom: 0.375rem;
  font-weight: 500;
}

.fv-select {
  background-color: var(--fv-bg-input, #0D1E38);
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-primary, #E2E8F0);
  outline: none;
  transition: border-color 0.15s ease;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B96B8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.6rem center;
  background-size: 1rem;
  padding-right: 2rem;
}
.fv-select:focus { border-color: #1490A8; }
.fv-select:disabled { opacity: 0.45; cursor: not-allowed; }

.err-msg {
  font-size: 0.75rem;
  color: #f87171;
  margin-top: 0.25rem;
}

/* ── Picker button inherits fv-input look ── */
.picker-btn {
  background-color: var(--fv-bg-input, #0D1E38);
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-primary, #E2E8F0);
  transition: border-color 0.15s ease;
  cursor: pointer;
}
.picker-btn:hover { border-color: #1490A8; }
.picker-placeholder { color: var(--fv-text-muted, #6B96B8); }

.btn-teal {
  background: var(--fv-blue, #1490A8);
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-teal:hover { background: #117a90; }

.btn-ghost {
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-muted, #6B96B8);
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.15s ease;
  text-decoration: none;
  display: inline-flex; align-items: center;
}
.btn-ghost:hover { border-color: #1490A8; color: #48C4D8; }

.btn-outline {
  background: transparent;
  border: 1px solid var(--fv-border, #1B3558);
  color: var(--fv-text-primary, #E2E8F0);
  border-radius: 0.375rem;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.15s ease;
  text-decoration: none;
  display: inline-flex; align-items: center;
}
.btn-outline:hover { border-color: #1490A8; color: #48C4D8; }

.btn-sm  { font-size: 0.875rem; padding: 0.375rem 0.875rem; }
.btn-xs  { font-size: 0.75rem;  padding: 0.25rem 0.625rem;  border-radius: 0.375rem; }

.fv-action-btn {
  width: 1.75rem; height: 1.75rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 0.375rem;
  border: 1px solid var(--fv-border, #1B3558);
  background: transparent;
  color: var(--fv-text-muted, #6B96B8);
  cursor: pointer; font-size: 0.8rem;
  transition: all 0.15s ease;
}
.fv-action-btn-danger { border-color: rgba(239,68,68,0.3); color: #f87171; }
.fv-action-btn-danger:hover { background: rgba(239,68,68,0.1); }

.rotate-180 { transform: rotate(180deg); }

@keyframes spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
.animate-spin { animation: spin 1s linear infinite; }
</style>