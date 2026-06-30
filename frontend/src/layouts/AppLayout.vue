<script setup>
import { computed, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  Building2,
  LogOut,
  Menu,
  X,
  ChevronRight,
  ChevronDown,
  Users,
  Shield,
  Database,
  MapPin,
  Globe,
  UserRound,
  Plane,
  Earth,
  FileUp,
  Building,
  MessageSquare,
  Receipt,
  Settings,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettingsStore } from '@/stores/siteSettings'
import { useConfirm } from '@/composables/useConfirm'
import { TERMS } from '@/constants/terminology'
import { P } from '@/constants/permissions'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const siteSettings = useSiteSettingsStore()
const confirm = useConfirm()
const sidebarOpen = ref(false)
const masterDataOpen = ref(false)
const importOpen = ref(false)

const appName = computed(() => siteSettings.siteName)
const appTagline = computed(() => siteSettings.tagline)

const showDashboard = computed(() => auth.hasPermission(P.DASHBOARD_VIEW))

const mainNavItems = computed(() =>
  [
    { path: '/corporate', label: TERMS.corporate.label, icon: Building2, permission: P.CORPORATE_VIEW },
    { path: '/customers', label: TERMS.employee.label, icon: UserRound, permission: P.PEGAWAI_VIEW },
    { path: '/users', label: 'Users', icon: Users, permission: P.USER_VIEW },
    { path: '/roles', label: 'Roles', icon: Shield, permission: P.ROLE_VIEW },
    { path: '/settings/website', label: 'Setting Website', icon: Settings, permission: P.SETTING_WEBSITE_VIEW },
  ].filter((item) => auth.hasPermission(item.permission)),
)

const importItems = computed(() =>
  [
    { path: '/import/corporate', label: 'Data Corporate', icon: Building, permission: P.IMPORT_CORPORATE },
    { path: '/import/service', label: 'Data Service', icon: Receipt, permission: P.IMPORT_SERVICE },
    { path: '/import/employee', label: 'Data Pegawai', icon: Users, permission: P.IMPORT_PEGAWAI },
  ].filter((item) => auth.hasPermission(item.permission)),
)

const showImportMenu = computed(() => importItems.value.length > 0)

const masterDataItems = computed(() =>
  [
    { path: '/master-data/branches', label: 'Cabang', icon: MapPin, permission: P.CABANG_VIEW },
    { path: '/master-data/nationalities', label: 'Kebangsaan', icon: Globe, permission: P.KEBANGSAAN_VIEW },
    { path: '/master-data/titles', label: 'Gelar', icon: UserRound, permission: P.GELAR_VIEW },
    { path: '/master-data/region-scopes', label: 'Scope Wilayah', icon: Earth, permission: P.SCOPE_WILAYAH_VIEW },
    { path: '/master-data/airlines', label: 'Maskapai', icon: Plane, permission: P.MASKAPAI_VIEW },
    { path: '/message-templates', label: 'Template Pesan', icon: MessageSquare, permission: P.TEMPLATE_PESAN_VIEW },
  ].filter((item) => auth.hasPermission(item.permission)),
)

const showMasterData = computed(() => masterDataItems.value.length > 0)

const pageTitle = computed(() => route.meta.title || 'Dashboard')

const breadcrumb = computed(() => {
  if (route.path.startsWith('/import')) return 'Import'
  if (route.path.startsWith('/master-data') || route.path.startsWith('/message-templates')) return 'Master Data'
  return null
})

const roleLabel = computed(() => auth.user?.role_label || auth.user?.roles?.[0] || '')

const isMasterDataActive = computed(() =>
  route.path.startsWith('/master-data') || route.path.startsWith('/message-templates'),
)

const isImportActive = computed(() => route.path.startsWith('/import'))

watch(isMasterDataActive, (active) => {
  if (active) masterDataOpen.value = true
}, { immediate: true })

watch(isImportActive, (active) => {
  if (active) importOpen.value = true
}, { immediate: true })

function isActive(path) {
  if (path === '/') return route.path === '/'
  if (path === '/profile') return route.path === '/profile'
  if (path === '/customers') {
    return route.path.startsWith('/customers') || route.path.startsWith('/employees')
  }
  if (path === '/corporate') return route.path.startsWith('/corporate')
  return route.path.startsWith(path)
}

function toggleMasterData() {
  masterDataOpen.value = !masterDataOpen.value
}

function toggleImportMenu() {
  importOpen.value = !importOpen.value
}

