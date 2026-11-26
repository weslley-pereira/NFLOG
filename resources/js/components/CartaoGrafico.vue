<template>
  <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 flex flex-col">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <h2 class="text-sm font-medium text-white">{{ titulo }}</h2>
        <p v-if="subtitulo" class="text-xs text-gray-500">{{ subtitulo }}</p>
      </div>
      <div v-if="legenda?.length" class="flex flex-wrap gap-3 text-[11px] text-gray-400">
        <span v-for="l in legenda" :key="l.nome" class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: l.cor }"></span>{{ l.nome }}
        </span>
      </div>
    </div>
    <slot />
    <details v-if="tabela?.linhas?.length" class="mt-3 text-xs">
      <summary class="text-gray-500 cursor-pointer hover:text-gray-300">Ver dados em tabela</summary>
      <div class="mt-2 max-h-56 overflow-y-auto border border-gray-800 rounded-lg">
        <table class="w-full">
          <thead class="text-gray-500 text-left sticky top-0 bg-gray-900"><tr><th v-for="c in tabela.colunas" :key="c" class="px-3 py-1.5 font-medium">{{ c }}</th></tr></thead>
          <tbody><tr v-for="(l, i) in tabela.linhas" :key="i" class="border-t border-gray-800">
            <td v-for="(v, j) in l" :key="j" class="px-3 py-1 text-gray-300" :class="j ? 'text-right tabular-nums' : ''">{{ v }}</td>
          </tr></tbody>
        </table>
      </div>
    </details>
  </div>
</template>

<script setup>
defineProps({ titulo: String, subtitulo: String, legenda: Array, tabela: Object })
</script>
