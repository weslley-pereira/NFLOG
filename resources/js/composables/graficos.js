import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

/**
 * Gráficos do painel num padrão só -- o mesmo composable e a mesma paleta do NFC ERP.
 *
 * Paleta validada (scripts do skill de dataviz) contra a superfície dos
 * cards do tema escuro (#11171e): passa em contraste, faixa de luminosidade
 * e separação pra daltonismo.
 * - Uma série só: AZUL. Duas séries: AZUL + LARANJA.
 * - CATEGORICAS (até 5, sempre nessa ordem) + OUTROS em cinza pro resto --
 *   uma 6ª cor (roxo) encostava no azul na volta da rosca e falhava.
 * - Cores de status (ok/atencao/critico) só quando a cor SIGNIFICA estado.
 * - Nunca dois eixos Y no mesmo gráfico: grandezas diferentes = gráficos separados.
 */
export const COR = {
  azul: '#2b8ef0',
  laranja: '#d95926',
  verdeAgua: '#199e70',
  cinza: '#4b5866',      // série de referência (ex.: faturado, mês anterior)
  clara: '#c3cbd3',      // linha de destaque neutra (ex.: resultado)
  trilho: '#28323d',
  grade: '#1e2731',
  eixo: '#28323d',
  textoEixo: '#6b7785',
  superficie: '#11171e',
  ok: '#22c55e',
  atencao: '#eab308',
  critico: '#ef4444',
}

export const CATEGORICAS = ['#2b8ef0', '#d95926', '#199e70', '#c98500', '#d55181']
export const OUTROS = COR.cinza

export const numero = (v) => Number(v ?? 0).toLocaleString('pt-BR')
export const moeda = (v) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
export const moedaCurta = (v) => {
  const n = Number(v ?? 0)
  if (Math.abs(n) >= 1000) return `R$ ${(n / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`
  return `R$ ${n.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`
}
export const porcento = (v) => `${Number(v ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`

/**
 * Agrupa a cauda de uma lista categórica em "Outros" e atribui as cores
 * em ordem fixa. itens: [{ label, valor }] -> [{ label, valor, cor }]
 */
export const comCores = (itens, maximo = CATEGORICAS.length) => {
  const ordenados = [...itens].sort((a, b) => b.valor - a.valor)
  const principais = ordenados.slice(0, maximo).map((i, k) => ({ ...i, cor: CATEGORICAS[k] }))
  const resto = ordenados.slice(maximo).reduce((s, i) => s + Number(i.valor || 0), 0)
  if (resto > 0) principais.push({ label: 'Outros', valor: resto, cor: OUTROS })
  return principais
}

const tooltip = (formato, extras = {}) => ({
  backgroundColor: '#030405',
  borderColor: COR.eixo,
  borderWidth: 1,
  titleColor: '#f3f4f6',
  bodyColor: '#d1d5db',
  footerColor: '#9aa5b1',
  footerFont: { weight: 'normal' },
  padding: 10,
  boxPadding: 4,
  usePointStyle: true,
  callbacks: {
    label: (i) => ` ${i.chart.data.datasets.length > 1 ? i.dataset.label + ': ' : ''}${formato(i.raw)}`,
    ...extras,
  },
})

const fonte = () => { Chart.defaults.font.family = getComputedStyle(document.body).fontFamily }

const eixoCategoria = (extra = {}) => ({
  grid: { display: false },
  border: { color: COR.eixo },
  ticks: { color: COR.textoEixo, font: { size: 11 }, maxRotation: 0, autoSkip: true, ...extra },
})
const eixoValor = (formato, extra = {}) => ({
  beginAtZero: true,
  grid: { color: COR.grade },
  border: { display: false },
  ticks: { color: COR.textoEixo, font: { size: 11 }, maxTicksLimit: 5, precision: 0, callback: (v) => formato(v) },
  ...extra,
})

// Valor escrito na ponta de cada barra (só barras horizontais de uma série)
const valorNaPonta = (formato) => ({
  id: 'valorNaPonta',
  afterDatasetsDraw: (chart) => {
    const { ctx } = chart
    ctx.save()
    ctx.fillStyle = '#d1d5db'
    ctx.font = `500 10px ${Chart.defaults.font.family}`
    ctx.textBaseline = 'middle'
    chart.getDatasetMeta(0).data.forEach((barra, i) => {
      ctx.fillText(formato(chart.data.datasets[0].data[i]), barra.x + 6, barra.y)
    })
    ctx.restore()
  },
})

// Linha vertical acompanhando o cursor nos gráficos de linha
const mira = {
  id: 'mira',
  afterDraw: (chart) => {
    const ativo = chart.tooltip?.getActiveElements?.()
    if (!ativo?.length) return
    const { ctx, chartArea } = chart
    ctx.save()
    ctx.strokeStyle = COR.eixo
    ctx.lineWidth = 1
    ctx.beginPath()
    ctx.moveTo(ativo[0].element.x, chartArea.top)
    ctx.lineTo(ativo[0].element.x, chartArea.bottom)
    ctx.stroke()
    ctx.restore()
  },
}

