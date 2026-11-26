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

      <div v-if="g" class="mt-6 space-y-4">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <CartaoGrafico titulo="Eventos de NAT por hora" :subtitulo="g.nat ? 'Últimas 24 h · se cair a zero, a coleta parou' : 'Pesquisa fora do ar: sem dados'"
            :tabela="tabelaHoras([['Eventos', g.nat || []]])">
            <div class="h-52"><canvas ref="cNat"></canvas></div>
          </CartaoGrafico>
          <CartaoGrafico titulo="Logins e logouts PPPoE por hora" subtitulo="Últimas 24 h, pelo syslog do roteador"
            :legenda="[{ nome: 'Logins', cor: COR.azul }, { nome: 'Logouts', cor: COR.laranja }]"
            :tabela="tabelaHoras([['Logins', g.logins], ['Logouts', g.logouts]])">
            <div class="h-52"><canvas ref="cPppoe"></canvas></div>
          </CartaoGrafico>
        </div>

        <CartaoGrafico titulo="Assinantes conectados" :subtitulo="`Hora a hora desde ${dh(g.conectados[0]?.hora)}`"
          :tabela="{ colunas: ['Hora', 'Conectados'], linhas: g.conectados.map((c) => [dh(c.hora), numero(c.n)]).reverse() }">
          <div class="h-56"><canvas ref="cConectados"></canvas></div>
        </CartaoGrafico>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
          <CartaoGrafico class="xl:col-span-2" titulo="Volume guardado por dia" subtitulo="Arquivos fechados e compactados (sem a base de pesquisa), últimos 30 dias"
            :legenda="[{ nome: 'NAT', cor: COR.azul }, { nome: 'Syslog', cor: COR.laranja }]"
            :tabela="{ colunas: ['Dia', 'NAT', 'Syslog'], linhas: g.volume.map((v) => [dia(v.dia), bytes(v.nat), bytes(v.syslog)]).reverse() }">
            <div class="h-56"><canvas ref="cVolume"></canvas></div>
          </CartaoGrafico>
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 flex flex-col justify-center">
            <div class="text-xs text-gray-500">O disco aguenta</div>
            <div class="text-3xl font-semibold text-white mt-1">{{ g.disco.dias_restantes !== null ? numero(g.disco.dias_restantes) + ' dias' : '—' }}</div>
            <div class="text-xs text-gray-500 mt-2" v-if="g.disco.dias_restantes !== null">
              no ritmo dos últimos {{ g.disco.dias_medidos }} dias ({{ bytes(g.disco.gasto_por_dia) }}/dia, contando a base de pesquisa) · {{ bytes(g.disco.livre) }} livres
            </div>
            <div class="text-xs text-gray-500 mt-2" v-else>
              Calculando: o painel mede o espaço livre uma vez por dia e precisa de pelo menos 3 dias ({{ g.disco.dias_medidos }} até agora).
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
          <div class="xl:col-span-2 bg-gray-900 border border-gray-800 rounded-xl p-4">
            <h2 class="text-sm font-medium text-white">Fechamento diário</h2>
            <p class="text-xs text-gray-500 mb-3">Arquivos compactados, resumidos (SHA-256) e assinados após a meia-noite · últimos 14 dias</p>
            <div class="grid grid-cols-7 sm:grid-cols-14 gap-1.5" style="grid-template-columns: repeat(auto-fill, minmax(44px, 1fr))">
              <div v-for="f in g.fechamentos" :key="f.dia" :title="`${dia(f.dia)}: ${situacoes[f.situacao].texto}`"
                class="rounded-lg border px-1 py-2 flex flex-col items-center gap-1" :class="situacoes[f.situacao].caixa">
                <component :is="situacoes[f.situacao].icone" class="w-4 h-4" :class="situacoes[f.situacao].cor" />
                <span class="text-[10px] text-gray-400 tabular-nums">{{ f.dia.slice(8, 10) }}/{{ f.dia.slice(5, 7) }}</span>
              </div>
            </div>
            <div class="flex flex-wrap gap-4 mt-3 text-[11px] text-gray-400">
              <span v-for="(v, k) in situacoes" :key="k" class="flex items-center gap-1"><component :is="v.icone" class="w-3.5 h-3.5" :class="v.cor" />{{ v.texto }}</span>
            </div>
          </div>
          <CartaoGrafico titulo="Consultas por mês" subtitulo="Ofícios registrados no painel"
            :tabela="{ colunas: ['Mês', 'Consultas'], linhas: g.consultas.map((c) => [mes(c.mes), c.n]) }">
            <div class="h-40"><canvas ref="cConsultas"></canvas></div>
          </CartaoGrafico>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { CircleCheck, CircleX, Clock, Minus } from '@lucide/vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import CartaoGrafico from '@/components/CartaoGrafico.vue'
