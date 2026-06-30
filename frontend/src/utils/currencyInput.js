/** Ambil digit saja dari input (untuk format rupiah Indonesia). */
export function extractDigits(value) {
  return String(value ?? '').replace(/\D/g, '')
}

/** Format angka ke notasi ribuan dengan pemisah titik: 10000 → 10.000 */
export function formatRupiahDigits(digits) {
  if (!digits) return ''
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

/** Format nilai bebas (bisa sudah berformat) ke tampilan rupiah. */
export function formatRupiahValue(value) {
  return formatRupiahDigits(extractDigits(value))
}

/** Nilai untuk disimpan ke API — pertahankan format titik ribuan. */
export function normalizeRupiahForSave(value) {
  const formatted = formatRupiahValue(value)
  return formatted || null
}
