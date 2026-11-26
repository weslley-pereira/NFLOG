<template>
  <div class="p-6">
    <div class="-mx-6 -mt-6 mb-6 px-6 py-4 border-b border-gray-800 bg-gray-900">
      <h1 class="text-sm font-medium text-white">Minha conta</h1>
      <p class="text-xs text-gray-500">{{ auth.usuario?.email }}</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-5xl">
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <div class="flex items-center justify-between mb-1">
          <h2 class="text-sm font-medium text-white">Verificação em duas etapas</h2>
          <span class="text-[11px] px-2 py-0.5 rounded-full" :class="auth.usuario?.dois_fatores ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-300'">
            {{ auth.usuario?.dois_fatores ? 'Ativa' : 'Desativada' }}</span>
        </div>
        <p class="text-xs text-gray-500 mb-4">Além da senha, o login pede um código de 6 dígitos do aplicativo no celular (Google Authenticator, Microsoft Authenticator, Authy...).</p>

        <template v-if="!auth.usuario?.dois_fatores">
          <button v-if="!novo" @click="comecar" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Ativar</button>
          <div v-else class="space-y-3">
            <p class="text-xs text-gray-300">1. No aplicativo, adicione uma conta e leia o QR Code:</p>
            <img :src="novo.qr" alt="QR Code" class="w-44 h-44 bg-white rounded-lg p-1" />
            <p class="text-[11px] text-gray-500">Sem câmera? Digite a chave: <span class="font-mono text-gray-300 break-all">{{ novo.segredo }}</span></p>
            <p class="text-xs text-gray-300">2. Digite o código que o aplicativo mostra:</p>
            <div class="flex gap-2">
              <input v-model="codigo" inputmode="numeric" maxlength="6" class="campo font-mono w-32 tracking-widest" placeholder="000000" @keyup.enter="ativar" />
              <button @click="ativar" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Confirmar</button>
            </div>
          </div>
        </template>
        <template v-else>
          <p class="text-xs text-gray-400 mb-2">Para desativar, confirme a sua senha:</p>
          <div class="flex gap-2">
            <input v-model="senhaDesativar" type="password" class="campo" autocomplete="current-password" />
            <button @click="desativar" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-red-400 text-sm rounded-lg whitespace-nowrap">Desativar</button>
          </div>
          <p class="text-[11px] text-gray-500 mt-3">Perdeu o celular? Quem tem acesso ao servidor desativa com <span class="font-mono">php artisan nflog:2fa-desativar {{ auth.usuario?.email }}</span>.</p>
        </template>
      </div>

      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 space-y-6">
        <div>
        <h2 class="text-sm font-medium text-white mb-3">Trocar e-mail</h2>
        <div class="space-y-3">
          <div><label class="rotulo">Novo e-mail</label><input v-model="e.email" type="email" class="campo" autocomplete="email" /></div>
          <div><label class="rotulo">Senha atual (para confirmar)</label><input v-model="e.senha" type="password" class="campo" autocomplete="current-password" /></div>
        </div>
        <button @click="trocarEmail" :disabled="!e.email || !e.senha" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white text-sm rounded-lg">Salvar novo e-mail</button>
        </div>

        <div class="pt-5 border-t border-gray-800">
        <h2 class="text-sm font-medium text-white mb-3">Trocar senha</h2>
        <div class="space-y-3">
          <div><label class="rotulo">Senha atual</label><input v-model="s.atual" type="password" class="campo" autocomplete="current-password" /></div>
          <div><label class="rotulo">Nova senha (mínimo 10 caracteres)</label><input v-model="s.nova" type="password" class="campo" autocomplete="new-password" /></div>
          <div><label class="rotulo">Repita a nova senha</label><input v-model="s.nova_confirmation" type="password" class="campo" autocomplete="new-password" /></div>
        </div>
        <button @click="trocarSenha" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Salvar nova senha</button>
        </div>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 right-6 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white shadow-xl z-50">{{ toast }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api, { mensagemErro } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const novo = ref(null)
const codigo = ref('')
const senhaDesativar = ref('')
const s = ref({ atual: '', nova: '', nova_confirmation: '' })
const e = ref({ email: '', senha: '' })
const toast = ref(null)
const aviso = (msg) => { toast.value = msg; setTimeout(() => (toast.value = null), 3500) }

const comecar = async () => {
  try { novo.value = (await api.get('/conta/2fa/novo')).data } catch (e) { aviso(mensagemErro(e)) }
}
const ativar = async () => {
  try {
    aviso((await api.post('/conta/2fa/ativar', { codigo: codigo.value })).data.mensagem)
    novo.value = null; codigo.value = ''
    await auth.carregar()
  } catch (e) { aviso(mensagemErro(e)) }
}
const desativar = async () => {
  try {
    aviso((await api.post('/conta/2fa/desativar', { senha: senhaDesativar.value })).data.mensagem)
    senhaDesativar.value = ''
    await auth.carregar()
  } catch (e) { aviso(mensagemErro(e)) }
}
const trocarEmail = async () => {
  try {
    aviso((await api.put('/conta/email', e.value)).data.mensagem)
    e.value = { email: '', senha: '' }
    await auth.carregar()
  } catch (err) { aviso(mensagemErro(err)) }
}
const trocarSenha = async () => {
  try {
    aviso((await api.put('/conta/senha', s.value)).data.mensagem)
    s.value = { atual: '', nova: '', nova_confirmation: '' }
  } catch (e) { aviso(mensagemErro(e)) }
}
</script>

<style scoped>
.rotulo { display: block; font-size: 11px; color: var(--color-gray-400); margin-bottom: 4px; }
.campo { width: 100%; padding: 8px 10px; background: var(--color-gray-800); border: 1px solid var(--color-gray-700); border-radius: 8px; font-size: 13px; color: var(--color-gray-100); }
.campo:focus { outline: none; border-color: var(--color-blue-500); }
</style>
