<template>
  <div class="flex h-screen bg-gray-950 text-gray-100 overflow-hidden">
    <!-- Menu: lateral no computador, barra no topo no celular -->
    <aside class="hidden md:flex w-56 flex-col border-r border-gray-800" style="background: var(--cor-menu)">
      <div class="px-4 py-4 flex items-center gap-2.5 border-b border-gray-800">
        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center"><ScrollText class="w-4.5 h-4.5 text-white" /></div>
        <div>
          <div class="text-sm font-semibold text-white">NF Log</div>
          <div class="text-[11px] text-gray-500">Registros de conexão</div>
        </div>
      </div>
      <nav class="flex-1 py-3 space-y-0.5">
        <router-link v-for="item in menu" :key="item.to" :to="item.to"
          class="flex items-center gap-3 mx-2 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800/60"
          exact-active-class="bg-blue-600/15 !text-blue-400">
          <component :is="item.icone" class="w-4 h-4" />{{ item.rotulo }}
        </router-link>
      </nav>
      <div class="px-4 py-3 border-t border-gray-800 text-xs text-gray-500">
        <div class="truncate text-gray-300">{{ auth.usuario?.name }}</div>
        <button @click="sair" class="mt-1 hover:text-white flex items-center gap-1"><LogOut class="w-3.5 h-3.5" />Sair</button>
      </div>
    </aside>

    <div class="md:hidden fixed top-0 inset-x-0 z-40 flex items-center gap-1 overflow-x-auto px-2 py-2 border-b border-gray-800" style="background: var(--cor-menu)">
      <router-link v-for="item in menu" :key="item.to" :to="item.to"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-gray-400 whitespace-nowrap" exact-active-class="bg-blue-600/15 !text-blue-400">
        <component :is="item.icone" class="w-3.5 h-3.5" />{{ item.rotulo }}
      </router-link>
    </div>

    <main class="flex-1 overflow-y-auto pt-12 md:pt-0">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { Gauge, LogOut, ScrollText, Search, Settings, FileText } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const menu = [
  { to: '/', rotulo: 'Painel', icone: Gauge },
  { to: '/consulta', rotulo: 'Nova consulta', icone: Search },
  { to: '/consultas', rotulo: 'Consultas e relatórios', icone: FileText },
  { to: '/configuracoes', rotulo: 'Configurações', icone: Settings },
]

const sair = async () => {
  await auth.sair()
  router.push('/login')
}
</script>
