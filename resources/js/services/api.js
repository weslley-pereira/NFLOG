import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true, // sessão por cookie (Sanctum SPA)
  withXSRFToken: true,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Sessão expirou: volta pro login (sem laço na própria tela de login).
api.interceptors.response.use(
  (resposta) => resposta,
  (erro) => {
    const url = erro.config?.url ?? ''
    if (erro.response?.status === 401 && !url.includes('/login') && !url.includes('/me') && window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
    return Promise.reject(erro)
  },
)

// Mensagem legível de qualquer erro da API.
export const mensagemErro = (e, padrao = 'Erro inesperado') =>
  e.response?.data?.mensagem ?? e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {})?.[0]?.[0] ?? padrao

export default api
