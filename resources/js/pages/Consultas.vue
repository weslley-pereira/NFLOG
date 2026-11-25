<template>
  <div class="p-6">
    <div class="-mx-6 -mt-6 mb-6 px-6 py-4 border-b border-gray-800 bg-gray-900 flex items-center justify-between">
      <div>
        <h1 class="text-sm font-medium text-white">Consultas e relatórios</h1>
        <p class="text-xs text-gray-500">Cada ofício respondido, com o relatório e o resumo SHA-256 dele</p>
      </div>
      <router-link to="/consulta" class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs rounded-lg">Nova consulta</router-link>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
      <table class="w-full text-xs">
        <thead class="text-gray-500 text-left"><tr>
          <th class="px-4 py-2 font-medium">Registrada em</th><th class="px-4 py-2 font-medium">Ofício</th><th class="px-4 py-2 font-medium">IP:porta</th>
          <th class="px-4 py-2 font-medium">Instante</th><th class="px-4 py-2 font-medium">Resultado</th><th class="px-4 py-2 font-medium">Por</th><th class="px-4 py-2"></th>
        </tr></thead>
        <tbody>
          <tr v-for="c in lista" :key="c.id" class="border-t border-gray-800">
            <td class="px-4 py-2 text-gray-400 whitespace-nowrap">{{ dh(c.created_at) }}</td>
            <td class="px-4 py-2 text-gray-200">{{ c.documento || '—' }}<div class="text-gray-500">{{ c.autoridade }}</div></td>
            <td class="px-4 py-2 font-mono text-gray-200">{{ c.ip }}{{ c.porta ? ':' + c.porta : '' }} <span class="text-gray-500">{{ c.protocolo?.toUpperCase() }}</span></td>
            <td class="px-4 py-2 text-gray-400 whitespace-nowrap">{{ dh(c.momento) }}</td>
            <td class="px-4 py-2" :class="c.conclusao === 'identificado' ? 'text-green-400' : 'text-yellow-300'">{{ titulos[c.conclusao] ?? c.conclusao }}</td>
            <td class="px-4 py-2 text-gray-400">{{ c.usuario }}</td>
            <td class="px-4 py-2 text-right whitespace-nowrap">
              <a :href="`/api/consultas/${c.id}/pdf`" target="_blank" class="px-2 py-1 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded">PDF</a>
              <div class="font-mono text-[10px] text-gray-600 mt-1" :title="c.relatorio_sha256">{{ c.relatorio_sha256?.slice(0, 16) }}…</div>
            </td>
          </tr>
          <tr v-if="carregado && !lista.length"><td colspan="7" class="px-4 py-10 text-center text-gray-500">Nenhuma consulta registrada</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const lista = ref([])
const carregado = ref(false)
const titulos = {
  identificado: 'Identificado', ambiguo: 'Mais de um usuário', sem_porta: 'Sem porta', nat_sem_sessao: 'Sem sessão de assinante',
  sem_registro: 'Sem registro', sem_dados: 'Sem dados no período',
}
const dh = (iso) => new Date(iso).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' })

onMounted(async () => {
  lista.value = (await api.get('/consultas')).data
  carregado.value = true
})
</script>
