import { defineStore } from 'pinia'
import * as api from '../services/api'

// One wallet store, read by WalletView / AccountView / anywhere that shows
// balance — so the number is always the server ledger, never a stale localStorage copy.
export const useWalletStore = defineStore('wallet', {
  state: () => ({
    balanceCents: 0,
    transactions: [],
    loaded: false,
  }),
  getters: {
    balance: (s) => (s.balanceCents / 100).toFixed(2),
  },
  actions: {
    apply(data) {
      this.balanceCents = data.balance_cents ?? this.balanceCents
      if (Array.isArray(data.transactions)) this.transactions = data.transactions
    },
    async load() {
      const data = await api.getWallet()
      this.apply(data)
      this.loaded = true
      return data
    },
    async topUp(amountCents) {
      const res = await api.walletTopUp(amountCents)
      await this.load()
      return res
    },
    async withdraw(amountCents) {
      const res = await api.walletWithdraw(amountCents)
      await this.load()
      return res
    },
  },
})
