<template>
  <div class="p-6">
    <div class="-mx-6 -mt-6 mb-6 px-6 py-4 border-b border-gray-800 bg-gray-900 flex items-center justify-between">
      <div>
        <h1 class="text-sm font-medium text-white">Painel</h1>
        <p class="text-xs text-gray-500">Se algo aqui parar, um ofício sobre esse período fica sem resposta</p>
      </div>
      <button @click="carregar" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded-lg">Atualizar</button>
    </div>

    <div v-if="!s" class="flex justify-center py-16"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div></div>
    <template v-else>
      <div v-if="s.licenca && s.licenca.situacao !== 'valida'" class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/30 rounded-xl text-xs text-red-300">
        {{ s.licenca.mensagem }} <router-link to="/configuracoes" class="underline">Configurações</router-link>
      </div>
      <div v-if="!auth.usuario?.dois_fatores" class="mb-4 px-4 py-3 bg-yellow-500/10 border border-yellow-500/30 rounded-xl text-xs text-yellow-300">
        O painel guarda dados pessoais de assinantes: ative a verificação em duas etapas em <router-link to="/conta" class="underline">Minha conta</router-link>.
      </div>
      <div v-if="!s.empresa_configurada" class="mb-4 px-4 py-3 bg-yellow-500/10 border border-yellow-500/30 rounded-xl text-xs text-yellow-300">
        Preencha os dados da empresa em <router-link to="/configuracoes" class="underline">Configurações</router-link>: eles saem no cabeçalho dos relatórios.
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <div v-for="c in cartoes" :key="c.titulo" class="bg-gray-900 border border-gray-800 rounded-xl p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs text-gray-500">{{ c.titulo }}</span>
            <span class="w-2.5 h-2.5 rounded-full" :class="{ ok: 'bg-green-500', alerta: 'bg-yellow-500', erro: 'bg-red-500' }[c.estado]"></span>
          </div>
          <div class="text-lg font-semibold text-white mt-1">{{ c.valor }}</div>
          <div class="text-xs text-gray-500 mt-0.5">{{ c.detalhe }}</div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const s = ref(null)

const ha = (iso) => {
  if (!iso) return null
  const min = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  return min < 1 ? 'agora' : min < 60 ? `há ${min} min` : min < 1440 ? `há ${Math.round(min / 60)} h` : `há ${Math.round(min / 1440)} dias`
}
const minutos = (iso) => (iso ? (Date.now() - new Date(iso).getTime()) / 60000 : Infinity)
const gb = (b) => (b / 1024 ** 3).toFixed(1)

const cartoes = computed(() => {
  const d = s.value
  const f = d.fechamento
  return [
    { titulo: 'Log de NAT', valor: d.nat.eventos_ultima_hora.toLocaleString('pt-BR') + ' eventos/h',
      detalhe: d.nat.ultimo_evento ? `último ${ha(d.nat.ultimo_evento)}` : 'nenhum evento recebido',
      estado: minutos(d.nat.ultimo_evento) < 10 ? 'ok' : 'erro' },
    { titulo: 'Sessões PPPoE', valor: d.pppoe.sessoes_abertas + ' conectadas',
      detalhe: d.pppoe.ultimo_evento ? `último login/logout ${ha(d.pppoe.ultimo_evento)}` : 'nenhum login/logout recebido',
      estado: d.pppoe.sessoes_total ? 'ok' : 'alerta' },
    { titulo: 'Fechamento diário', valor: f ? new Date(f.dia + 'T12:00').toLocaleDateString('pt-BR') : '—',
      detalhe: f ? f.detalhe : 'nenhum dia fechado ainda',
      estado: !f ? 'alerta' : f.situacao === 'integro' ? 'ok' : 'erro' },
    { titulo: 'Disco dos registros', valor: d.disco ? `${gb(d.disco.livre)} GB livres` : '—',
      detalhe: d.disco ? `de ${gb(d.disco.total)} GB` : 'pasta de registros não encontrada',
      estado: !d.disco ? 'erro' : d.disco.livre / d.disco.total < 0.15 ? 'alerta' : 'ok' },
    { titulo: 'Pesquisa (VictoriaLogs)', valor: d.victorialogs ? 'No ar' : 'Fora do ar', detalhe: 'serviço de busca dos registros',
      estado: d.victorialogs ? 'ok' : 'erro' },
    { titulo: 'Consultas registradas', valor: d.consultas, detalhe: 'ofícios respondidos pelo sistema', estado: 'ok' },
  ]
})

const carregar = async () => {
  s.value = null
  const { data } = await api.get('/painel/saude')
  s.value = data
}

onMounted(carregar)
</script>
