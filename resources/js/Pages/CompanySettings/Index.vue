<template>
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto px-4 py-8">

      <!-- ══ PAGE HEADER ══ -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <nav class="flex items-center gap-2 text-xs mb-2">
            <span class="fv-text-muted">Settings</span>
            <span class="fv-text-muted opacity-40">/</span>
            <span style="color: var(--fv-gold); font-weight:600;">Company Settings</span>
          </nav>
          <h1 class="text-2xl font-bold fv-text-primary">Company Settings</h1>
          <p class="fv-text-label text-sm mt-1">{{ company.trade_name || company.name }}</p>
        </div>
      </div>

      <!-- ══ FLASH ══ -->
      <div v-if="$page.props.flash?.success"
        class="mb-6 px-4 py-3 rounded-lg text-sm"
        style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); color:#34d399;">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="mb-6 px-4 py-3 rounded-lg text-sm"
        style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#f87171;">
        {{ $page.props.flash.error }}
      </div>

      <!-- ══ TAB NAVIGATION ══ -->
      <div class="flex gap-1 mb-6 p-1 rounded-xl overflow-x-auto" style="background:rgba(11,34,64,0.6); border:1px solid var(--fv-border);">
        <button v-for="tab in tabs" :key="tab.key"
          @click="activeTab = tab.key"
          :class="['px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all', activeTab === tab.key ? 'tab-active' : 'tab-inactive']">
          {{ tab.label }}
        </button>
      </div>

      <!-- ══════════════════════════════════════════════════════
           TAB: TENANTS
      ══════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'tenants'">
        <div class="mb-6">
          <h2 class="text-lg font-bold fv-text-primary">Tenants</h2>
          <p class="fv-text-label text-sm mt-1">
            Manage your tenant list. Tenants are linked to rent contracts.
          </p>
        </div>

        <!-- Action bar -->
        <div class="flex flex-wrap items-center gap-3 mb-5">
          <button @click="startAddTenant" class="btn-sm btn-teal flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Tenant
          </button>
        </div>

        <!-- Add tenant form -->
        <div v-if="addingTenant" class="fv-card mb-4">
          <h3 class="fv-text-primary font-semibold mb-4 text-sm">New Tenant</h3>
          <div class="flex flex-wrap gap-3 items-end">

            <!-- Tenant Name -->
            <div class="flex-1 min-w-48">
              <label class="block text-xs fv-text-label mb-1">Tenant Name <span style="color:#f87171;">*</span></label>
              <input v-model="newTenant.customer_name" class="fv-input input-sm w-full rounded-lg" placeholder="e.g. Al Nour Trading Co." />
            </div>

            <!-- Nature -->
            <div style="min-width:9rem;">
              <label class="block text-xs fv-text-label mb-1">Nature</label>
              <select v-model="newTenant.tenant_nature" class="fv-select input-sm w-full rounded-lg"
                @change="handleNatureChange">
                <option value="">— Select —</option>
                <option value="individual">Individual</option>
                <option value="corporate">Corporate</option>
              </select>
            </div>

            <!-- Business Sector -->
            <div class="flex-1 min-w-40">
              <label class="block text-xs fv-text-label mb-1">Business Sector</label>
              <input
                v-model="newTenant.business_sector"
                class="fv-input input-sm w-full rounded-lg"
                placeholder="e.g. Retail"
                :disabled="newTenant.tenant_nature === 'individual'"
                :style="newTenant.tenant_nature === 'individual' ? 'opacity:0.35; cursor:not-allowed;' : ''"
              />
            </div>

            <!-- Related Party -->
            <div class="flex items-center gap-2 pb-1" style="padding-bottom:0.45rem;">
              <label class="text-xs fv-text-label whitespace-nowrap">Related Party</label>
              <button type="button"
                @click="newTenant.is_related_party = !newTenant.is_related_party"
                :style="newTenant.is_related_party
                  ? 'background:var(--fv-blue); border-color:var(--fv-blue);'
                  : 'background:transparent; border-color:var(--fv-border);'"
                class="w-9 h-5 rounded-full border-2 flex items-center transition-all duration-200 relative">
                <span
                  :style="newTenant.is_related_party ? 'transform:translateX(1rem);' : 'transform:translateX(0.1rem);'"
                  class="w-3.5 h-3.5 rounded-full bg-white absolute transition-transform duration-200">
                </span>
              </button>
              <span class="text-xs" :style="newTenant.is_related_party ? 'color:var(--fv-blue);' : 'color:var(--fv-text-muted);'">
                {{ newTenant.is_related_party ? 'Yes' : 'No' }}
              </span>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              <button @click="submitTenant" :disabled="!newTenant.customer_name" class="btn-sm btn-teal">Add</button>
              <button @click="addingTenant = false" class="btn-sm btn-ghost">✕</button>
            </div>
          </div>
        </div>

        <!-- Tenants table -->
        <div class="fv-card">
          <div v-if="props.tenants.length === 0" class="text-center py-12 text-sm fv-text-label">
            No tenants yet. Add your first tenant above.
          </div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-xs" style="color:var(--fv-text-label); border-bottom:1px solid var(--fv-border);">
                <th class="text-left pb-2 pr-4 font-medium">Tenant Name</th>
                <th class="text-left pb-2 pr-4 font-medium">Nature</th>
                <th class="text-left pb-2 pr-4 font-medium">Business Sector</th>
                <th class="text-center pb-2 pr-4 font-medium">Related Party</th>
                <th class="pb-2 w-20"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tenant in props.tenants" :key="tenant.id" style="border-bottom:1px solid rgba(27,53,88,0.4);">

                <!-- Tenant Name -->
                <td class="py-2 pr-4">
                  <div v-if="editingTenant === tenant.id">
                    <input v-model="tenantEdit.customer_name" class="fv-input input-xs w-full rounded-lg" />
                  </div>
                  <span v-else class="fv-text-primary font-medium">{{ tenant.customer_name }}</span>
                </td>

                <!-- Nature -->
                <td class="py-2 pr-4">
                  <div v-if="editingTenant === tenant.id">
                    <select v-model="tenantEdit.tenant_nature" class="fv-select input-sm w-full rounded-lg"
                      @change="handleNatureChange">
                      <option value="">— —</option>
                      <option value="individual">Individual</option>
                      <option value="corporate">Corporate</option>
                    </select>
                  </div>
                  <span v-else class="fv-text-primary text-xs">
                    {{ tenant.tenant_nature ? (tenant.tenant_nature === 'individual' ? 'Individual' : 'Corporate') : '—' }}
                  </span>
                </td>

                <!-- Business Sector -->
                <td class="py-2 pr-4">
                  <div v-if="editingTenant === tenant.id">
                    <input
                      v-model="tenantEdit.business_sector"
                      class="fv-input input-xs w-full rounded-lg"
                      placeholder="Business sector"
                      :disabled="tenantEdit.tenant_nature === 'individual'"
                      :style="tenantEdit.tenant_nature === 'individual' ? 'opacity:0.35; cursor:not-allowed;' : ''"
                    />
                  </div>
                  <span v-else>
                    <span v-if="tenant.business_sector" class="text-xs px-2 py-0.5 rounded"
                      style="background:rgba(11,34,64,0.8); color:var(--fv-text-label); border:1px solid var(--fv-border);">
                      {{ tenant.business_sector }}
                    </span>
                    <span v-else class="fv-text-label text-xs">—</span>
                  </span>
                </td>

                <!-- Related Party -->
                <td class="py-2 pr-4 text-center">
                  <div v-if="editingTenant === tenant.id" class="flex justify-center">
                    <button type="button"
                      @click="tenantEdit.is_related_party = !tenantEdit.is_related_party"
                      :style="tenantEdit.is_related_party
                        ? 'background:var(--fv-blue); border-color:var(--fv-blue);'
                        : 'background:transparent; border-color:var(--fv-border);'"
                      class="w-9 h-5 rounded-full border-2 flex items-center transition-all duration-200 relative">
                      <span
                        :style="tenantEdit.is_related_party ? 'transform:translateX(1rem);' : 'transform:translateX(0.1rem);'"
                        class="w-3.5 h-3.5 rounded-full bg-white absolute transition-transform duration-200">
                      </span>
                    </button>
                  </div>
                  <span v-else class="text-xs fv-text-primary">
                    {{ tenant.is_related_party ? 'Yes' : 'No' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-2">
                  <div v-if="editingTenant === tenant.id" class="flex gap-1 justify-end">
                    <button @click="saveTenant(tenant)" class="btn-xs btn-teal">✓</button>
                    <button @click="editingTenant = null" class="btn-xs btn-ghost">✕</button>
                  </div>
                  <div v-else class="flex gap-1 justify-end">
                    <button @click="startEditTenant(tenant)" class="fv-action-btn">✎</button>
                    <button @click="removeTenant(tenant)" class="fv-action-btn fv-action-btn-danger">✕</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════
           TAB: MANPOWER STRUCTURE
      ══════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'manpower'">
        <div class="mb-6">
          <h2 class="text-lg font-bold fv-text-primary">Manpower Structure</h2>
          <p class="fv-text-label text-sm mt-1">Define HQ departments and the job titles within each department.</p>
        </div>

        <div class="fv-card">
          <h3 class="fv-text-primary font-semibold mb-4">Departments & Titles</h3>
          <div class="space-y-3 mb-4">
            <div v-for="dept in departments" :key="dept.id"
              class="rounded-lg p-3" style="background:rgba(11,34,64,0.5); border:1px solid var(--fv-border);">
              <div v-if="editingDept === dept.id" class="flex flex-wrap gap-2 mb-2">
                <input v-model="deptEdit.department_name" class="fv-input input-sm flex-1 min-w-36 rounded-lg" placeholder="Department name" />
                <select v-model="deptEdit.cost_center" class="fv-select input-sm rounded-lg">
                  <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
                </select>
                <select v-if="deptEdit.cost_center === 'cost_of_service'"
                  v-model="deptEdit.business_unit_name"
                  class="fv-select input-sm rounded-lg"
                  style="min-width:10rem;">
                  <option value="">— No Business Unit —</option>
                  <option v-for="bu in businessUnits" :key="bu" :value="bu">{{ bu }}</option>
                </select>
                <button @click="saveDept(dept)" class="btn-xs btn-teal">✓</button>
                <button @click="editingDept = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <div v-else class="flex items-center gap-2 mb-2">
                <span class="fv-text-primary text-sm font-medium flex-1">{{ dept.department_name }}</span>
                <span v-if="dept.cost_center === 'cost_of_service' && dept.business_unit_name"
                  class="text-xs px-2 py-0.5 rounded font-medium"
                  style="background:rgba(186,117,23,0.12); color:var(--fv-gold); border:1px solid rgba(186,117,23,0.3);">
                  {{ dept.business_unit_name }}
                </span>
                <span class="badge-cc">{{ ccLabel(dept.cost_center) }}</span>
                <button @click="startEditDept(dept)" class="fv-action-btn">✎</button>
                <button @click="removeDept(dept)" class="fv-action-btn fv-action-btn-danger">✕</button>
              </div>
              <div class="pl-3 space-y-1" style="border-left:1px solid var(--fv-border);">
                <div v-for="title in dept.titles" :key="title.id" class="flex items-center gap-2">
                  <div v-if="editingTitle === title.id" class="flex gap-2 flex-1">
                    <input v-model="titleEdit.title_name" class="fv-input input-xs flex-1 rounded-lg" />
                    <select v-model="titleEdit.cost_center" class="fv-select input-xs rounded-lg">
                      <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
                    </select>
                    <button @click="saveTitle(title)" class="btn-xs btn-teal">✓</button>
                    <button @click="editingTitle = null" class="btn-xs btn-ghost">✕</button>
                  </div>
                  <template v-else>
                    <span class="fv-text-label text-xs flex-1">• {{ title.title_name }}</span>
                    <span class="badge-cc-xs">{{ ccLabel(title.cost_center) }}</span>
                    <button @click="startEditTitle(title)" class="fv-action-btn" style="width:1.4rem;height:1.4rem;">✎</button>
                    <button @click="removeTitle(title)" class="fv-action-btn fv-action-btn-danger" style="width:1.4rem;height:1.4rem;">✕</button>
                  </template>
                </div>
                <div v-if="addingTitleToDept === dept.id" class="flex gap-2 mt-1">
                  <input v-model="newTitle.title_name" class="fv-input input-xs flex-1 rounded-lg" placeholder="Title name" />
                  <select v-model="newTitle.cost_center" class="fv-select input-xs rounded-lg">
                    <option value="" disabled>Cost center</option>
                    <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
                  </select>
                  <button @click="submitTitle(dept.id)" class="btn-xs btn-teal">Add</button>
                  <button @click="addingTitleToDept = null" class="btn-xs btn-ghost">✕</button>
                </div>
                <button v-else @click="startAddTitle(dept.id)"
                  class="text-xs mt-1 transition-colors"
                  style="color:var(--fv-blue);"
                  onmouseover="this.style.color='var(--fv-gold)'"
                  onmouseout="this.style.color='var(--fv-blue)'">
                  + Add Title
                </button>
              </div>
            </div>
          </div>
          <div v-if="addingDept" class="flex flex-wrap gap-2">
            <input v-model="newDept.department_name" class="fv-input input-sm flex-1 min-w-36 rounded-lg" placeholder="Department name" />
            <select v-model="newDept.cost_center" class="fv-select input-sm rounded-lg">
              <option value="" disabled>Cost center</option>
              <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
            </select>
            <select v-if="newDept.cost_center === 'cost_of_service'"
              v-model="newDept.business_unit_name"
              class="fv-select input-sm rounded-lg"
              style="min-width:10rem;">
              <option value="">— No Business Unit —</option>
              <option v-for="bu in businessUnits" :key="bu" :value="bu">{{ bu }}</option>
            </select>
            <button @click="submitDept" class="btn-sm btn-teal">Add</button>
            <button @click="addingDept = false" class="btn-sm btn-ghost">✕</button>
          </div>
          <button v-else @click="addingDept = true" class="btn-sm btn-outline w-full mt-2">+ Add Department</button>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════
           TAB: COSTS & EXPENSES
      ══════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'expenses'">
        <div class="mb-6">
          <h2 class="text-lg font-bold fv-text-primary">Costs & Expenses</h2>
          <p class="fv-text-label text-sm mt-1">
            Define expense categories and their line items. CoA code enables accounting system integration.
            Tick <span style="color:#c084fc; font-weight:600;">Employee Expense</span> if the item appears in the employee expense projection.
          </p>
        </div>
        <div class="space-y-4">
          <div v-for="cat in expenseCategories" :key="cat.id" class="fv-card">
            <div class="flex items-center gap-3 mb-3">
              <div v-if="editingCat === cat.id" class="flex gap-2 flex-1">
                <input v-model="catEdit.category_name" class="fv-input input-sm flex-1 rounded-lg" />
                <select v-model="catEdit.cost_center" class="fv-select input-sm rounded-lg">
                  <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
                </select>
                <button @click="saveCat(cat)" class="btn-xs btn-teal">✓</button>
                <button @click="editingCat = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <template v-else>
                <div class="w-2 h-2 rounded-full" style="background:var(--fv-blue);"></div>
                <span class="fv-text-primary font-semibold flex-1">{{ cat.category_name }}</span>
                <span class="badge-cc">{{ ccLabel(cat.cost_center) }}</span>
                <button @click="startEditCat(cat)" class="fv-action-btn">✎</button>
                <button @click="removeCat(cat)" class="fv-action-btn fv-action-btn-danger">✕</button>
              </template>
            </div>
            <div class="pl-5" style="border-left:2px solid var(--fv-border); margin-top:0.5rem;">
              <table v-if="cat.items.length" class="w-full text-sm mb-2">
                <thead>
                  <tr class="text-xs" style="color:var(--fv-text-label); border-bottom:1px solid var(--fv-border);">
                    <th class="text-left pb-1 pr-3 font-medium">Item Name</th>
                    <th class="text-left pb-1 pr-3 font-medium w-28">CoA Code</th>
                    <th class="text-center pb-1 pr-3 font-medium w-28">Employee<br>Expense</th>
                    <th class="pb-1 w-16"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in cat.items" :key="item.id" style="border-bottom:1px solid rgba(27,53,88,0.3);">
                    <td class="py-1.5 pr-3">
                      <input v-if="editingItem === item.id" v-model="itemEdit.item_name" class="fv-input input-xs w-full rounded-lg" />
                      <span v-else :class="['text-sm', item.is_active ? 'fv-text-primary' : 'fv-text-label line-through']">{{ item.item_name }}</span>
                    </td>
                    <td class="py-1.5 pr-3">
                      <input v-if="editingItem === item.id" v-model="itemEdit.coa_code" class="fv-input input-xs w-full rounded-lg" placeholder="CoA code" />
                      <span v-else-if="item.coa_code" class="text-xs font-mono px-2 py-0.5 rounded" style="background:rgba(11,34,64,0.8); color:var(--fv-text-label);">{{ item.coa_code }}</span>
                    </td>
                    <td class="py-1.5 pr-3 text-center">
                      <input v-if="editingItem === item.id"
                        type="checkbox" v-model="itemEdit.is_employee_expense"
                        class="w-4 h-4 cursor-pointer" style="accent-color:#a855f7;" />
                      <span v-else>
                        <span v-if="item.is_employee_expense" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                          style="background:rgba(168,85,247,0.15); color:#c084fc;">✓</span>
                        <span v-else class="text-xs" style="color:var(--fv-border);">—</span>
                      </span>
                    </td>
                    <td class="py-1.5">
                      <div v-if="editingItem === item.id" class="flex gap-1 justify-end">
                        <button @click="saveItem(item)" class="btn-xs btn-teal">✓</button>
                        <button @click="editingItem = null" class="btn-xs btn-ghost">✕</button>
                      </div>
                      <div v-else class="flex gap-1 justify-end">
                        <button @click="startEditItem(item)" class="fv-action-btn">✎</button>
                        <button @click="removeItem(item)" class="fv-action-btn fv-action-btn-danger">✕</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div v-if="addingItemToCat === cat.id" class="flex flex-wrap gap-2 mt-2 items-center">
                <input v-model="newItem.item_name" class="fv-input input-xs flex-1 min-w-36 rounded-lg" placeholder="Item name" />
                <input v-model="newItem.coa_code" class="fv-input input-xs w-28 rounded-lg" placeholder="CoA code" />
                <label class="flex items-center gap-1.5 text-xs fv-text-label">
                  <input type="checkbox" v-model="newItem.is_employee_expense" class="w-3.5 h-3.5" style="accent-color:#a855f7;" />
                  Emp. Exp.
                </label>
                <button @click="submitItem(cat.id)" class="btn-xs btn-teal">Add</button>
                <button @click="addingItemToCat = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <button v-else @click="startAddItem(cat.id)"
                class="text-xs mt-2 transition-colors"
                style="color:var(--fv-blue);"
                onmouseover="this.style.color='var(--fv-gold)'"
                onmouseout="this.style.color='var(--fv-blue)'">
                + Add Item
              </button>
            </div>
          </div>
          <div v-if="addingCat" class="fv-card">
            <div class="flex flex-wrap gap-2">
              <input v-model="newCat.category_name" class="fv-input input-sm flex-1 rounded-lg" placeholder="Category name" />
              <select v-model="newCat.cost_center" class="fv-select input-sm rounded-lg">
                <option value="" disabled>Cost center</option>
                <option v-for="cc in costCenters" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
              </select>
              <button @click="submitCat" class="btn-sm btn-teal">Add Category</button>
              <button @click="addingCat = false" class="btn-sm btn-ghost">✕</button>
            </div>
          </div>
          <button v-else @click="addingCat = true" class="btn-sm btn-outline w-full">+ Add Expense Category</button>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════
           TAB: FIXED ASSETS
      ══════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'fixed-assets'">
        <div class="mb-6">
          <h2 class="text-lg font-bold fv-text-primary">Fixed Assets</h2>
          <p class="fv-text-label text-sm mt-1">
            Company fixed assets used in financial planning. These are the company's own operational assets, not managed properties.
          </p>
        </div>
        <div class="fv-card">
          <div v-if="addingAsset" class="flex flex-wrap gap-2 mb-4 pb-4" style="border-bottom:1px solid var(--fv-border);">
            <input v-model="newAsset.asset_name" class="fv-input input-sm flex-1 min-w-48 rounded-lg" placeholder="Asset name (e.g. Company Vehicle)" />
            <label class="flex items-center gap-2 text-sm fv-text-label">
              <input type="checkbox" v-model="newAsset.is_employee_asset" class="w-4 h-4" style="accent-color:var(--fv-blue);" />
              Employee Asset
            </label>
            <button @click="submitAsset" :disabled="!newAsset.asset_name" class="btn-sm btn-teal">Add Asset</button>
            <button @click="addingAsset = false" class="btn-sm btn-ghost">✕</button>
          </div>
          <button v-else @click="addingAsset = true" class="btn-sm btn-outline w-full mb-4">+ Add Fixed Asset</button>

          <div v-if="fixedAssets.length === 0" class="text-center py-8 text-sm fv-text-label">
            No fixed assets defined yet.
          </div>
          <div v-else class="space-y-2">
            <div v-for="asset in fixedAssets" :key="asset.id"
              class="flex items-center gap-3 px-3 py-2.5 rounded-lg"
              style="background:rgba(11,34,64,0.5); border:1px solid var(--fv-border);">
              <div v-if="editingAsset === asset.id" class="flex gap-2 flex-1 items-center">
                <input v-model="assetEdit.asset_name" class="fv-input input-xs flex-1 rounded-lg" />
                <label class="flex items-center gap-1.5 text-xs fv-text-label whitespace-nowrap">
                  <input type="checkbox" v-model="assetEdit.is_employee_asset" class="w-3.5 h-3.5" style="accent-color:var(--fv-blue);" />
                  Employee Asset
                </label>
                <button @click="saveAsset(asset)" class="btn-xs btn-teal">✓</button>
                <button @click="editingAsset = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <template v-else>
                <span class="fv-text-primary text-sm flex-1">{{ asset.asset_name }}</span>
                <span v-if="asset.is_employee_asset" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                  style="background:rgba(168,85,247,0.15); color:#c084fc; border:1px solid rgba(168,85,247,0.25);">
                  Employee
                </span>
                <button @click="startEditAsset(asset)" class="fv-action-btn">✎</button>
                <button @click="removeAsset(asset)" class="fv-action-btn fv-action-btn-danger">✕</button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════
           TAB: PROPERTY CATEGORIES & TYPES
      ══════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'property-categories'">
        <div class="mb-6">
          <h2 class="text-lg font-bold fv-text-primary">Property Categories & Types</h2>
          <p class="fv-text-label text-sm mt-1">
            Define the categories and types used when creating properties.
            The 5 default categories are system-provided and cannot be deleted.
            You can add your own categories and add property types under any category.
          </p>
        </div>

        <!-- Category cards -->
        <div class="space-y-4">
          <div v-for="cat in propertyCategories" :key="cat.id" class="fv-card">

            <!-- Category header -->
            <div class="flex items-center gap-3 mb-4">
              <!-- System default marker -->
              <div class="w-2 h-2 rounded-full flex-shrink-0"
                :style="cat.is_system ? 'background:var(--fv-gold);' : 'background:var(--fv-blue);'">
              </div>

              <div v-if="editingPropCat === cat.id" class="flex gap-2 flex-1">
                <input v-model="propCatEdit.category_name" class="fv-input input-sm flex-1 rounded-lg" placeholder="Category name" />
                <button @click="savePropCat(cat)" class="btn-xs btn-teal">✓</button>
                <button @click="editingPropCat = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <template v-else>
                <span class="fv-text-primary font-semibold flex-1">{{ cat.category_name }}</span>
                <span v-if="cat.is_system" class="text-xs px-2 py-0.5 rounded-full font-medium"
                  style="background:rgba(186,117,23,0.12); color:var(--fv-gold); border:1px solid rgba(186,117,23,0.3);">
                  Default
                </span>
                <button @click="startEditPropCat(cat)" class="fv-action-btn" title="Edit category name">✎</button>
                <button v-if="!cat.is_system" @click="removePropCat(cat)" class="fv-action-btn fv-action-btn-danger" title="Delete category">✕</button>
                <button v-else class="fv-action-btn" style="opacity:0.2; cursor:not-allowed;" title="System default — cannot delete">✕</button>
              </template>
            </div>

            <!-- Property Types under this category -->
            <div class="pl-5" style="border-left:2px solid var(--fv-border);">

              <!-- Types list -->
              <div v-if="cat.types && cat.types.length" class="flex flex-wrap gap-2 mb-3">
                <div v-for="ptype in cat.types" :key="ptype.id"
                  class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm"
                  style="background:rgba(11,34,64,0.5); border:1px solid var(--fv-border);">

                  <div v-if="editingPropType === ptype.id" class="flex gap-1.5 items-center">
                    <input v-model="propTypeEdit.type_name" class="fv-input input-xs rounded-lg" style="width:9rem;" />
                    <button @click="savePropType(ptype)" class="btn-xs btn-teal">✓</button>
                    <button @click="editingPropType = null" class="btn-xs btn-ghost">✕</button>
                  </div>
                  <template v-else>
                    <span class="fv-text-primary text-xs font-medium">{{ ptype.type_name }}</span>
                    <button @click="startEditPropType(ptype)" class="fv-action-btn" style="width:1.3rem;height:1.3rem;">✎</button>
                    <button @click="removePropType(ptype)" class="fv-action-btn fv-action-btn-danger" style="width:1.3rem;height:1.3rem;">✕</button>
                  </template>
                </div>
              </div>
              <p v-else class="text-xs fv-text-label mb-3">No types defined yet for this category.</p>

              <!-- Add type inline -->
              <div v-if="addingTypeToCat === cat.id" class="flex gap-2 items-center mt-1">
                <input v-model="newPropType.type_name"
                  class="fv-input input-xs flex-1 max-w-xs rounded-lg"
                  placeholder="e.g. Office, Retail Shop, Villa…"
                  @keyup.enter="submitPropType(cat.id)" />
                <button @click="submitPropType(cat.id)" class="btn-xs btn-teal">Add</button>
                <button @click="addingTypeToCat = null" class="btn-xs btn-ghost">✕</button>
              </div>
              <button v-else @click="startAddPropType(cat.id)"
                class="text-xs mt-1 transition-colors"
                style="color:var(--fv-blue);"
                onmouseover="this.style.color='var(--fv-gold)'"
                onmouseout="this.style.color='var(--fv-blue)'">
                + Add Type
              </button>
            </div>
          </div>

          <!-- Add new custom category -->
          <div v-if="addingPropCat" class="fv-card">
            <div class="flex gap-2">
              <input v-model="newPropCat.category_name"
                class="fv-input input-sm flex-1 rounded-lg"
                placeholder="New category name (e.g. Hospitality)"
                @keyup.enter="submitPropCat" />
              <button @click="submitPropCat" class="btn-sm btn-teal">Add Category</button>
              <button @click="addingPropCat = false" class="btn-sm btn-ghost">✕</button>
            </div>
          </div>
          <button v-else @click="addingPropCat = true" class="btn-sm btn-outline w-full">
            + Add Custom Category
          </button>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:            Object,
  tenants:            { type: Array, default: () => [] },
  departments:        { type: Array, default: () => [] },
  expenseCategories:  { type: Array, default: () => [] },
  fixedAssets:        { type: Array, default: () => [] },
  propertyCategories: { type: Array, default: () => [] },
  costCenters:        { type: Array, default: () => [] },
  businessUnits:      { type: Array, default: () => [] },
})

