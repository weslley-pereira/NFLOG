<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-950 p-4 fundo-app">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2.5 mb-2">
          <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center"><ScrollText class="w-5 h-5 text-white" /></div>
          <h1 class="text-xl font-bold text-white">NF Log</h1>
        </div>
        <p class="text-sm text-gray-500">Registros de conexão e relatórios para ofícios</p>
      </div>

      <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
        <h2 class="text-sm font-medium text-white mb-5">Acesse sua conta</h2>
        <div v-if="erro" class="mb-4 px-3 py-2 bg-red-500/10 border border-red-500/30 rounded-lg text-xs text-red-400">{{ erro }}</div>
        <form @submit.prevent="entrar" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">E-mail</label>
            <input v-model="form.email" type="email" required autofocus autocomplete="username"
              class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Senha</label>
            <input v-model="form.password" type="password" required autocomplete="current-password"
              class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-blue-500" />
          </div>
          <button type="submit" :disabled="carregando"
            class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
            {{ carregando ? 'Entrando...' : 'Entrar' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ScrollText } from '@lucide/vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { mensagemErro } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const form = ref({ email: '', password: '' })
const erro = ref(null)
const carregando = ref(false)

const entrar = async () => {
  carregando.value = true
  erro.value = null
  try {
    await api.get('/sanctum/csrf-cookie', { baseURL: '/' })
    await api.post('/login', form.value)
    await auth.carregar()
    router.push('/')
  } catch (e) {
    erro.value = mensagemErro(e, 'Não foi possível entrar')
  } finally {
    carregando.value = false
  }
}
</script>
