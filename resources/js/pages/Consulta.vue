<template>
  <div class="p-6">
    <div class="-mx-6 -mt-6 mb-6 px-6 py-4 border-b border-gray-800 bg-gray-900">
      <h1 class="text-sm font-medium text-white">Nova consulta</h1>
      <p class="text-xs text-gray-500">Quem usava um endereço IP (e porta) num instante, com relatório para responder o ofício</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
      <!-- Formulário -->
      <div class="xl:col-span-2 space-y-4">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
          <h2 class="text-sm font-medium text-white mb-3">O que o ofício pede</h2>
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="rotulo">Endereço IP</label>
              <input v-model.trim="f.ip" class="campo font-mono" placeholder="45.171.253.135" />
            </div>
            <div>
              <label class="rotulo">Porta de origem</label>
              <input v-model.number="f.porta" type="number" min="1" max="65535" class="campo font-mono" placeholder="opcional" />
            </div>
            <div>
              <label class="rotulo">Data</label>
              <input v-model="f.data" type="date" class="campo" />
            </div>
            <div>
              <label class="rotulo">Hora</label>
              <input v-model="f.hora" type="time" step="1" class="campo" />
            </div>
            <div>
              <label class="rotulo">Protocolo</label>
              <select v-model="f.protocolo" class="campo">
                <option :value="null">Não informado</option>
                <option value="tcp">TCP</option>
                <option value="udp">UDP</option>
              </select>
            </div>
            <div class="col-span-3">
              <label class="rotulo">Fuso do horário informado</label>
              <select v-model="f.fuso" class="campo">
                <option value="America/Sao_Paulo">Horário de Brasília (GMT-3)</option>
                <option value="UTC">UTC / GMT</option>
                <option value="America/Manaus">Amazonas (GMT-4)</option>
                <option value="America/Rio_Branco">Acre (GMT-5)</option>
                <option value="America/Noronha">Fernando de Noronha (GMT-2)</option>
              </select>
              <p class="text-[11px] text-gray-500 mt-1">Plataformas como Meta e Google costumam informar em UTC. Confira no ofício.</p>
            </div>
          </div>
          <div v-if="erro" class="mt-3 px-3 py-2 bg-red-500/10 border border-red-500/30 rounded-lg text-xs text-red-400">{{ erro }}</div>
          <button @click="buscar" :disabled="buscando || !f.ip || !f.data || !f.hora"
            class="mt-4 w-full px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white text-sm rounded-lg">
            {{ buscando ? 'Consultando...' : 'Consultar' }}
          </button>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
          <h2 class="text-sm font-medium text-white mb-3">Dados do ofício (saem no relatório)</h2>
          <div class="space-y-3">
            <div><label class="rotulo">Autoridade solicitante</label><input v-model="f.autoridade" class="campo" placeholder="Ex.: Delegacia de Polícia de ..." /></div>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="rotulo">Nº do ofício / mandado</label><input v-model="f.documento" class="campo" /></div>
              <div><label class="rotulo">Recebido em</label><input v-model="f.recebido_em" type="date" class="campo" /></div>
            </div>
            <div><label class="rotulo">Processo / procedimento</label><input v-model="f.processo" class="campo" /></div>
          </div>
        </div>
      </div>

      <!-- Resultado -->
      <div class="xl:col-span-3">
        <div v-if="!r" class="bg-gray-900 border border-gray-800 rounded-xl p-10 text-center text-xs text-gray-500">
          O resultado aparece aqui. Nada é registrado até você gerar o relatório.
        </div>
        <div v-else class="space-y-4">
          <div class="rounded-xl p-4 border" :class="estilo.caixa">
            <div class="text-sm font-semibold" :class="estilo.texto">{{ titulos[r.conclusao] ?? r.conclusao }}</div>
            <p class="text-xs text-gray-300 mt-1">{{ r.explicacao }}</p>
          </div>

          <div v-if="sessoes.length" class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="text-gray-500 text-left"><tr>
                <th class="px-4 py-2 font-medium">Login</th><th class="px-4 py-2 font-medium">MAC</th><th class="px-4 py-2 font-medium">IP</th>
                <th class="px-4 py-2 font-medium">Início</th><th class="px-4 py-2 font-medium">Fim</th>
              </tr></thead>
              <tbody><tr v-for="s in sessoes" :key="s.login + s.inicio" class="border-t border-gray-800">
                <td class="px-4 py-2 font-mono text-white">{{ s.login }}</td><td class="px-4 py-2 font-mono text-gray-300">{{ s.mac || '—' }}</td>
                <td class="px-4 py-2 font-mono text-gray-300">{{ s.ip }}</td><td class="px-4 py-2 text-gray-400">{{ dh(s.inicio) }}</td>
                <td class="px-4 py-2 text-gray-400">{{ s.fim ? dh(s.fim) : 'conectado' }}</td>
              </tr></tbody>
            </table>
          </div>

          <div v-if="r.nat.length" class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
            <div class="px-4 py-2 text-xs text-gray-400 border-b border-gray-800">Sessões de NAT com esse IP e porta na janela pesquisada</div>
            <table class="w-full text-xs">
              <tbody><tr v-for="(n, i) in r.nat" :key="i" class="border-t border-gray-800 first:border-t-0" :class="n.cobre_instante ? 'bg-green-500/5' : ''">
                <td class="px-4 py-1.5 text-gray-400">{{ n.protocolo.toUpperCase() }}</td>
                <td class="px-4 py-1.5 font-mono text-gray-200">{{ n.privado }}</td>
                <td class="px-4 py-1.5 text-gray-400">{{ dh(n.abre) }} → {{ n.fecha ? dh(n.fecha) : 'sem fim na janela' }}</td>
                <td class="px-4 py-1.5" :class="n.cobre_instante ? 'text-green-400' : 'text-gray-600'">{{ n.cobre_instante ? 'cobre o instante' : 'fora' }}</td>
              </tr></tbody>
            </table>
          </div>

          <div v-if="r.integridade.length" class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-xs space-y-1">
            <div class="text-gray-400 mb-1">Cadeia de custódia</div>
            <div v-for="i in r.integridade" :key="i.dia">
              <span class="text-gray-200">{{ new Date(i.dia + 'T12:00').toLocaleDateString('pt-BR') }}:</span>
              <span :class="{ integro: 'text-green-400', falha: 'text-red-400' }[i.situacao] ?? 'text-gray-400'"> {{ i.detalhe }}</span>
            </div>
          </div>

          <button @click="registrar" :disabled="registrando"
            class="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white text-sm rounded-lg">
            {{ registrando ? 'Gerando...' : 'Registrar consulta e gerar relatório (PDF)' }}
          </button>
          <p class="text-[11px] text-gray-500 text-center">O servidor refaz a consulta ao registrar, busca a qualificação do titular no sistema de gestão (só se identificado) e guarda o resumo SHA-256 do PDF.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api, { mensagemErro } from '@/services/api'

