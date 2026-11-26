import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import '../css/app.css'

createApp(App).use(createPinia()).use(router).mount('#app')

// Igual ao NFC ERP: clicar em qualquer parte de um campo de hora abre o
// seletor (o padrão dos navegadores é só no ícone). Datas usam o CampoData.
document.addEventListener('click', (e) => {
  const campo = e.target.closest('input[type="time"]')
  if (campo && !campo.disabled && !campo.readOnly) {
    try { campo.showPicker?.() } catch (err) { /* navegador sem showPicker */ }
  }
})
