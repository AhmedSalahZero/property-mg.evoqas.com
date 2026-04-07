<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n/index.js'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'

const { t, locale } = useI18n()

const page         = usePage()
const user         = computed(() => page.props.auth.user)
const isSuperAdmin = computed(() => user.value?.is_super_admin)
const companyId    = computed(() => user.value?.company_id ?? null)
const canSeeSettings = computed(() =>
    !isSuperAdmin.value && companyId.value !== null &&
    ['company_admin', 'manager'].includes(user.value?.role)
)

// ── Sidebar ───────────────────────────────────────────────────────────
const sidebarExpanded  = ref(true)
const showingMobileMenu = ref(false)
const showMobileSidebar = ref(false)

const activeCompanyId = computed(() => {
    if (!isSuperAdmin.value) return companyId.value

    const pathMatch = window.location.pathname.match(/\/companies\/(\d+)\//)
    if (pathMatch) return parseInt(pathMatch[1])

    const params = new URLSearchParams(window.location.search)
    const qId    = params.get('company_id')
    if (qId) return parseInt(qId)

    return null
})

const hasSidebar = computed(() => activeCompanyId.value !== null)

// ── Task badge ─────────────────────────────────────────────────────────
const taskBadge = ref(0)
const isDark    = ref(true)

onMounted(() => {
    fetch('/tasks/badge-count', { credentials: 'include' })
        .then(r => r.json())
        .then(d => { taskBadge.value = d.count || 0 })
        .catch(() => {})

    const savedTheme = user.value?.theme || localStorage.getItem('fv_theme') || 'dark'
    applyTheme(savedTheme !== 'light')

    const saved = localStorage.getItem('fv_sidebar')
    if (saved !== null) sidebarExpanded.value = saved === 'expanded'
})

function applyTheme(dark) {
    isDark.value = dark
    const root = document.documentElement
    if (dark) {
        root.setAttribute('data-theme', 'dark')
        root.style.setProperty('--fv-bg',      '#0C1829')
        root.style.setProperty('--fv-nav',     '#0E1E34')
        root.style.setProperty('--fv-card',    '#112240')
        root.style.setProperty('--fv-border',  '#1B3558')
        root.style.setProperty('--fv-text',    '#E2E8F0')
        root.style.setProperty('--fv-muted',   '#6B96B8')
        root.style.setProperty('--fv-input',   '#0D1E38')
        root.style.setProperty('--fv-sidebar', '#0B1A30')
    } else {
        root.setAttribute('data-theme', 'light')
        root.style.setProperty('--fv-bg',      '#F0F8FD')
        root.style.setProperty('--fv-nav',     '#ffffff')
        root.style.setProperty('--fv-card',    '#ffffff')
        root.style.setProperty('--fv-border',  '#e2e8f0')
        root.style.setProperty('--fv-text',    '#0f172a')
        root.style.setProperty('--fv-muted',   '#64748b')
        root.style.setProperty('--fv-input',   '#f8fafc')
        root.style.setProperty('--fv-sidebar', '#f1f5f9')
    }
}

function toggleTheme() {
    const newDark = !isDark.value
    applyTheme(newDark)
    localStorage.setItem('fv_theme', newDark ? 'dark' : 'light')
}

function toggleLocale() {
    const newLocale = locale.value === 'en' ? 'ar' : 'en'
    setLocale(newLocale)
}

function toggleSidebar() {
    sidebarExpanded.value = !sidebarExpanded.value
    localStorage.setItem('fv_sidebar', sidebarExpanded.value ? 'expanded' : 'collapsed')
}

// ── Nav modules ────────────────────────────────────────────────────────
const analysisModules = computed(() => [
    {
        label: 'Dashboard',
        route: 'company.properties.dashboard',
        routeMatch: 'company.properties.dashboard',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>`
    },
    {
        label: 'Properties',
        route: 'company.properties.index',
        routeMatch: 'company.properties.index',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>`
    },
     
])

const planningModules = computed(() => [
    {
        label: 'Cash Forecast',
        route: 'company.properties.cash-forecast',
        routeMatch: 'company.properties.cash-forecast*',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>`
    },
    {
        label: 'Keep or Sell',
        route: 'company.properties.keep-or-sell.index',
        routeMatch: 'company.properties.keep-or-sell*',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>`
    },
    {
        label: 'Projects & Tasks',
        route: 'company.projects.index',
        routeMatch: 'company.projects.*',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4"/>`
    },
    {
        label: 'Statistica',
        route: 'company.statistica.index',
        routeMatch: 'company.statistica.*',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>`
    },
])
</script>

<template>
    <div>
        <div class="min-h-screen transition-colors duration-300" style="background-color: var(--fv-bg, #0C1829);">

            <!-- ══════════════════════════════════════════════════════
                 TOP NAVIGATION BAR
            ═════════════════════════════════════════════════════════ -->
            <nav class="border-b sticky top-0 transition-colors duration-300"
                style="z-index:10000; background-color: var(--fv-nav, #0d1426); border-color: var(--fv-border, #1B3558);">

                <div class="mx-auto max-w-full px-4 sm:px-6">
                    <div class="flex h-16 items-center justify-between">

                        <!-- LEFT: Logo + Nav Links -->
                        <div class="flex items-center gap-4">

                            <!-- Sidebar toggle (only when inside a company) -->
                            <button v-if="hasSidebar" @click="toggleSidebar"
                                class="fv-icon-btn hidden lg:flex" title="Toggle sidebar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>

                            <!-- Logo / Brand -->
                            <Link :href="isSuperAdmin
                                    ? route('companies.index')
                                    : route('company.properties.dashboard', activeCompanyId)"
                                class="flex items-center gap-2 flex-shrink-0">
                                <div class="fv-logo-mark">
                                    <svg viewBox="0 0 28 28" fill="none" class="w-full h-full">
                                        <rect width="28" height="28" rx="7" fill="#0C447C"/>
                                        <path d="M7 20L12 9l5 7 3-4 3 8" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="hidden sm:block">
                                    <span class="text-base font-extrabold tracking-tight" style="color:#48C4D8;">VERO</span>
                                    <span class="text-xs font-semibold ml-1.5 px-1.5 py-0.5 rounded" style="background:rgba(186,117,23,0.15); color:#FAC775;">Property Management</span>
                                </div>
                            </Link>

                            <!-- Divider -->
                            <div class="hidden lg:block w-px h-6" style="background-color: var(--fv-border, #1B3558);"></div>

                            <!-- Desktop Nav Links -->
                            <div class="hidden lg:flex items-center gap-0.5">

                                <Link v-if="isSuperAdmin" :href="route('companies.index')" class="fv-nav-link"
                                    :class="route().current('companies.*') ? 'fv-nav-active' : ''">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 00-1-1h-2a1 1 0 00-1 1v5m4 0H9" />
                                    </svg>
                                    {{ t('nav.companies') }}
                                </Link>
                                <Link v-if="isSuperAdmin || user?.role === 'company_admin'" :href="route('users.index')" class="fv-nav-link"
                                    :class="route().current('users.*') ? 'fv-nav-active' : ''">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    {{ t('nav.users') }}
                                </Link>
                            </div>
                        </div>

                        <!-- RIGHT: Actions + User Menu -->
                        <div class="hidden sm:flex items-center gap-2">

                            <!-- Theme Toggle -->
                            <button @click="toggleTheme" class="fv-icon-btn" :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                                <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>

                            <!-- Language Toggle -->
                            <button @click="toggleLocale" class="fv-lang-btn"
                                :title="locale === 'en' ? 'Switch to Arabic' : 'التبديل إلى الإنجليزية'">
                                <span class="fv-lang-active">{{ locale === 'en' ? 'EN' : 'ع' }}</span>
                                <span class="fv-lang-divider">/</span>
                                <span class="fv-lang-inactive">{{ locale === 'en' ? 'ع' : 'EN' }}</span>
                            </button>

                            <!-- My Tasks -->
                            <Link :href="route('tasks.index')" class="fv-tasks-btn">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                {{ t('nav.myTasks') }}
                                <span v-if="taskBadge > 0" class="fv-badge">{{ taskBadge }}</span>
                            </Link>

                            <!-- User Dropdown -->
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button type="button" class="fv-user-btn">
                                        <span class="fv-user-avatar">{{ user.name.charAt(0).toUpperCase() }}</span>
                                        <span class="hidden md:block text-sm font-medium" style="color: #E2E8F0;">{{ user.name }}</span>
                                        <svg class="h-3.5 w-3.5" style="color: #1490A8;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <div class="px-4 py-2.5 border-b" style="border-color: var(--fv-border, #1B3558);">
                                        <p class="text-xs mb-0.75 font-bold" style="color: #0C447C;">{{ user.name }}</p>
                                        <p class="text-xs mt-0.75 mb-0.75 break-words"  style="color: #0C447C ;">{{ user.email }}</p>
                                        <span v-if="isSuperAdmin" class="inline-block mt-1 text-xs font-semibold px-1.5 py-0.5 rounded" style="background:rgba(20, 144, 168,0.15);color:#000080;">Super Admin</span>
                                    </div>
                                    <DropdownLink :href="route('profile.edit')">{{ t('nav.profile') }}</DropdownLink>
                                    <DropdownLink v-if="canSeeSettings" :href="route('company.settings.index', companyId)">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            Company Settings
                                        </span>
                                    </DropdownLink>
                                    <div v-if="isSuperAdmin" class="px-4 py-1.5">
                                        <p class="text-xs" style="color: #0C447C;">⚙ Open a company to access its settings</p>
                                    </div>
                                    <DropdownLink :href="route('logout')" method="post" as="button">{{ t('nav.logout') }}</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <!-- Mobile: theme + hamburger -->
                        <div class="flex items-center gap-2 sm:hidden">
                            <button @click="toggleTheme" class="fv-icon-btn">
                                <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>
                            <button @click="showingMobileMenu = !showingMobileMenu" class="fv-icon-btn">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{ hidden: showingMobileMenu, 'inline-flex': !showingMobileMenu }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{ hidden: !showingMobileMenu, 'inline-flex': showingMobileMenu }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Mobile Menu -->
                <div v-show="showingMobileMenu" class="sm:hidden border-t" style="border-color: var(--fv-border, #1B3558);">
                    <div class="px-4 py-3 space-y-1">
                        <Link v-if="activeCompanyId"
                            :href="route('company.properties.dashboard', activeCompanyId)"
                            class="fv-mobile-link"
                            :class="route().current('company.properties.dashboard') ? 'fv-mobile-link-active' : ''">
                            Dashboard
                        </Link>
                        <Link v-if="isSuperAdmin" :href="route('companies.index')" class="fv-mobile-link" :class="route().current('companies.*') ? 'fv-mobile-link-active' : ''">Companies</Link>
                        <Link v-if="isSuperAdmin || user?.role === 'company_admin'" :href="route('users.index')" class="fv-mobile-link" :class="route().current('users.*') ? 'fv-mobile-link-active' : ''">Users</Link>
                        <Link :href="route('tasks.index')" class="fv-mobile-link" style="color: #E2E8F0 !important;">
                            My Tasks <span v-if="taskBadge > 0" class="fv-badge ml-1">{{ taskBadge }}</span>
                        </Link>
                        <template v-if="hasSidebar">
                            <div class="pt-2 pb-1 px-3 text-xs font-semibold uppercase tracking-widest" style="color: #1490A8;">Properties</div>
                            <Link v-for="m in analysisModules" :key="m.route"
                                :href="route(m.route, activeCompanyId)"
                                class="fv-mobile-link"
                                :class="route().current(m.routeMatch) ? 'fv-mobile-link-active' : ''">{{ m.label }}</Link>
                            <div class="pt-2 pb-1 px-3 text-xs font-semibold uppercase tracking-widest" style="color: #1490A8;">Planning</div>
                            <Link v-for="m in planningModules" :key="m.route"
                                :href="route(m.route, activeCompanyId)"
                                class="fv-mobile-link"
                                :class="route().current(m.routeMatch) ? 'fv-mobile-link-active' : ''">{{ m.label }}</Link>
                            <div class="pt-2 pb-1 px-3 text-xs font-semibold uppercase tracking-widest" style="color: #26C6DA;">Tools</div>
                            <Link :href="route('loan-engine.index')" class="fv-mobile-link" :class="route().current('loan-engine.*') ? 'fv-mobile-link-active' : ''">Loan Calculator</Link>
                            <div class="border-t my-1" style="border-color: var(--fv-border);"></div>
                            <Link :href="route('company.settings.index', activeCompanyId)" class="fv-mobile-link">⚙ Settings</Link>
                        </template>
                    </div>
                    <div class="px-4 py-3 border-t space-y-1" style="border-color: var(--fv-border, #1B3558);">
                        <p class="text-sm font-semibold px-3" style="color: #E2E8F0;">{{ user.name }}</p>
                        <p class="text-xs px-3 mb-2" style="color: #1490A8;">{{ user.email }}</p>
                        <Link :href="route('profile.edit')" class="fv-mobile-link">Profile</Link>
                        <Link :href="route('logout')" method="post" as="button" class="fv-mobile-link w-full text-left">Log Out</Link>
                    </div>
                </div>

            </nav>

            <!-- ══════════════════════════════════════════════════════
                 BODY: Sidebar + Page Content
            ═════════════════════════════════════════════════════════ -->
            <div class="flex" style="min-height: calc(100vh - 4rem);">

                <!-- ── SIDEBAR ─────────────────────────────────────── -->
                <aside v-if="hasSidebar"
                    :class="['fv-sidebar hidden lg:flex flex-col sticky top-16 h-[calc(100vh-4rem)] transition-all duration-300 ease-in-out flex-shrink-0',
                        sidebarExpanded ? 'w-56' : 'w-[60px]']"
                    style="background-color: var(--fv-sidebar, #0B1A30); border-right: 1px solid var(--fv-border, #1B3558);">

                    <!-- ── ANALYSIS GROUP ──────────────────────────── -->
                    <div class="flex-1 overflow-y-auto py-4 flex flex-col gap-0.5 px-2">

                        <div v-if="sidebarExpanded"
                            class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-widest"
                            style="color: #26C6DA;">
                            Properties
                        </div>
                        <div v-else class="h-px mx-2 mb-2 mt-1" style="background: var(--fv-border);"></div>

                        <Link v-for="mod in analysisModules" :key="mod.route"
                            :href="route(mod.route, activeCompanyId)"
                            :title="!sidebarExpanded ? mod.label : ''"
                            :class="['fv-sidebar-link', route().current(mod.routeMatch) ? 'fv-sidebar-active' : '',
                                sidebarExpanded ? 'px-3' : 'px-0 justify-center']">
                            <svg class="flex-shrink-0 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="mod.icon"></svg>
                            <span v-if="sidebarExpanded" class="truncate">{{ mod.label }}</span>
                        </Link>

                        <!-- Divider -->
                        <div class="my-3 mx-2 h-px" style="background: var(--fv-border, #1B3558);"></div>

                        <!-- Planning Group -->
                        <div v-if="sidebarExpanded"
                            class="px-3 pb-2 text-xs font-bold uppercase tracking-widest"
                            style="color: #26C6DA;">
                            Planning
                        </div>

                        <Link v-for="mod in planningModules" :key="mod.route"
                            :href="route(mod.route, activeCompanyId)"
                            :title="!sidebarExpanded ? mod.label : ''"
                            :class="['fv-sidebar-link', route().current(mod.routeMatch) ? 'fv-sidebar-active' : '',
                                sidebarExpanded ? 'px-3' : 'px-0 justify-center']">
                            <svg class="flex-shrink-0 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="mod.icon"></svg>
                            <span v-if="sidebarExpanded" class="truncate">{{ mod.label }}</span>
                        </Link>

                        <!-- Divider -->
                        <div class="my-3 mx-2 h-px" style="background: var(--fv-border, #1B3558);"></div>

                        <!-- Tools Group -->
                        <div v-if="sidebarExpanded"
                            class="px-3 pb-2 text-xs font-bold uppercase tracking-widest"
                            style="color: #26C6DA;">
                            Tools
                        </div>
                        <div v-else class="h-px mx-2 mb-2" style="background: var(--fv-border);"></div>

                        <!-- Loan Calculator -->
                        <Link
                            :href="route('loan-engine.index')"
                            :title="!sidebarExpanded ? 'Loan Calculator' : ''"
                            :class="['fv-sidebar-link', route().current('loan-engine.*') ? 'fv-sidebar-active' : '',
                                sidebarExpanded ? 'px-3' : 'px-0 justify-center']">
                            <svg class="flex-shrink-0 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M9 7H7a2 2 0 00-2 2v9a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-2M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M9 7h6M9 12h.01M12 12h.01M15 12h.01M9 15h.01M12 15h.01M15 15h.01"/>
                            </svg>
                            <span v-if="sidebarExpanded">Loan Calculator</span>
                        </Link>

                    </div>

                    <!-- ── SETTINGS — pinned to bottom ────────────── -->
                    <div class="py-3 px-2 border-t" style="border-color: var(--fv-border, #1B3558);">
                        <Link
                            :href="route('company.settings.index', activeCompanyId)"
                            :title="!sidebarExpanded ? 'Settings' : ''"
                            :class="['fv-sidebar-link fv-sidebar-settings', route().current('company.settings.*') ? 'fv-sidebar-active' : '',
                                sidebarExpanded ? 'px-3' : 'px-0 justify-center']">
                            <svg class="flex-shrink-0 w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span v-if="sidebarExpanded">Settings</span>
                        </Link>
                    </div>

                </aside>

                <!-- ── PAGE CONTENT ────────────────────────────────── -->
                <main class="flex-1 min-w-0">
                    <slot />
                </main>

            </div>

        </div>
    </div>
</template>

<style>
/* ═══════════════════════════════════════════════════════════════════
   VERO Property Management GLOBAL DESIGN SYSTEM
═══════════════════════════════════════════════════════════════════ */

.fv-bg          { background-color: var(--fv-bg,     #0C1829); }
.fv-header-bg   { background-color: var(--fv-nav,    #0E1E34); }
.fv-card-bg     { background-color: var(--fv-card,   #112240); }
.fv-border      { border-color:     var(--fv-border, #1B3558); }
.fv-text-primary{ color: var(--fv-text,  #E2E8F0); }
.fv-text-muted  { color: var(--fv-muted, #6B96B8); }

.fv-input {
    background-color: var(--fv-input, #0D1E38);
    border: 1px solid var(--fv-border, #1B3558);
    color: var(--fv-text, #E2E8F0);
    outline: none;
    transition: border-color 0.15s ease;
}
.fv-input:focus { border-color: #1490A8; }
.fv-input::placeholder { color: var(--fv-muted, #6B96B8); }

.fv-btn-secondary {
    background-color: var(--fv-card, #112240);
    border: 1px solid var(--fv-border, #1B3558);
    color: var(--fv-text, #E2E8F0);
    cursor: pointer;
    transition: all 0.15s ease;
}
.fv-btn-secondary:hover { border-color: #1490A8; color: #48C4D8; }

.fv-avatar {
    width: 2.5rem; height: 2.5rem; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1490A8, #0C447C);
    color: white; font-size: 1rem; font-weight: 700; flex-shrink: 0;
}

/* ── Top Nav Links ───────────────────────────────────────────────────── */
.fv-nav-link {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.75rem; border-radius: 0.5rem;
    font-size: 0.8125rem; font-weight: 500;
    color: #E2E8F0;
    transition: all 0.15s ease; text-decoration: none; white-space: nowrap;
}
.fv-nav-link:hover { color: #48C4D8; background-color: rgba(20, 144, 168,0.08); }
.fv-nav-active { color: #48C4D8 !important; background-color: rgba(20, 144, 168,0.10) !important; font-weight: 600; }

/* ── Sidebar Links ───────────────────────────────────────────────────── */
.fv-sidebar-link {
    display: flex; align-items: center; gap: 0.625rem;
    padding-top: 0.5rem; padding-bottom: 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem; font-weight: 500;
    color: #E2E8F0;
    text-decoration: none;
    transition: all 0.15s ease;
    white-space: nowrap; overflow: hidden;
    border-left: 3px solid transparent;
}
.fv-sidebar-link:hover {
    color: #48C4D8;
    background-color: rgba(20, 144, 168,0.07);
}

.fv-sidebar-active {
    color: #FFBF00 !important;
    background-color: rgba(186, 117, 23,0.10) !important;
    border-left: 3px solid #FFBF00 !important;
    font-weight: 700;
}

.fv-sidebar-settings:hover {
    color: #FAC775 !important;
    background-color: rgba(186,117,23,0.08) !important;
}
.fv-sidebar-active.fv-sidebar-settings {
    color: #BA7517 !important;
    background-color: rgba(186, 117, 23,0.10) !important;
    border-left: 3px solid #BA7517 !important;
}

/* ── Mobile Links ────────────────────────────────────────────────────── */
.fv-mobile-link {
    display: block; padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    font-size: 0.875rem; font-weight: 500;
    color: #E2E8F0; text-decoration: none;
    transition: all 0.15s ease; cursor: pointer;
    background: transparent; border: none;
}
.fv-mobile-link:hover { color: #48C4D8; background-color: rgba(20, 144, 168,0.07); }
.fv-mobile-link-active { color: #BA7517 !important; background-color: rgba(186, 117, 23,0.08) !important; }

/* ── Icon Button ─────────────────────────────────────────────────────── */
.fv-icon-btn {
    width: 2.25rem; height: 2.25rem;
    display: flex; align-items: center; justify-content: center;
    border-radius: 0.5rem;
    border: 1px solid var(--fv-border, #1B3558);
    background-color: var(--fv-card, #112240);
    color: #E2E8F0;
    transition: all 0.15s ease; cursor: pointer;
}
.fv-icon-btn:hover { color: #48C4D8; border-color: #48C4D8; }

/* ── Tasks Button ────────────────────────────────────────────────────── */
.fv-tasks-btn {
    display: flex; align-items: center; gap: 0.375rem;
    font-size: 0.8125rem; font-weight: 600;
    padding: 0.375rem 0.75rem; border-radius: 0.5rem;
    color: #E2E8F0; text-decoration: none;
    transition: all 0.15s ease; position: relative;
}
.fv-tasks-btn:hover { color: #48C4D8; background-color: rgba(20, 144, 168,0.07); }

/* ── Badge ───────────────────────────────────────────────────────────── */
.fv-badge {
    background-color: #ef4444; color: white;
    font-size: 0.6rem; font-weight: 700;
    padding: 0.1rem 0.4rem; border-radius: 9999px; line-height: 1.4;
}

/* ── Language Button ─────────────────────────────────────────────────── */
.fv-lang-btn {
    display: flex; align-items: center; gap: 0.2rem;
    padding: 0.375rem 0.625rem; border-radius: 0.5rem;
    border: 1px solid var(--fv-border, #1B3558);
    background-color: var(--fv-card, #112240);
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
    cursor: pointer; transition: all 0.15s ease; color: #E2E8F0;
}
.fv-lang-btn:hover { border-color: #48C4D8; color: #48C4D8; }
.fv-lang-active   { color: #48C4D8; }
.fv-lang-divider  { color: #1B3558; }
.fv-lang-inactive { color: #6B96B8; }

/* ── Logo ────────────────────────────────────────────────────────────── */
.fv-logo-mark { width: 2rem; height: 2rem; flex-shrink: 0; }

/* ── User Button ─────────────────────────────────────────────────────── */
.fv-user-btn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.375rem 0.75rem; border-radius: 0.5rem;
    border: 1px solid var(--fv-border, #1B3558);
    background-color: var(--fv-card, #112240);
    cursor: pointer; transition: all 0.15s ease;
}
.fv-user-btn:hover { border-color: #48C4D8; }
.fv-user-avatar {
    width: 1.5rem; height: 1.5rem; border-radius: 9999px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1490A8, #0C447C);
    color: white; font-size: 0.65rem; font-weight: 700; flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════════════════════
   LIGHT MODE OVERRIDES
══════════════════════════════════════════════════════════════════════ */
[data-theme="light"] .fv-nav-link { color: #0C447C; }
[data-theme="light"] .fv-nav-link:hover { color: #0C447C; background-color: rgba(20, 144, 168,0.08); }
[data-theme="light"] .fv-nav-active { color: #0C447C !important; background-color: rgba(20, 144, 168,0.12) !important; }

[data-theme="light"] .fv-sidebar-link { color: #0C447C; }
[data-theme="light"] .fv-sidebar-link:hover { color: #0C447C; background-color: rgba(20, 144, 168,0.08); }
[data-theme="light"] .fv-sidebar-active { color: #0C447C !important; background-color: rgba(20, 144, 168,0.10) !important; border-left: 3px solid #0C447C !important; }
[data-theme="light"] .fv-sidebar-settings:hover { color: #BA7517 !important; background-color: rgba(186,117,23,0.08) !important; }
[data-theme="light"] .fv-sidebar-active.fv-sidebar-settings { color: #0C447C !important; background-color: rgba(20, 144, 168,0.10) !important; border-left: 3px solid #0C447C !important; }

[data-theme="light"] .fv-mobile-link { color: #0C447C; }
[data-theme="light"] .fv-mobile-link:hover { color: #0C447C; background-color: rgba(20, 144, 168,0.08); }
[data-theme="light"] .fv-mobile-link-active { color: #0C447C !important; background-color: rgba(20, 144, 168,0.10) !important; }

[data-theme="light"] .fv-icon-btn { color: #0C447C; background-color: #ffffff; border-color: #cbd5e1; }
[data-theme="light"] .fv-icon-btn:hover { color: #0C447C; border-color: #1490A8; }

[data-theme="light"] .fv-tasks-btn { color: #0C447C; }
[data-theme="light"] .fv-tasks-btn:hover { color: #0C447C; background-color: rgba(20, 144, 168,0.08); }

[data-theme="light"] .fv-lang-btn { color: #0C447C; background-color: #ffffff; border-color: #cbd5e1; }
[data-theme="light"] .fv-lang-btn:hover { border-color: #1490A8; color: #0C447C; }
[data-theme="light"] .fv-lang-active   { color: #0C447C; }
[data-theme="light"] .fv-lang-divider  { color: #94a3b8; }
[data-theme="light"] .fv-lang-inactive { color: #64748b; }

[data-theme="light"] .fv-user-btn { background-color: #ffffff; border-color: #cbd5e1; }
[data-theme="light"] .fv-user-btn:hover { border-color: #1490A8; }

[data-theme="light"] .fv-text-primary { color: #0f172a; }
[data-theme="light"] .fv-text-muted   { color: #64748b; }
</style>