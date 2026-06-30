/**
 * Terminologi bisnis Astrindo Travel Services:
 * - Corporate = perusahaan pelanggan Astrindo
 * - Pegawai (Customer di sistem) = user/pegawai dari corporate tersebut
 */
export const APP_TAGLINE = 'Portal Corporate & Pegawai'

export const TERMS = {
  corporate: {
    label: 'Corporate',
    singular: 'Corporate',
    plural: 'Corporate',
    description: 'Perusahaan pelanggan Astrindo Travel Services.',
    listDescription: 'Kelola profil perusahaan pelanggan, kontak PIC, dan service fee.',
    pageHelp: 'Menu ini khusus untuk data perusahaan pelanggan. Untuk mengelola pegawai individu, gunakan menu Pegawai. Untuk menambah data massal, gunakan menu Import.',
  },
  employee: {
    label: 'Pegawai',
    singular: 'Pegawai',
    plural: 'Pegawai',
    description: 'Pegawai (user) dari perusahaan pelanggan corporate.',
    listDescription: 'Daftar pegawai dari semua corporate. Tambah, edit, atau kirim WhatsApp dari sini.',
    pageHelp: 'Menu ini khusus untuk data pegawai perorangan. Untuk profil perusahaan, PIC kontak, atau service fee, buka menu Corporate.',
  },
}
