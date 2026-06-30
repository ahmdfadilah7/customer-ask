export const PERMISSION_GROUPS = [
  'dashboard',
  'cabang',
  'kebangsaan',
  'gelar',
  'scope-wilayah',
  'maskapai',
  'template-pesan',
  'corporate',
  'pegawai',
  'import',
  'whatsapp',
  'user',
  'role',
  'setting-website',
]

export const MANAGE_ACTIONS = ['create', 'update', 'delete']

export function featurePermission(feature, action) {
  return `${feature}-${action}`
}

export function managePermissions(feature) {
  return MANAGE_ACTIONS.map((action) => featurePermission(feature, action))
}

export const P = {
  DASHBOARD_VIEW: 'dashboard-view',
  CABANG_VIEW: 'cabang-view',
  KEBANGSAAN_VIEW: 'kebangsaan-view',
  GELAR_VIEW: 'gelar-view',
  SCOPE_WILAYAH_VIEW: 'scope-wilayah-view',
  MASKAPAI_VIEW: 'maskapai-view',
  TEMPLATE_PESAN_VIEW: 'template-pesan-view',
  CORPORATE_VIEW: 'corporate-view',
  PEGAWAI_VIEW: 'pegawai-view',
  IMPORT_CORPORATE: 'import-corporate',
  IMPORT_SERVICE: 'import-service',
  IMPORT_PEGAWAI: 'import-pegawai',
  WHATSAPP_KIRIM: 'whatsapp-kirim',
  USER_VIEW: 'user-view',
  ROLE_VIEW: 'role-view',
  ROLE_CREATE: 'role-create',
  ROLE_UPDATE: 'role-update',
  ROLE_DELETE: 'role-delete',
  SETTING_WEBSITE_VIEW: 'setting-website-view',
  SETTING_WEBSITE_UPDATE: 'setting-website-update',
}
