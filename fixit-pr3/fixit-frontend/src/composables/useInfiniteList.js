import { ref, watch, onMounted, onUnmounted } from 'vue'

/**
 * Infinite-scroll list. `fetchPage(offset, pageSize)` must return an array of
 * items for that window. Bind the returned `sentinel` ref to an element at the
 * bottom of the list — when it scrolls into view, the next page loads.
 *
 *   const { items, loading, done, sentinel, reset } = useInfiniteList(fetch, 20)
 *   watch([q, sort], reset)   // re-query when filters change
 */
export function useInfiniteList(fetchPage, pageSize = 20) {
  const items   = ref([])
  const loading = ref(false)
  const done    = ref(false)
  const offset  = ref(0)
  const sentinel = ref(null)
  let observer = null

  async function loadMore() {
    if (loading.value || done.value) return
    loading.value = true
    try {
      const batch = await fetchPage(offset.value, pageSize)
      const arr = Array.isArray(batch) ? batch : []
      items.value.push(...arr)
      offset.value += arr.length
      if (arr.length < pageSize) done.value = true
    } catch {
      done.value = true   // stop hammering on error
    } finally {
      loading.value = false
    }
  }

  function reset() {
    items.value = []
    offset.value = 0
    done.value = false
    loadMore()
  }

  onMounted(() => {
    observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting) loadMore() },
      { rootMargin: '300px' },
    )
    if (sentinel.value) observer.observe(sentinel.value)
    reset()
  })

  // Re-observe whenever the sentinel element mounts/changes (e.g. v-if results).
  watch(sentinel, (el, old) => {
    if (!observer) return
    if (old) observer.unobserve(old)
    if (el) observer.observe(el)
  })

  onUnmounted(() => observer?.disconnect())

  return { items, loading, done, offset, sentinel, loadMore, reset }
}
