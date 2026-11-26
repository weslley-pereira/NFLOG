<template>
  <div class="relative" ref="raizEl">
    <input
      type="text"
      inputmode="numeric"
      :value="texto"
      @input="onInput"
      @focus="onFocus"
      @click="abrirCalendario"
      @keydown.esc="aberto = false"
      @keydown.tab="aberto = false"
      @blur="onBlur"
      placeholder="DD/MM/AAAA"
      maxlength="10"
      :class="inputClass"
      class="w-full pl-3 pr-9 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:border-blue-500"
    />
    <button type="button" tabindex="-1" @click="alternarCalendario"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    </button>

    <!-- Calendário PRÓPRIO (não é o picker nativo do navegador) -- ver
    achado real 2026-08-13 embaixo, no abrirCalendario() antigo que isso
    substituiu: o picker nativo intercepta o teclado no nível do
    navegador enquanto está aberto (nenhum evento chega nem no
    document, testado com listener na fase de captura), então clicar
    no campo pra abrir E digitar por cima nunca iam conviver com o
    nativo. Sendo só divs/botões normais da página, esse aqui não
    disputa teclado com o input de texto -- os dois funcionam juntos,
    igual o calendário de referência que o usuário mandou (print do
    ISPFY, também um widget próprio, não nativo). -->
    <div v-if="aberto" class="absolute z-20 mt-1 w-64 bg-gray-800 border border-gray-700 rounded-lg shadow-xl p-3"
      @mousedown.prevent>
      <div class="flex items-center justify-between mb-2">
        <button type="button" tabindex="-1" @click="mesAnterior" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-700 text-gray-400 hover:text-white">‹</button>
        <span class="text-sm text-gray-200 font-medium">{{ tituloMes }}</span>
        <button type="button" tabindex="-1" @click="mesSeguinte" class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-700 text-gray-400 hover:text-white">›</button>
      </div>
      <div class="grid grid-cols-7 gap-1 text-center text-[11px] text-gray-500 mb-1">
        <span v-for="d in ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']" :key="d">{{ d }}</span>
      </div>
      <div class="grid grid-cols-7 gap-1">
        <button v-for="dia in diasGrade" :key="dia.chave" type="button" tabindex="-1"
          :disabled="!dia.iso || dia.desabilitado"
          @click="dia.iso && selecionarDia(dia.iso)"
          class="w-8 h-8 flex items-center justify-center text-xs rounded transition-colors disabled:cursor-default"
          :class="classeDia(dia)">
          {{ dia.numero }}
        </button>
      </div>
      <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-700">
        <button type="button" tabindex="-1" @click="limpar" class="text-xs text-gray-400 hover:text-white">Limpar</button>
        <button type="button" tabindex="-1" @click="irParaHoje" class="text-xs text-blue-400 hover:text-blue-300">Hoje</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

// v-model continua em ISO (AAAA-MM-DD), igual um <input type="date"> normal
// -- só a exibição/digitação é que vira DD/MM/AAAA com máscara. Troca direta
// por <input type="date"> em qualquer formulário, sem mudar o resto do form.
const props = defineProps({
  modelValue: { type: String, default: '' },
  inputClass: { type: String, default: '' },
  min: { type: String, default: undefined },
  max: { type: String, default: undefined },
})
const emit = defineEmits(['update:modelValue', 'change'])

const texto = ref('')
const raizEl = ref(null)
const aberto = ref(false)
const mesExibido = ref(new Date())

// O backend às vezes devolve a data já como datetime ISO completo
// (ex: "1992-04-23T03:00:00.000000Z", serialização padrão do Carbon) em
// vez de só "AAAA-MM-DD" -- pega só os 10 primeiros caracteres antes de
// separar, senão o "T03:00..." vaza pro meio da data formatada.
const isoLimpo = computed(() => (props.modelValue || '').slice(0, 10))

const isoParaTexto = (iso) => {
  const limpo = (iso || '').slice(0, 10)
  const [y, m, d] = limpo.split('-')
  if (!y || !m || !d) return ''
  return `${d}/${m}/${y}`
}

const textoParaIso = (valor) => {
  const digitos = valor.replace(/\D/g, '')
  if (digitos.length !== 8) return null

  const d = parseInt(digitos.slice(0, 2), 10)
  const m = parseInt(digitos.slice(2, 4), 10)
  const y = parseInt(digitos.slice(4, 8), 10)
  if (m < 1 || m > 12 || d < 1 || d > 31 || y < 1000) return null

  // Confere se é uma data real (rejeita 31/02, 30/02 etc.) -- new Date()
  // "normaliza" datas inválidas em vez de dar erro, então precisa validar
  // voltando os componentes e comparando.
  const data = new Date(y, m - 1, d)
  if (data.getFullYear() !== y || data.getMonth() !== m - 1 || data.getDate() !== d) return null

  return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}

watch(() => props.modelValue, (novo) => {
  texto.value = isoParaTexto(novo)
}, { immediate: true })

