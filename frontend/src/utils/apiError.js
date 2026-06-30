export function getApiErrorMessage(err, fallback = 'Terjadi kesalahan.') {
  if (!err?.response) {
    if (err?.code === 'ERR_NETWORK' || err?.code === 'ECONNABORTED') {
      return 'Tidak dapat terhubung ke server. Pastikan backend Laravel sedang berjalan (php artisan serve).'
    }

    return err?.message || fallback
  }

  const data = err.response.data

  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ')
  }

  return data?.message || fallback
}