// ── Tabs ───────────────────────────────────────────────────────────────
const tabs = [
  { key: 'tenants',             label: 'Tenants' },
  { key: 'manpower',            label: 'Manpower Structure' },
  { key: 'expenses',            label: 'Costs & Expenses' },
  { key: 'fixed-assets',        label: 'Fixed Assets' },
  { key: 'property-categories', label: 'Property Categories & Types' },
]
const activeTab = ref('tenants')

// ── Helpers ────────────────────────────────────────────────────────────
const ccLabel = (val) => ({
  cost_of_service: 'Cost of Service',
  opex:            'OPEX',
  sales_marketing: 'Sales & Mktg',
  admin_general:   'Admin & Gen',
}[val] || val)

const cid = props.company.id
const go  = (method, url, data = {}) => router[method](url, data, { preserveScroll: true })

// ══ TENANTS ═══════════════════════════════════════════════════════════

const addingTenant  = ref(false)
const newTenant     = reactive({ 
  customer_name: '', 
  business_sector: '', 
  tenant_nature: '', 
  is_related_party: false 
})

const editingTenant = ref(null)
const tenantEdit    = reactive({ 
  customer_name: '', 
  business_sector: '', 
  tenant_nature: '', 
  is_related_party: false 
})

const handleNatureChange = () => {
  if (newTenant.tenant_nature === 'individual') {
    newTenant.business_sector = '';
  }
}

