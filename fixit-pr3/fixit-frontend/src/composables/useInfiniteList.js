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
  let scroller = null   // the element (or window) that actually scrolls

  // The scroller may be the document (desktop) or an inner container
  // (mobile shell uses overflow:auto on .fx-main). Observe against whichever
  // actually scrolls, or the observer never fires inside a container.
  function scrollRoot(el) {
    let p = el?.parentElement
    while (p && p !== document.body) {
      const oy = getComputedStyle(p).overflowY
      if (oy === 'auto' || oy === 'scroll') return p
      p = p.parentElement
    }
    return null // viewport
  }
  function onScroll() {
    const el = scroller
    const nearBottom = el && el !== window
      ? el.scrollTop + el.clientHeight >= el.scrollHeight - 400
      : window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 400
    if (nearBottom) loadMore()
  }
  function attach() {
    observer?.disconnect()
    scroller?.removeEventListener?.('scroll', onScroll)
    if (!sentinel.value) return
    const root = scrollRoot(sentinel.value)
    observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting) loadMore() },
      { root, rootMargin: '300px' },
    )
    observer.observe(sentinel.value)
    // Scroll-position fallback — robust if IntersectionObserver misses inside
    // a container scroller (some WebViews/edge cases).
    scroller = root || window
    scroller.addEventListener('scroll', onScroll, { passive: true })
  }

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
    attach()
    reset()
  })

  // Re-attach whenever the sentinel element mounts/changes (e.g. v-if results).
  watch(sentinel, () => attach())

  onUnmounted(() => { observer?.disconnect(); scroller?.removeEventListener?.('scroll', onScroll) })

  return { items, loading, done, offset, sentinel, loadMore, reset }
}
