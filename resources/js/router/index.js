import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/pages/Login.vue') },
    {
      path: '/',
      component: () => import('@/components/Layout.vue'),
      children: [
        { path: '', name: 'painel', component: () => import('@/pages/Painel.vue') },
        { path: 'consulta', name: 'consulta', component: () => import('@/pages/Consulta.vue') },
        { path: 'consultas', name: 'consultas', component: () => import('@/pages/Consultas.vue') },
        { path: 'configuracoes', name: 'configuracoes', component: () => import('@/pages/Configuracoes.vue') },
        { path: 'conta', name: 'conta', component: () => import('@/pages/Conta.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.carregado) await auth.carregar()
  if (to.name === 'login') return auth.usuario ? { name: 'painel' } : true
  if (!auth.usuario) return { name: 'login' }
  return true
})

export default router