const startAddTenant = () => {
  addingTenant.value = true
  newTenant.customer_name   = ''
  newTenant.business_sector = ''
  newTenant.tenant_nature   = ''
  newTenant.is_related_party = false
}

const startEditTenant = (t) => {
  editingTenant.value           = t.id
  tenantEdit.customer_name      = t.customer_name
  tenantEdit.business_sector    = t.business_sector || ''
  tenantEdit.tenant_nature      = t.tenant_nature || ''
  tenantEdit.is_related_party   = !!t.is_related_party
}

const submitTenant = () => {
  if (!newTenant.customer_name) return
  
  router.post(route('company.settings.tenants.store', cid), { ...newTenant }, {
    preserveScroll: true,
    onSuccess: () => {
      addingTenant.value = false
      // Reset form
      newTenant.customer_name    = ''
      newTenant.business_sector  = ''
      newTenant.tenant_nature    = ''
      newTenant.is_related_party = false
    },
  })
}

const saveTenant = (t) => {
  router.put(route('company.settings.tenants.update', [cid, t.id]), { ...tenantEdit }, {
    preserveScroll: true,
    onSuccess: () => { editingTenant.value = null }
  })
}

const removeTenant = (t) => {
  go('delete', route('company.settings.tenants.destroy', [cid, t.id]))
}