async function handleLogout() {
  const confirmed = await confirm.confirm({
    title: 'Logout',
    message: 'Apakah Anda yakin ingin keluar dari sistem?',
    confirmLabel: 'Ya, Logout',
    cancelLabel: 'Batal',
    variant: 'warning',
  })

  if (!confirmed) return

  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen">
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
      @click="sidebarOpen = false"
    />

    <aside
      class="fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 flex-col bg-gradient-to-b from-brand-950 via-brand-900 to-brand-950 text-white shadow-2xl transition-transform"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
      <div class="flex shrink-0 items-center justify-between border-b border-white/10 px-6 py-5">
        <div class="flex items-center gap-2">
          <div v-if="siteSettings.settings.logo_url" class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/10 ring-1 ring-white/20">
            <img :src="siteSettings.settings.logo_url" :alt="appName" class="max-h-7 max-w-7 object-contain" />
          </div>
          <div v-else class="flex size-9 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
            <Building2 class="size-5" />
          </div>
          <div>
            <h1 class="text-sm font-bold leading-tight">{{ appName }}</h1>
            <p class="text-[11px] text-brand-200">{{ appTagline }}</p>
          </div>
        </div>
        <button type="button" class="rounded-lg p-1.5 text-brand-200 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false">
          <X class="size-5" />
        </button>
      </div>

      <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-4">
        <RouterLink
          v-if="showDashboard"
          to="/"
          class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
          :class="isActive('/')
            ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/20'
            : 'text-brand-200 hover:bg-white/10 hover:text-white'"
          @click="sidebarOpen = false"
        >
          <LayoutDashboard class="size-5 shrink-0" />
          Dashboard
          <ChevronRight v-if="isActive('/')" class="ml-auto size-4 opacity-60" />
        </RouterLink>

        <!-- Master Data dropdown -->
        <div v-if="showMasterData">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
            :class="isMasterDataActive
              ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/20'
              : 'text-brand-200 hover:bg-white/10 hover:text-white'"
            @click="toggleMasterData"
          >
            <Database class="size-5 shrink-0" />
            Master Data
            <ChevronDown
              class="ml-auto size-4 opacity-60 transition-transform"
              :class="masterDataOpen ? 'rotate-180' : ''"
            />
          </button>

          <div v-show="masterDataOpen" class="ml-4 mt-1 space-y-0.5 border-l border-white/10 pl-3">
            <RouterLink
              v-for="item in masterDataItems"
              :key="item.path"
              :to="item.path"
              class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition"
              :class="route.path === item.path
                ? 'bg-white/10 font-medium text-white'
                : 'text-brand-300 hover:bg-white/5 hover:text-white'"
              @click="sidebarOpen = false"
            >
              <component :is="item.icon" class="size-4 shrink-0 opacity-70" />
              {{ item.label }}
            </RouterLink>
          </div>
        </div>

        <!-- Import dropdown -->
        <div v-if="showImportMenu">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
            :class="isImportActive
              ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/20'
              : 'text-brand-200 hover:bg-white/10 hover:text-white'"
            @click="toggleImportMenu"
          >
            <FileUp class="size-5 shrink-0" />
            Import
            <ChevronDown
              class="ml-auto size-4 opacity-60 transition-transform"
              :class="importOpen ? 'rotate-180' : ''"
            />
          </button>

          <div v-show="importOpen" class="ml-4 mt-1 space-y-0.5 border-l border-white/10 pl-3">
            <RouterLink
              v-for="item in importItems"
              :key="item.path"
              :to="item.path"
              class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition"
              :class="route.path === item.path
                ? 'bg-white/10 font-medium text-white'
                : 'text-brand-300 hover:bg-white/5 hover:text-white'"
              @click="sidebarOpen = false"
            >
              <component :is="item.icon" class="size-4 shrink-0 opacity-70" />
              {{ item.label }}
            </RouterLink>
          </div>
        </div>

        <RouterLink
          v-for="item in mainNavItems"
          :key="item.path"
          :to="item.path"
          class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
          :class="isActive(item.path)
            ? 'bg-white/15 text-white shadow-sm ring-1 ring-white/20'
            : 'text-brand-200 hover:bg-white/10 hover:text-white'"
          @click="sidebarOpen = false"
        >
          <component :is="item.icon" class="size-5 shrink-0" />
          {{ item.label }}
          <ChevronRight v-if="isActive(item.path)" class="ml-auto size-4 opacity-60" />
        </RouterLink>
      </nav>

      <div class="shrink-0 border-t border-white/10 p-4">
        <RouterLink
          to="/profile"
          class="flex items-center gap-3 rounded-xl bg-white/5 px-3 py-3 ring-1 ring-white/10 transition hover:bg-white/10"
          @click="sidebarOpen = false"
        >
          <div class="flex size-9 items-center justify-center rounded-full bg-brand-500 text-sm font-bold">
            {{ auth.user?.name?.charAt(0) || 'A' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ auth.user?.name }}</p>
            <p class="truncate text-xs text-brand-300">Profil &amp; password</p>
          </div>
        </RouterLink>
      </div>
    </aside>

    <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:ml-72">
      <header class="sticky top-0 z-30 border-b border-slate-200/60 bg-white/90 px-4 py-3.5 shadow-sm shadow-slate-900/[0.03] backdrop-blur-md sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <button type="button" class="rounded-xl border border-slate-200 bg-white p-2 text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 lg:hidden" @click="sidebarOpen = true">
              <Menu class="size-5" />
            </button>
            <div>
              <p v-if="breadcrumb" class="text-[11px] font-semibold uppercase tracking-wider text-brand-600">
                {{ breadcrumb }}
              </p>
              <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ pageTitle }}</h2>
            </div>
          </div>
          <div class="flex items-center gap-2 sm:gap-3">
            <RouterLink
              to="/profile"
              class="flex max-w-[220px] items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-2 py-1.5 shadow-sm transition hover:border-brand-200 hover:bg-brand-50/40 sm:max-w-xs sm:px-3 sm:py-2"
              :class="route.path === '/profile' ? 'ring-2 ring-brand-200' : ''"
            >
              <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
                {{ auth.user?.name?.charAt(0) || 'A' }}
              </div>
              <div class="min-w-0 hidden sm:block">
                <p class="truncate text-sm font-semibold text-slate-900">{{ auth.user?.name }}</p>
                <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
              </div>
              <span
                v-if="roleLabel"
                class="hidden rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 md:inline"
              >
                {{ roleLabel }}
              </span>
            </RouterLink>

            <button
              type="button"
              class="btn-danger shrink-0 !py-2 !text-xs"
              title="Logout"
              @click="handleLogout"
            >
              <LogOut class="size-4" />
              <span class="hidden sm:inline">Logout</span>
            </button>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
