// Shared category → icon-asset resolver. Keyword-based so granular category
// names (e.g. "Pipe Repair", "Garden Trim", "Move & Pack") map to the right
// icon, not just an emoji. Used by CategoryGrid and ProviderGridCard.
import plumbing    from '../assets/category-icons/plumbing.png'
import electrical  from '../assets/category-icons/electrical.png'
import cleaning    from '../assets/category-icons/cleaning.png'
import gardening   from '../assets/category-icons/gardening.png'
import acService   from '../assets/category-icons/ac-service.png'
import moving      from '../assets/category-icons/moving.png'
import painting    from '../assets/category-icons/painting.svg'
import pestControl from '../assets/category-icons/pest-control.svg'
import roofing     from '../assets/category-icons/roofing.svg'
import carpentry   from '../assets/category-icons/carpentry.svg'
import poolService from '../assets/category-icons/pool-service.svg'
import handyman    from '../assets/category-icons/handyman.svg'

// Order matters: first matching keyword wins.
const RULES = [
  [['plumb', 'pipe'],                          plumbing],
  [['electric', 'wiring'],                     electrical],
  [['clean'],                                  cleaning],
  [['garden', 'trim', 'lawn'],                 gardening],
  [['ac ', 'a/c', 'air', 'cond'],              acService],
  [['move', 'moving', 'pack', 'relocat'],      moving],
  [['paint'],                                  painting],
  [['pest', 'fumig'],                          pestControl],
  [['roof'],                                   roofing],
  [['furnitur', 'carpent', 'assembl', 'wood'], carpentry],
  [['pool'],                                   poolService],
  [['handy', 'repair', 'fix'],                 handyman],
]

const TINTS = {
  plumbing: 'rgba(59,130,246,0.14)', electrical: 'rgba(245,200,40,0.18)',
  cleaning: 'rgba(34,197,94,0.14)',  gardening: 'rgba(16,185,129,0.14)',
}

export function categoryIcon(name) {
  const n = ` ${(name || '').toLowerCase()} `
  for (const [keys, icon] of RULES) {
    if (keys.some(k => n.includes(k))) return icon
  }
  return handyman // sensible default
}

export function categoryTint(name) {
  const icon = categoryIcon(name)
  if (icon === plumbing)   return TINTS.plumbing
  if (icon === electrical) return TINTS.electrical
  if (icon === cleaning)   return TINTS.cleaning
  if (icon === gardening)  return TINTS.gardening
  return 'rgba(255,102,53,0.12)'
}