// ══ MANPOWER STRUCTURE ════════════════════════════════════════════════
const addingDept  = ref(false)
const newDept     = reactive({ department_name: '', cost_center: '', business_unit_name: '' })
const editingDept = ref(null)
const deptEdit    = reactive({ department_name: '', cost_center: '', business_unit_name: '' })
const submitDept    = () => { go('post', route('company.settings.departments.store', cid), { ...newDept }); newDept.department_name = ''; newDept.cost_center = ''; newDept.business_unit_name = ''; addingDept.value = false }
const startEditDept = (d) => { editingDept.value = d.id; deptEdit.department_name = d.department_name; deptEdit.cost_center = d.cost_center; deptEdit.business_unit_name = d.business_unit_name || '' }
const saveDept      = (d) => { go('put', route('company.settings.departments.update', [cid, d.id]), { ...deptEdit }); editingDept.value = null }
const removeDept    = (d) => go('delete', route('company.settings.departments.destroy', [cid, d.id]))

const addingTitleToDept = ref(null)
const newTitle          = reactive({ title_name: '', cost_center: '' })
const editingTitle      = ref(null)
const titleEdit         = reactive({ title_name: '', cost_center: '' })
const startAddTitle  = (deptId) => { addingTitleToDept.value = deptId; newTitle.title_name = ''; newTitle.cost_center = '' }
const submitTitle    = (deptId) => { go('post', route('company.settings.titles.store', cid), { title_name: newTitle.title_name, cost_center: newTitle.cost_center, manpower_department_id: deptId, is_branch_title: false }); addingTitleToDept.value = null }
const startEditTitle = (t) => { editingTitle.value = t.id; titleEdit.title_name = t.title_name; titleEdit.cost_center = t.cost_center }
const saveTitle      = (t) => { go('put', route('company.settings.titles.update', [cid, t.id]), { ...titleEdit }); editingTitle.value = null }
const removeTitle    = (t) => go('delete', route('company.settings.titles.destroy', [cid, t.id]))