/**
 * Barras. series: [{ nome, valores, cor }]
 * opcoes: { formato, horizontal, empilhar, rodape(itens) -> texto do tooltip,
 *           linhas: [{ nome, valores, cor }] (linha sobre as barras, MESMO eixo) }
 */
export const barras = (canvas, labels, series, opcoes = {}) => {
  if (!canvas) return null
  fonte()
  const { formato = numero, horizontal = false, empilhar = false, rodape, linhas = [], larguraRotulos } = opcoes
  const umaSerie = series.length === 1 && !linhas.length
  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        ...series.map(s => ({
          label: s.nome,
          data: s.valores,
          backgroundColor: s.cor,
          hoverBackgroundColor: s.cor,
          borderRadius: horizontal ? { topRight: 4, bottomRight: 4 } : { topLeft: 4, topRight: 4 },
          borderSkipped: 'start',
          maxBarThickness: horizontal ? 16 : 26,
          barPercentage: 0.85,
          categoryPercentage: series.length > 1 ? 0.7 : 0.8,
          order: 2,
        })),
        ...linhas.map(l => ({
          type: 'line',
          label: l.nome,
          data: l.valores,
          borderColor: l.cor,
          backgroundColor: l.cor,
          borderWidth: 2,
          tension: 0,
          pointRadius: 3,
          pointHoverRadius: 5,
          pointBorderColor: COR.superficie,
          pointBorderWidth: 2,
          order: 1,
        })),
      ],
    },
    options: {
      indexAxis: horizontal ? 'y' : 'x',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 300 },
      interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
      layout: horizontal && umaSerie ? { padding: { right: 64 } } : {},
      plugins: {
        legend: { display: false },
        tooltip: tooltip(formato, rodape ? { footer: rodape } : {}),
      },
      scales: horizontal
        ? {
            x: { ...eixoValor(formato), display: !umaSerie, stacked: empilhar },
            y: {
              ...eixoCategoria({
                color: '#9aa5b1',
                font: { size: 10 },
                autoSkip: false,
                // Rótulo longo é abreviado (o nome inteiro aparece no tooltip)
                callback(v) { const l = String(this.getLabelForValue(v)); return l.length > 24 ? l.slice(0, 23) + '…' : l },
              }),
              border: { display: false },
              stacked: empilhar,
              ...(larguraRotulos ? { afterFit: (e) => { e.width = larguraRotulos } } : {}),
            },
          }
        : {
            x: { ...eixoCategoria(), stacked: empilhar },
            y: { ...eixoValor(formato), stacked: empilhar },
          },
    },
    plugins: horizontal && umaSerie ? [valorNaPonta(formato)] : [],
  })
}

/**
 * Linha. series: [{ nome, valores, cor }] -- todas no MESMO eixo.
 * opcoes: { formato, area (preenche abaixo da 1ª série), maxRotulos }
 */
export const linha = (canvas, labels, series, opcoes = {}) => {
  if (!canvas) return null
  fonte()
  const { formato = numero, area = false, maxRotulos = 12, comecarDoZero = true } = opcoes
  return new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: series.map((s, k) => ({
        label: s.nome,
        data: s.valores,
        borderColor: s.cor,
        backgroundColor: area && k === 0 ? `${s.cor}1f` : s.cor,
        fill: area && k === 0,
        borderWidth: 2,
        tension: 0,
        pointRadius: labels.length > 12 ? 0 : 3,
        pointHoverRadius: 5,
        pointBackgroundColor: s.cor,
        pointBorderColor: COR.superficie,
        pointBorderWidth: 2,
      })),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 300 },
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip: tooltip(formato) },
      scales: {
        x: eixoCategoria({ maxTicksLimit: maxRotulos }),
        y: eixoValor(formato, { beginAtZero: comecarDoZero }),
      },
    },
    plugins: [mira],
  })
}

/**
 * Rosca. itens: [{ label, valor, cor }] (use comCores() pra categorias).
 * opcoes: { formato, centro: texto no meio }
 */
export const rosca = (canvas, itens, opcoes = {}) => {
  if (!canvas) return null
  fonte()
  const { formato = numero, centro } = opcoes
  const total = itens.reduce((s, i) => s + Number(i.valor || 0), 0)
  return new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: itens.map(i => i.label),
      datasets: [{
        data: itens.map(i => i.valor),
        backgroundColor: itens.map(i => i.cor),
        hoverBackgroundColor: itens.map(i => i.cor),
        borderColor: COR.superficie,
        borderWidth: 2,
        hoverOffset: 3,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      animation: { duration: 300 },
      plugins: {
        legend: { display: false },
        tooltip: tooltip(formato, { label: (i) => ` ${i.label}: ${formato(i.raw)} (${total ? Math.round(i.raw / total * 100) : 0}%)` }),
      },
    },
    plugins: centro ? [{
      id: 'centro',
      afterDraw: (chart) => {
        const { ctx, chartArea: a } = chart
        ctx.save()
        ctx.fillStyle = '#f3f4f6'
        ctx.font = `600 14px ${Chart.defaults.font.family}`
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.fillText(centro, (a.left + a.right) / 2, (a.top + a.bottom) / 2)
        ctx.restore()
      },
    }] : [],
  })
}
