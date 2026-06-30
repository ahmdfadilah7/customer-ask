/** Label & grup kolom — selaras dengan manifest import backend. */
export const CORPORATE_IMPORT_GROUPS = [
  { key: 'identity', label: 'Identitas' },
  { key: 'profile', label: 'Profil Operasional' },
  { key: 'materai', label: 'Materai' },
  { key: 'contract', label: 'Periode Kontrak' },
  { key: 'note', label: 'Catatan' },
]

export const CORPORATE_INVOICE_METHODS = [
  { value: '', label: '— Pilih —' },
  { value: 'print', label: 'Print' },
  { value: 'email', label: 'Email' },
  { value: 'print_email', label: 'Print & Email' },
  { value: 'no', label: 'Tidak' },
]

export const YES_NO_OPTIONS = [
  { value: '', label: '—' },
  { value: 'yes', label: 'Ya' },
  { value: 'no', label: 'Tidak' },
]

export function formatCustomerNameForImport(customer) {
  if (!customer) return ''
  const parts = [customer.name, ...(customer.aliases ?? [])].filter(Boolean)
  return parts.join(' / ')
}

export function parseYesNo(value) {
  if (value === '' || value === null || value === undefined) return null
  if (value === true || value === 'yes' || value === '1') return true
  if (value === false || value === 'no' || value === '0') return false
  return null
}

export function yesNoFromBool(value) {
  if (value === true) return 'yes'
  if (value === false) return 'no'
  return ''
}

export const EMPLOYEE_IMPORT_GROUPS = [
  { key: 'identity', label: 'Identitas' },
  { key: 'employee', label: 'Data Employee' },
]

export const EMPLOYEE_IMPORT_HINT = 'Kosongkan dengan "-" jika tidak ada (sesuai template import).'