// ══ COSTS & EXPENSES ══════════════════════════════════════════════════
const addingCat  = ref(false)
const newCat     = reactive({ category_name: '', cost_center: '' })
const editingCat = ref(null)
const catEdit    = reactive({ category_name: '', cost_center: '' })
const submitCat    = () => { go('post', route('company.settings.expense-categories.store', cid), { ...newCat }); addingCat.value = false }
const startEditCat = (c) => { editingCat.value = c.id; catEdit.category_name = c.category_name; catEdit.cost_center = c.cost_center }
const saveCat      = (c) => { go('put', route('company.settings.expense-categories.update', [cid, c.id]), { ...catEdit }); editingCat.value = null }
const removeCat    = (c) => go('delete', route('company.settings.expense-categories.destroy', [cid, c.id]))

const addingItemToCat = ref(null)
const newItem         = reactive({ item_name: '', coa_code: '', is_employee_expense: false })
const editingItem     = ref(null)
const itemEdit        = reactive({ item_name: '', coa_code: '', is_employee_expense: false })
const startAddItem  = (catId) => { addingItemToCat.value = catId; newItem.item_name = ''; newItem.coa_code = ''; newItem.is_employee_expense = false }
const submitItem    = (catId) => { go('post', route('company.settings.expense-items.store', [cid, catId]), { ...newItem }); addingItemToCat.value = null }
const startEditItem = (i) => { editingItem.value = i.id; itemEdit.item_name = i.item_name; itemEdit.coa_code = i.coa_code || ''; itemEdit.is_employee_expense = !!i.is_employee_expense }
const saveItem      = (i) => { go('put', route('company.settings.expense-items.update', [cid, i.id]), { ...itemEdit }); editingItem.value = null }
const removeItem    = (i) => go('delete', route('company.settings.expense-items.destroy', [cid, i.id]))

