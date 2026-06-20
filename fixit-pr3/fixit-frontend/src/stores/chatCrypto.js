import { defineStore } from 'pinia'
import * as api from '../services/api'
import * as crypto from '../services/crypto'

export const useChatCryptoStore = defineStore('chatCrypto', {
  state: () => ({
    pinConfigured: false,
    unlocked: false,
    pinKey: null,
    privateKey: null,
    publicKey: null,
    pinSalt: null,
    jobKeys: {},
    error: null,
    loading: false,
  }),
  actions: {
    async loadStatus() {
      const status = await api.getCryptoStatus()
      this.pinConfigured = status.pin_configured
      this.unlocked = crypto.isUnlockedThisSession() && !!this.privateKey
    },

    async setupPin(pin) {
      if (!/^\d{4,8}$/.test(pin)) throw new Error('PIN must be 4–8 digits')
      this.loading = true
      this.error = null
      try {
        const salt = crypto.generateSalt()
        const verifier = await crypto.computePinVerifier(pin, salt)
        const pinKey = await crypto.derivePinKey(pin, salt)
        const keyPair = await crypto.generateUserKeyPair()
        const wrapped = await crypto.wrapPrivateKey(pinKey, keyPair.privateKey)
        const publicJwk = await crypto.exportPublicKeyJwk(keyPair.publicKey)
        await api.setupPin({
          pin_salt: salt,
          pin_verifier: verifier,
          public_key_jwk: publicJwk,
          wrapped_private_key: wrapped.wrapped_private_key,
          private_key_iv: wrapped.private_key_iv,
        })
        this.pinSalt = salt
        this.pinKey = pinKey
        this.privateKey = keyPair.privateKey
        this.publicKey = keyPair.publicKey
        this.pinConfigured = true
        this.unlocked = true
        crypto.markUnlocked()
      } catch (e) {
        this.error = e.message
        throw e
      } finally {
        this.loading = false
      }
    },

    async unlockWithPin(pin) {
      if (!/^\d{4,8}$/.test(pin)) throw new Error('PIN must be 4–8 digits')
      this.loading = true
      this.error = null
      try {
        const { pin_salt: salt } = await api.fetchPinSalt()
        const verifier = await crypto.computePinVerifier(pin, salt)
        const bundle = await api.verifyPin({ pin_verifier: verifier })
        this.pinSalt = salt
        this.pinKey = await crypto.derivePinKey(pin, salt)
        this.privateKey = await crypto.unwrapPrivateKey(
          this.pinKey,
          bundle.wrapped_private_key,
          bundle.private_key_iv,
        )
        const myPub = await api.getMyPublicKey()
        this.publicKey = await crypto.importPublicKeyJwk(myPub.public_key_jwk)
        this.unlocked = true
        crypto.markUnlocked()
      } catch (e) {
        this.error = e.message
        throw e
      } finally {
        this.loading = false
      }
    },

    lock() {
      this.pinKey = null
      this.privateKey = null
      this.publicKey = null
      this.jobKeys = {}
      this.unlocked = false
      crypto.clearUnlockSession()
    },

    async ensureJobKey(jobId) {
      if (!this.privateKey || !this.publicKey) throw new Error('Unlock with PIN first')
      if (this.jobKeys[jobId]) return this.jobKeys[jobId]

      const remote = await api.getJobCryptoKey(jobId)
      if (remote.encrypted_job_key) {
        this.jobKeys[jobId] = await crypto.decryptJobKey(this.privateKey, remote.encrypted_job_key)
        return this.jobKeys[jobId]
      }

      const peers = await api.getJobPeers(jobId)
      let otherPublicKey
      try {
        const otherPub = await api.getUserPublicKey(peers.other_user_id)
        otherPublicKey = await crypto.importPublicKeyJwk(otherPub.public_key_jwk)
      } catch {
        throw new Error('Other participant must set up their chat PIN before you can start encrypted chat')
      }

      const jobKey = await crypto.generateJobKey()
      const encSelf = await crypto.encryptJobKeyForUser(this.publicKey, jobKey)
      const encOther = await crypto.encryptJobKeyForUser(otherPublicKey, jobKey)

      await api.saveJobCryptoKey(jobId, {
        encrypted_job_key: encSelf,
        target_user_id: peers.other_user_id,
        encrypted_job_key_for_target: encOther,
      })

      this.jobKeys[jobId] = jobKey
      return jobKey
    },

    async encryptForJob(jobId, plaintext) {
      const key = await this.ensureJobKey(jobId)
      const enc = await crypto.encryptMessage(key, plaintext)
      const hash = await crypto.contentHash(plaintext)
      return { ...enc, content_hash: hash }
    },

    async decryptMessage(msg) {
      if (!msg.is_encrypted) return msg.body || '[legacy message]'
      if (!this.jobKeys[msg.job_id]) await this.ensureJobKey(msg.job_id)
      try {
        return await crypto.decryptMessage(this.jobKeys[msg.job_id], msg.ciphertext, msg.iv)
      } catch {
        return '🔒 Unable to decrypt — enter PIN on this device'
      }
    },
  },
})