import { COR, barras, linha, numero } from '@/composables/graficos'

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

// ---- gráficos
const g = ref(null)
const cNat = ref(null), cPppoe = ref(null), cConectados = ref(null), cVolume = ref(null), cConsultas = ref(null)
let graficos = []

const hora = (iso) => new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', timeZone: 'America/Sao_Paulo' }) + 'h'
const dh = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' }) : '—')
const dia = (d) => `${d.slice(8, 10)}/${d.slice(5, 7)}`
const mes = (m) => new Date(m + '-15T12:00').toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '') + '/' + m.slice(2, 4)
const curto = (v) => (v >= 1e6 ? (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : v >= 1e3 ? (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : numero(v))
const bytes = (b) => {
  if (b === null || b === undefined) return '—'
  const u = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0, v = Number(b)
  while (v >= 1024 && i < u.length - 1) { v /= 1024; i++ }
  return `${v.toLocaleString('pt-BR', { maximumFractionDigits: v < 10 && i ? 1 : 0 })} ${u[i]}`
}
const tabelaHoras = (series) => ({
  colunas: ['Hora', ...series.map((s) => s[0])],
  linhas: (g.value?.horas || []).map((h, i) => [hora(h), ...series.map((s) => numero(s[1][i] ?? 0))]).reverse(),
})
const situacoes = {
  assinado: { texto: 'assinado', icone: CircleCheck, cor: 'text-green-400', caixa: 'border-green-500/30 bg-green-500/10' },
  nao_fechado: { texto: 'não fechado', icone: CircleX, cor: 'text-red-400', caixa: 'border-red-500/40 bg-red-500/10' },
  andamento: { texto: 'em andamento', icone: Clock, cor: 'text-blue-400', caixa: 'border-gray-700 bg-gray-800/50' },
  sem_coleta: { texto: 'sem coleta', icone: Minus, cor: 'text-gray-500', caixa: 'border-gray-800 bg-gray-900' },
}

const desenhar = async () => {
  graficos.forEach((c) => c?.destroy())
  await nextTick()
  const d = g.value
  const rotHoras = d.horas.map(hora)
  graficos = [
    d.nat ? barras(cNat.value, rotHoras, [{ nome: 'Eventos de NAT', valores: d.nat, cor: COR.azul }], { formato: curto }) : null,
    barras(cPppoe.value, rotHoras, [{ nome: 'Logins', valores: d.logins, cor: COR.azul }, { nome: 'Logouts', valores: d.logouts, cor: COR.laranja }]),
    linha(cConectados.value, d.conectados.map((c) => dh(c.hora)), [{ nome: 'Conectados', valores: d.conectados.map((c) => c.n), cor: COR.azul }], { area: true, maxRotulos: 8 }),
    barras(cVolume.value, d.volume.map((v) => dia(v.dia)), [{ nome: 'NAT', valores: d.volume.map((v) => v.nat), cor: COR.azul }, { nome: 'Syslog', valores: d.volume.map((v) => v.syslog), cor: COR.laranja }], { formato: bytes, empilhar: true }),
    barras(cConsultas.value, d.consultas.map((c) => mes(c.mes)), [{ nome: 'Consultas', valores: d.consultas.map((c) => c.n), cor: COR.azul }]),
  ]
}

const carregar = async () => {
  s.value = null
  const [saude, dados] = await Promise.all([api.get('/painel/saude'), api.get('/painel/graficos').catch(() => null)])
  s.value = saude.data
  g.value = dados?.data ?? null
  if (g.value) desenhar()
}

onMounted(carregar)
onBeforeUnmount(() => graficos.forEach((c) => c?.destroy()))
</script>