// ══ FIXED ASSETS ══════════════════════════════════════════════════════
const addingAsset  = ref(false)
const newAsset     = reactive({ asset_name: '', is_employee_asset: false })
const editingAsset = ref(null)
const assetEdit    = reactive({ asset_name: '', is_employee_asset: false })
const submitAsset    = () => { go('post', route('company.settings.fixed-assets.store', cid), { ...newAsset }); addingAsset.value = false }
const startEditAsset = (a) => { editingAsset.value = a.id; assetEdit.asset_name = a.asset_name; assetEdit.is_employee_asset = !!a.is_employee_asset }
const saveAsset      = (a) => { go('put', route('company.settings.fixed-assets.update', [cid, a.id]), { ...assetEdit }); editingAsset.value = null }
const removeAsset    = (a) => go('delete', route('company.settings.fixed-assets.destroy', [cid, a.id]))

// ══ PROPERTY CATEGORIES & TYPES ═══════════════════════════════════════

// ── Property Categories ───────────────────────────────────────────────
const addingPropCat  = ref(false)
const newPropCat     = reactive({ category_name: '' })
const editingPropCat = ref(null)
const propCatEdit    = reactive({ category_name: '' })

const startEditPropCat = (c) => { editingPropCat.value = c.id; propCatEdit.category_name = c.category_name }
const submitPropCat    = () => {
  if (!newPropCat.category_name) return
  go('post', route('company.settings.property-categories.store', cid), { ...newPropCat })
  newPropCat.category_name = ''
  addingPropCat.value = false
}
const savePropCat   = (c) => { go('put', route('company.settings.property-categories.update', [cid, c.id]), { ...propCatEdit }); editingPropCat.value = null }
const removePropCat = (c) => go('delete', route('company.settings.property-categories.destroy', [cid, c.id]))

