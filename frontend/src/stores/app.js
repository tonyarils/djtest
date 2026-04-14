import { defineStore } from 'pinia'

export const useAppStore = defineStore('app', {
  state: () => ({
    appName: '智慧红芯党建协同管理系统',
  }),
})