const router = useRouter()
const f = ref({ ip: '', porta: null, data: '', hora: '', protocolo: null, fuso: 'America/Sao_Paulo', autoridade: '', documento: '', processo: '', recebido_em: '' })
const r = ref(null)
const erro = ref(null)
const buscando = ref(false)
const registrando = ref(false)

const titulos = {
  identificado: 'Usuário identificado', ambiguo: 'Mais de um usuário no instante', sem_porta: 'Não individualizável sem a porta',
  nat_sem_sessao: 'Endereço privado sem sessão de assinante', sem_registro: 'Sem registro correspondente', sem_dados: 'Sem registros de NAT no período',
}
const estilo = computed(() => r.value?.conclusao === 'identificado'
  ? { caixa: 'bg-green-500/10 border-green-500/30', texto: 'text-green-400' }
  : ['sem_registro'].includes(r.value?.conclusao)
    ? { caixa: 'bg-gray-800/60 border-gray-700', texto: 'text-gray-200' }
    : { caixa: 'bg-yellow-500/10 border-yellow-500/30', texto: 'text-yellow-300' })
const sessoes = computed(() => {
  const lista = (r.value?.candidatos ?? []).map((c) => c.sessao).filter(Boolean)
  if (r.value?.ip_dedicado) lista.push(r.value.ip_dedicado)
  return lista.filter((s, i) => lista.findIndex((o) => o.login === s.login && o.inicio === s.inicio) === i)
})
const dh = (iso) => new Date(iso).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' })

const corpo = () => {
  const dados = { ...f.value, hora: f.value.hora.length === 5 ? f.value.hora + ':00' : f.value.hora }
  Object.keys(dados).forEach((k) => (dados[k] === '' ? (dados[k] = null) : null))
  return dados
}

const buscar = async () => {
  buscando.value = true
  erro.value = null
  r.value = null
  try {
    r.value = (await api.post('/consultas/buscar', corpo())).data
  } catch (e) {
    erro.value = mensagemErro(e, 'Erro na consulta')
  } finally {
    buscando.value = false
  }
}

const registrar = async () => {
  registrando.value = true
  try {
    const { data } = await api.post('/consultas', corpo())
    window.open(`/api/consultas/${data.id}/pdf`, '_blank')
    router.push('/consultas')
  } catch (e) {
    erro.value = mensagemErro(e, 'Erro ao registrar')
  } finally {
    registrando.value = false
  }
}
</script>

<style scoped>
.rotulo { display: block; font-size: 11px; color: var(--color-gray-400); margin-bottom: 4px; }
.campo { width: 100%; padding: 8px 10px; background: var(--color-gray-800); border: 1px solid var(--color-gray-700); border-radius: 8px; font-size: 13px; color: var(--color-gray-100); }
.campo:focus { outline: none; border-color: var(--color-blue-500); }
</style>