// ── Property Types ────────────────────────────────────────────────────
const addingTypeToCat = ref(null)
const newPropType     = reactive({ type_name: '' })
const editingPropType = ref(null)
const propTypeEdit    = reactive({ type_name: '' })

const startAddPropType  = (catId) => { addingTypeToCat.value = catId; newPropType.type_name = '' }
const submitPropType    = (catId) => {
  if (!newPropType.type_name) return
  go('post', route('company.settings.property-types.store', [cid, catId]), { ...newPropType })
  addingTypeToCat.value = null
  newPropType.type_name = ''
}
const startEditPropType = (t) => { editingPropType.value = t.id; propTypeEdit.type_name = t.type_name }
const savePropType      = (t) => { go('put', route('company.settings.property-types.update', [cid, t.id]), { ...propTypeEdit }); editingPropType.value = null }
const removePropType    = (t) => go('delete', route('company.settings.property-types.destroy', [cid, t.id]))
</script>

<style scoped>
/* ── Tab States ──────────────────────────────────────────────────── */
.tab-active {
  background: var(--fv-blue);
  color: #fff;
  box-shadow: 0 2px 8px rgba(20,144,168,0.3);
}
.tab-inactive {
  color: var(--fv-text-label);
}
.tab-inactive:hover {
  color: var(--fv-text-primary);
  background: var(--fv-bg-hover);
}

