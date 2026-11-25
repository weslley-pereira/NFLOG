<template>
  <div class="p-6">
    <div class="-mx-6 -mt-6 mb-6 px-6 py-4 border-b border-gray-800 bg-gray-900">
      <h1 class="text-sm font-medium text-white">Configurações</h1>
      <p class="text-xs text-gray-500">Dados que saem no relatório e os roteadores que enviam os registros</p>
    </div>

    <div v-if="!c" class="flex justify-center py-16"><div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div></div>
    <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <h2 class="text-sm font-medium text-white mb-3">Empresa (cabeçalho e assinatura do relatório)</h2>
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2"><label class="rotulo">Razão social</label><input v-model="c.empresa.razao_social" class="campo" /></div>
          <div><label class="rotulo">CNPJ</label><input v-model="c.empresa.cnpj" class="campo" /></div>
          <div><label class="rotulo">Outorga (SCM / Anatel)</label><input v-model="c.empresa.outorga" class="campo" /></div>
          <div class="col-span-2"><label class="rotulo">Endereço</label><input v-model="c.empresa.endereco" class="campo" /></div>
          <div><label class="rotulo">Responsável técnico</label><input v-model="c.empresa.responsavel" class="campo" /></div>
          <div><label class="rotulo">Cargo</label><input v-model="c.empresa.cargo_responsavel" class="campo" /></div>
          <div><label class="rotulo">E-mail</label><input v-model="c.empresa.email" type="email" class="campo" /></div>
          <div><label class="rotulo">Telefone</label><input v-model="c.empresa.telefone" class="campo" /></div>
        </div>
        <button @click="salvarEmpresa" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Salvar</button>

        <h2 class="text-sm font-medium text-white mt-6 mb-1 pt-4 border-t border-gray-800">Sistema de gestão (qualificação do titular)</h2>
        <p class="text-xs text-gray-500 mb-3">Ao registrar uma consulta com usuário identificado, o NF Log busca lá o cadastro do login (nome, CPF/CNPJ, endereço) para o relatório.</p>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="rotulo">Sistema</label>
            <select v-model="c.gestao.tipo" class="campo"><option value="nenhum">Nenhum (preencher à mão)</option><option value="nfc">NFC ERP</option></select>
          </div>
          <template v-if="c.gestao.tipo === 'nfc'">
            <div><label class="rotulo">Endereço do NFC</label><input v-model="c.gestao.url" class="campo font-mono" placeholder="https://erp.provedor.com.br" /></div>
            <div class="col-span-2"><label class="rotulo">IP interno do NFC (opcional)</label>
              <input v-model="c.gestao.ip" class="campo font-mono" placeholder="só se este servidor não alcança o endereço acima (ex.: 10.0.0.5)" /></div>
            <div class="col-span-2"><label class="rotulo">Token (NFC: Integrações &gt; NF Log)</label>
              <input v-model="c.gestao.token" type="password" class="campo font-mono" autocomplete="new-password" :placeholder="c.gestao.tem_token ? 'manter o atual' : ''" /></div>
          </template>
        </div>
        <div class="flex items-center gap-2 mt-4">
          <button @click="salvarGestao" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Salvar</button>
          <button v-if="c.gestao.tipo !== 'nenhum'" @click="testarGestao" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs rounded-lg">Testar conexão</button>
        </div>
      </div>

      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 xl:col-span-2">
        <div class="flex items-center justify-between mb-1">
          <h2 class="text-sm font-medium text-white">Licença</h2>
          <span v-if="lic" class="text-[11px] px-2 py-0.5 rounded-full" :class="lic.situacao === 'valida' ? 'bg-green-500/15 text-green-400' : 'bg-yellow-500/15 text-yellow-300'">
            {{ lic.situacao === 'valida' ? 'Válida' : lic.situacao === 'sem_licenca' ? 'Sem licença' : 'Inválida' }}</span>
        </div>
        <template v-if="lic">
          <p class="text-xs text-gray-400 mb-3">{{ lic.mensagem }}</p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mb-3">
            <div><div class="text-gray-500">Chave</div><div class="font-mono text-gray-200">{{ lic.chave || '—' }}</div></div>
            <div><div class="text-gray-500">Cliente</div><div class="text-gray-200">{{ lic.cliente || '—' }}</div></div>
            <div><div class="text-gray-500">Válida até</div><div class="text-gray-200">{{ lic.valido_ate ? (lic.valido_ate >= '2099' ? 'não vence' : new Date(lic.valido_ate + 'T12:00').toLocaleDateString('pt-BR')) : '—' }}</div></div>
            <div><div class="text-gray-500">Última verificação</div><div class="text-gray-200">{{ lic.verificado_em ? new Date(lic.verificado_em).toLocaleString('pt-BR') : '—' }}</div></div>
          </div>
          <p v-if="lic.erro" class="text-[11px] text-yellow-300 mb-3">Última tentativa com problema: {{ lic.erro }} (vale a última verificação boa por até {{ lic.tolerancia_dias }} dias).</p>
          <div class="flex flex-wrap items-center gap-2">
            <input v-model="novaChave" class="campo font-mono max-w-sm" placeholder="NFLOG-XXXXX-XXXXX-XXXXX-XXXXX" />
            <button @click="salvarLicenca" :disabled="!novaChave" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white text-sm rounded-lg">{{ lic.chave ? 'Trocar chave' : 'Ativar' }}</button>
            <button v-if="lic.chave" @click="verificarLicenca" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 text-xs rounded-lg">Verificar agora</button>
          </div>
        </template>
      </div>

      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <h2 class="text-sm font-medium text-white mb-1">Roteadores</h2>
        <p class="text-xs text-gray-500 mb-3">O usuário da API é opcional: serve para registrar quem já estava conectado antes da instalação (leitura das sessões ativas, de hora em hora).</p>
        <div class="mb-3"><label class="rotulo">IP deste coletor (para onde os roteadores mandam)</label><input v-model="c.ip_coletor" class="campo font-mono" placeholder="10.0.0.10" /></div>
        <div v-for="(r, i) in c.roteadores" :key="i" class="border border-gray-800 rounded-lg p-3 mb-3">
          <div class="grid grid-cols-2 gap-3">
            <div><label class="rotulo">Nome</label><input v-model="r.nome" class="campo" placeholder="Concentrador" /></div>
            <div><label class="rotulo">IP do roteador (origem dos logs)</label><input v-model="r.ip" class="campo font-mono" /></div>
            <div><label class="rotulo">Interface de saída (WAN)</label><input v-model="r.interface_wan" class="campo font-mono" placeholder="ether1" /></div>
            <div><label class="rotulo">Porta da API</label><input v-model.number="r.porta" type="number" class="campo font-mono" placeholder="8728" /></div>
            <div><label class="rotulo">Usuário da API (só leitura)</label><input v-model="r.usuario" class="campo" autocomplete="off" /></div>
            <div><label class="rotulo">Senha da API</label><input v-model="r.senha" type="password" class="campo" autocomplete="new-password" :placeholder="r.tem_senha ? 'manter a atual' : ''" /></div>
          </div>
          <div class="flex items-center gap-2 mt-3">
            <button @click="baixarScript(r)" :disabled="!r.ip || !r.interface_wan || !c.ip_coletor" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 disabled:opacity-40 text-gray-200 text-xs rounded-lg">Baixar script do Mikrotik</button>
            <button @click="c.roteadores.splice(i, 1)" class="px-3 py-1.5 text-red-400 hover:text-red-300 text-xs">Remover</button>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="c.roteadores.push({ nome: '', ip: '', interface_wan: '', porta: 8728, usuario: '', senha: '' })" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded-lg">+ Roteador</button>
          <button @click="salvarRoteadores" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg">Salvar roteadores</button>
        </div>
        <p class="text-[11px] text-gray-500 mt-3">O script liga no Mikrotik o envio do syslog e o log de NAT (eventos de sessão do CGNAT, sem o destino). Importe com <span class="font-mono">/import nflog-mikrotik.rsc</span>; pode importar de novo.</p>
      </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 right-6 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white shadow-xl z-50">{{ toast }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api, { mensagemErro } from '@/services/api'

