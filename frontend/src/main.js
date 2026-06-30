import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { setupElementPlus } from '@/plugins/element-plus'
import { useSiteSettingsStore } from '@/stores/siteSettings'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
setupElementPlus(app)

app.component('ToastContainer', ToastContainer)
app.component('ConfirmDialog', ConfirmDialog)

const siteSettings = useSiteSettingsStore(pinia)
siteSettings.load().finally(() => {
  app.mount('#app')
})