/* ── Inputs ──────────────────────────────────────────────────────── */
.input-sm { font-size: 0.875rem; padding: 0.375rem 0.75rem; }
.input-xs { font-size: 0.75rem;  padding: 0.25rem 0.5rem; }

/* ── Buttons ─────────────────────────────────────────────────────── */
.btn-teal {
  background: var(--fv-blue);
  color: #fff;
  border-radius: 0.5rem;
  font-weight: 500;
  transition: background 0.15s;
  cursor: pointer;
}
.btn-teal:hover    { background: var(--fv-blue-hover); }
.btn-teal:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-ghost {
  background: rgba(27,53,88,0.5);
  color: var(--fv-text-label);
  border-radius: 0.5rem;
  font-weight: 500;
  transition: background 0.15s;
  cursor: pointer;
}
.btn-ghost:hover { background: var(--fv-border); }

.btn-outline {
  border: 1px solid var(--fv-border);
  color: var(--fv-text-label);
  border-radius: 0.5rem;
  font-weight: 500;
  transition: all 0.15s;
  cursor: pointer;
  background: transparent;
}
.btn-outline:hover {
  border-color: var(--fv-gold);
  color: var(--fv-gold);
}

.btn-sm  { padding: 0.375rem 0.75rem; font-size: 0.875rem; }
.btn-xs  { padding: 0.25rem 0.5rem;   font-size: 0.75rem; }

/* ── Cost Center Badge ───────────────────────────────────────────── */
.badge-cc {
  font-size: 0.65rem;
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  background: var(--fv-blue-dim);
  color: #48C4D8;
  border: 1px solid var(--fv-blue-border);
  font-weight: 600;
  white-space: nowrap;
}
.badge-cc-xs {
  font-size: 0.6rem;
  padding: 0.1rem 0.4rem;
  border-radius: 9999px;
  background: rgba(11,34,64,0.8);
  color: var(--fv-text-label);
  border: 1px solid var(--fv-border);
}
</style>