const c = ref(null)
const lic = ref(null)
const novaChave = ref('')
const toast = ref(null)
const aviso = (msg) => { toast.value = msg; setTimeout(() => (toast.value = null), 3500) }

const carregar = async () => {
  const { data } = await api.get('/configuracoes')
  data.roteadores.forEach((r) => (r.senha = ''))
  data.gestao.token = ''
  c.value = data
  lic.value = (await api.get('/licenca')).data
}

const salvarEmpresa = async () => {
  try { aviso((await api.put('/configuracoes/empresa', c.value.empresa)).data.mensagem) } catch (e) { aviso(mensagemErro(e)) }
}

const salvarRoteadores = async () => {
  try {
    aviso((await api.put('/configuracoes/roteadores', { ip_coletor: c.value.ip_coletor || null, roteadores: c.value.roteadores })).data.mensagem)
    await carregar()
  } catch (e) { aviso(mensagemErro(e)) }
}

const salvarLicenca = async () => {
  try {
    lic.value = (await api.put('/licenca', { chave: novaChave.value })).data
    novaChave.value = ''
    aviso(lic.value.mensagem)
  } catch (e) { aviso(mensagemErro(e)) }
}

const verificarLicenca = async () => {
  try { lic.value = (await api.post('/licenca/verificar')).data; aviso(lic.value.mensagem) } catch (e) { aviso(mensagemErro(e)) }
}

const salvarGestao = async () => {
  try {
    aviso((await api.put('/configuracoes/gestao', { tipo: c.value.gestao.tipo, url: c.value.gestao.url || null, ip: c.value.gestao.ip || null, token: c.value.gestao.token || null })).data.mensagem)
    await carregar()
  } catch (e) { aviso(mensagemErro(e)) }
}

const testarGestao = async () => {
  try { aviso((await api.post('/configuracoes/gestao/testar')).data.mensagem) } catch (e) { aviso(mensagemErro(e)) }
}

const baixarScript = async (r) => {
  try {
    const { data } = await api.get('/configuracoes/script-mikrotik', { params: { roteador: r.ip, interface_wan: r.interface_wan }, responseType: 'text' })
    const url = URL.createObjectURL(new Blob([data], { type: 'text/plain' }))
    const a = document.createElement('a')
    a.href = url
    a.download = 'nflog-mikrotik.rsc'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) { aviso('Salve os roteadores e o IP do coletor antes de gerar o script.') }
}

onMounted(carregar)
</script>

<style scoped>
.rotulo { display: block; font-size: 11px; color: var(--color-gray-400); margin-bottom: 4px; }
.campo { width: 100%; padding: 8px 10px; background: var(--color-gray-800); border: 1px solid var(--color-gray-700); border-radius: 8px; font-size: 13px; color: var(--color-gray-100); }
.campo:focus { outline: none; border-color: var(--color-blue-500); }
</style>
