import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettingsStore } from '@/stores/siteSettings'
import { P } from '@/constants/permissions'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
          meta: { title: 'Dashboard', permission: P.DASHBOARD_VIEW },
        },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/ProfileView.vue'),
          meta: { title: 'Profil Saya' },
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/users/UserListView.vue'),
          meta: { title: 'User Management', permission: P.USER_VIEW },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/roles/RoleListView.vue'),
          meta: { title: 'Role Management', permission: P.ROLE_VIEW },
        },
        {
          path: 'settings/website',
          name: 'website-settings',
          component: () => import('@/views/settings/WebsiteSettingsView.vue'),
          meta: { title: 'Setting Website', permission: P.SETTING_WEBSITE_VIEW },
        },
        {
          path: 'master-data/branches',
          name: 'master-data-branches',
          component: () => import('@/views/master-data/BranchListView.vue'),
          meta: { title: 'Cabang', permission: P.CABANG_VIEW, group: 'master-data' },
        },
        {
          path: 'master-data/nationalities',
          name: 'master-data-nationalities',
          component: () => import('@/views/master-data/NationalityListView.vue'),
          meta: { title: 'Kebangsaan', permission: P.KEBANGSAAN_VIEW, group: 'master-data' },
        },
        {
          path: 'master-data/titles',
          name: 'master-data-titles',
          component: () => import('@/views/master-data/TitleListView.vue'),
          meta: { title: 'Gelar', permission: P.GELAR_VIEW, group: 'master-data' },
        },
        {
          path: 'master-data/region-scopes',
          name: 'master-data-region-scopes',
          component: () => import('@/views/master-data/RegionScopeListView.vue'),
          meta: { title: 'Scope Wilayah', permission: P.SCOPE_WILAYAH_VIEW, group: 'master-data' },
        },
        {
          path: 'master-data/airlines',
          name: 'master-data-airlines',
          component: () => import('@/views/master-data/AirlineListView.vue'),
          meta: { title: 'Maskapai', permission: P.MASKAPAI_VIEW, group: 'master-data' },
        },
        {
          path: 'message-templates',
          name: 'message-templates',
          component: () => import('@/views/message-templates/MessageTemplateListView.vue'),
          meta: { title: 'Template Pesan', permission: P.TEMPLATE_PESAN_VIEW, group: 'master-data' },
        },
        {
          path: 'customers',
          name: 'customer-list',
          component: () => import('@/views/customers/CustomerListView.vue'),
          meta: { title: 'Pegawai', permission: P.PEGAWAI_VIEW },
        },
        {
          path: 'employees/:id',
          name: 'employee-detail',
          component: () => import('@/views/customers/EmployeeDetailView.vue'),
          meta: { title: 'Detail Pegawai', permission: P.PEGAWAI_VIEW },
        },
        {
          path: 'customers/:id',
          redirect: (to) => ({ name: 'corporate-detail', params: { id: to.params.id } }),
        },
        {
          path: 'corporate',
          name: 'corporate-list',
          component: () => import('@/views/corporate/CorporateListView.vue'),
          meta: { title: 'Corporate (Pelanggan)', permission: P.CORPORATE_VIEW },
        },
        {
          path: 'corporate/:id',
          name: 'corporate-detail',
          component: () => import('@/views/corporate/CorporateDetailView.vue'),
          meta: { title: 'Detail Corporate', permission: P.CORPORATE_VIEW },
        },
        {
          path: 'import/corporate',
          name: 'import-corporate',
          component: () => import('@/views/corporate/CorporateImportView.vue'),
          meta: { title: 'Import Data Corporate', permission: P.IMPORT_CORPORATE, importType: 'corporate' },
        },
        {
          path: 'import/service',
          name: 'import-service',
          component: () => import('@/views/corporate/CorporateImportView.vue'),
          meta: { title: 'Import Data Service', permission: P.IMPORT_SERVICE, importType: 'service' },
        },
        {
          path: 'import/employee',
          name: 'import-employee',
          component: () => import('@/views/corporate/EmployeeImportView.vue'),
          meta: { title: 'Import Data Pegawai', permission: P.IMPORT_PEGAWAI },
        },
        {
          path: 'corporate/import',
          redirect: '/import/corporate',
        },
        {
          path: 'branches',
          redirect: '/master-data/branches',
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.user && to.meta.requiresAuth) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.user) {
    return { name: 'login' }
  }

  if (to.meta.guest && auth.user) {
    return { name: 'dashboard' }
  }

  if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  const siteSettings = useSiteSettingsStore()
  const base = siteSettings.settings.meta_title || siteSettings.siteName
  document.title = to.meta.title ? `${to.meta.title} — ${base}` : base
})

export default router