const onInput = (evento) => {
  const digitos = evento.target.value.replace(/\D/g, '').slice(0, 8)
  let formatado = digitos
  if (digitos.length > 4) formatado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4)}`
  else if (digitos.length > 2) formatado = `${digitos.slice(0, 2)}/${digitos.slice(2)}`

  evento.target.value = formatado
  texto.value = formatado

  const iso = textoParaIso(formatado)
  if (iso) {
    emit('update:modelValue', iso)
    emit('change', iso)
    // Data completa digitada: o calendário sai da frente do próximo campo.
    aberto.value = false
  }
}

const onBlur = () => {
  // Ficou pela metade (ex: só "15/07") -- não é uma data válida pra
  // salvar, então limpa em vez de mandar algo quebrado pro backend.
  if (texto.value && !textoParaIso(texto.value)) {
    texto.value = ''
    emit('update:modelValue', '')
  }
}

// --- Calendário -----------------------------------------------------

const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']

const tituloMes = computed(() => {
  const mes = MESES[mesExibido.value.getMonth()]
  return `${mes.charAt(0).toUpperCase()}${mes.slice(1)} de ${mesExibido.value.getFullYear()}`
})

// Sempre que o campo tem uma data válida, abrir o calendário nela em vez
// de ficar no mês atual -- senão editar uma data antiga (ex: nascimento
// de 1992) obrigava a navegar mês a mês desde hoje até lá.
const sincronizarMesExibido = () => {
  const iso = textoParaIso(texto.value) || isoLimpo.value
  const [y, m, d] = (iso || '').split('-').map(Number)
  mesExibido.value = (y && m && d) ? new Date(y, m - 1, 1) : new Date()
}

const abrirCalendario = () => {
  sincronizarMesExibido()
  aberto.value = true
}

const alternarCalendario = () => {
  if (aberto.value) aberto.value = false
  else abrirCalendario()
}

// Chegar no campo VAZIO via teclado (Tab) continua abrindo sozinho, igual
// já era antes -- não tem mais o risco de travar o teclado (esse
// calendário é só divs normais), mas não custa manter o comportamento:
// campo já preenchido, foco por teclado só reposiciona/seleciona texto
// pra redigitar, sem popup no meio do caminho.
const onFocus = () => {
  if (!texto.value) abrirCalendario()
}

const fecharAoClicarFora = (evento) => {
  if (raizEl.value && !raizEl.value.contains(evento.target)) {
    aberto.value = false
  }
}
watch(aberto, (valor) => {
  if (valor) document.addEventListener('mousedown', fecharAoClicarFora)
  else document.removeEventListener('mousedown', fecharAoClicarFora)
})
onBeforeUnmount(() => document.removeEventListener('mousedown', fecharAoClicarFora))

const mesAnterior = () => {
  const d = mesExibido.value
  mesExibido.value = new Date(d.getFullYear(), d.getMonth() - 1, 1)
}
const mesSeguinte = () => {
  const d = mesExibido.value
  mesExibido.value = new Date(d.getFullYear(), d.getMonth() + 1, 1)
}

const paraIso = (ano, mes, dia) => `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`

// Grade de 42 células (6 semanas) -- dias do mês anterior/seguinte só
// preenchem o alinhamento (não clicáveis, ver template: dia.iso null).
const diasGrade = computed(() => {
  const ano = mesExibido.value.getFullYear()
  const mes = mesExibido.value.getMonth()
  const primeiroDiaSemana = new Date(ano, mes, 1).getDay()
  const totalDiasMes = new Date(ano, mes + 1, 0).getDate()

  const celulas = []
  for (let i = 0; i < primeiroDiaSemana; i++) {
    celulas.push({ chave: `vazio-antes-${i}`, numero: '', iso: null })
  }
  for (let dia = 1; dia <= totalDiasMes; dia++) {
    const iso = paraIso(ano, mes, dia)
    celulas.push({
      chave: iso,
      numero: dia,
      iso,
      desabilitado: (props.min && iso < props.min) || (props.max && iso > props.max),
    })
  }
  while (celulas.length < 42) {
    celulas.push({ chave: `vazio-depois-${celulas.length}`, numero: '', iso: null })
  }
  return celulas
})

const hojeIso = () => {
  const h = new Date()
  return paraIso(h.getFullYear(), h.getMonth(), h.getDate())
}

const classeDia = (dia) => {
  if (!dia.iso) return 'invisible'
  if (dia.desabilitado) return 'text-gray-700'
  if (dia.iso === isoLimpo.value) return 'bg-blue-600 text-white font-medium'
  if (dia.iso === hojeIso()) return 'bg-gray-700 text-blue-400 font-medium'
  return 'text-gray-300 hover:bg-gray-700'
}

const selecionarDia = (iso) => {
  texto.value = isoParaTexto(iso)
  emit('update:modelValue', iso)
  emit('change', iso)
  aberto.value = false
}

const limpar = () => {
  texto.value = ''
  emit('update:modelValue', '')
  emit('change', '')
  aberto.value = false
}

const irParaHoje = () => {
  mesExibido.value = new Date()
}
</script>
