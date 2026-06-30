import ElementPlus from 'element-plus'
import id from 'element-plus/es/locale/lang/id'
import 'element-plus/dist/index.css'

export function setupElementPlus(app) {
  app.use(ElementPlus, { locale: id, size: 'default' })
}
