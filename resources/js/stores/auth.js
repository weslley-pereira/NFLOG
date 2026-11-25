import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({ usuario: null, carregado: false }),
  actions: {
    async carregar() {
      try {
        const { data } = await api.get('/me')
        this.usuario = data
      } catch {
        this.usuario = null
      }
      this.carregado = true
    },
    async sair() {
      await api.post('/logout').catch(() => {})
      this.usuario = null
    },
  },
})
