/**
 * GateHero.tsx
 * Animated gate preview — migrated from framer-motion → motion (motiondivision/motion)
 */
import { animate, motion, useReducedMotion } from 'motion/react'
import { useEffect, useMemo, useState } from 'react'

function useDocumentVisible() {
  const [visible, setVisible] = useState(true)
  useEffect(() => {
    const onVis = () => setVisible(document.visibilityState === 'visible')
    onVis()
    document.addEventListener('visibilitychange', onVis)
    return () => document.removeEventListener('visibilitychange', onVis)
  }, [])
  return visible
}

export function GateHero() {
  const reduce = useReducedMotion()
  const visible = useDocumentVisible()
  const shouldAnimate = useMemo(() => !reduce && visible, [reduce, visible])

  const tGateOpen = 0.7
  const tCarPass = 1.25
  const tGateClose = 1.9
  const cycle = 3.2

  return (
    <div className="glass-panel home-hero-card">
      <div className="home-hero-header">
        <div className="home-hero-chip">
          <span className="dot" />
          Live Gate Preview
        </div>
        <div className="home-hero-meta">
          <span className="material-symbols-outlined" style={{ fontSize: 14, marginRight: 6, verticalAlign: 'middle' }}>memory</span>
          Motion-ready UI
        </div>
      </div>

      <div className="home-hero-stage" role="img" aria-label="Animated car entering through a parking gate">
        <svg viewBox="0 0 760 360" width="100%" height="100%" className="home-hero-svg" aria-hidden="true">
          <defs>
            <linearGradient id="road" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="rgba(255,255,255,0.07)" />
              <stop offset="100%" stopColor="rgba(255,255,255,0.02)" />
            </linearGradient>
            <linearGradient id="gate" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stopColor="rgba(34,197,94,0.55)" />
              <stop offset="100%" stopColor="rgba(59,130,246,0.35)" />
            </linearGradient>
            <filter id="softGlow" x="-30%" y="-30%" width="160%" height="160%">
              <feGaussianBlur stdDeviation="10" result="blur" />
              <feColorMatrix in="blur" type="matrix"
                values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 0.5 0" result="glow" />
              <feMerge>
                <feMergeNode in="glow" />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>

          {/* Background grid */}
          <g opacity="0.35">
            {Array.from({ length: 10 }).map((_, i) => (
              <line key={`h-${i}`} x1="0" x2="760" y1={40 + i * 28} y2={40 + i * 28} stroke="rgba(255,255,255,0.05)" />
            ))}
            {Array.from({ length: 14 }).map((_, i) => (
              <line key={`v-${i}`} y1="0" y2="360" x1={40 + i * 52} x2={40 + i * 52} stroke="rgba(255,255,255,0.03)" />
            ))}
          </g>

          {/* Road */}
          <path d="M0 260 C 180 220, 340 220, 760 260 L 760 360 L 0 360 Z"
            fill="url(#road)" stroke="rgba(255,255,255,0.06)" />

          {/* Lane markers */}
          <motion.g
            initial={false}
            animate={shouldAnimate ? { x: [0, -60] } : { x: 0 }}
            transition={shouldAnimate
              ? { duration: 1.2, ease: 'linear', repeat: Infinity }
              : { duration: 0 }}
            opacity={0.55}
          >
            {Array.from({ length: 8 }).map((_, i) => (
              <rect key={`m-${i}`} x={100 + i * 90} y="292" width="44" height="6" rx="3"
                fill="rgba(255,255,255,0.12)" />
            ))}
          </motion.g>

          {/* Gate pillars */}
          <g filter="url(#softGlow)">
            <rect x="500" y="120" width="26" height="150" rx="10" fill="rgba(255,255,255,0.09)" />
            <rect x="620" y="120" width="26" height="150" rx="10" fill="rgba(255,255,255,0.09)" />
            <rect x="496" y="108" width="34" height="20" rx="10" fill="url(#gate)" opacity="0.75" />
          </g>

          {/* Gate arm pivot */}
          <circle cx="513" cy="128" r="8" fill="rgba(255,255,255,0.18)" />

          {/* Gate arm */}
          <motion.g
            style={{ originX: '513px', originY: '128px' } as React.CSSProperties}
            initial={false}
            animate={shouldAnimate
              ? { rotate: [0, 0, -68, -68, 0, 0] }
              : { rotate: -30 }}
            transition={shouldAnimate
              ? {
                  duration: cycle,
                  times: [0, tGateOpen / cycle, tCarPass / cycle, tGateClose / cycle, 0.92, 1],
                  ease: [0.16, 1, 0.3, 1],
                  repeat: Infinity,
                  repeatDelay: 0.15,
                }
              : { duration: 0 }}
          >
            <rect x="513" y="122" width="170" height="12" rx="6"
              fill="rgba(255,255,255,0.14)" stroke="rgba(255,255,255,0.2)" />
            <rect x="540" y="124" width="38" height="8" rx="4" fill="rgba(34,197,94,0.55)" />
            <rect x="590" y="124" width="38" height="8" rx="4" fill="rgba(239,68,68,0.45)" />
          </motion.g>

          {/* Car */}
          <motion.g
            initial={false}
            animate={shouldAnimate
              ? { x: [-260, 80, 520, 900] }
              : { x: 140 }}
            transition={shouldAnimate
              ? {
                  duration: cycle,
                  times: [0, 0.35, 0.67, 1],
                  ease: ['easeInOut', 'easeInOut', 'easeInOut'],
                  repeat: Infinity,
                  repeatDelay: 0.15,
                }
              : { duration: 0 }}
          >
            <ellipse cx="150" cy="292" rx="86" ry="14" fill="rgba(0,0,0,0.35)" />
            <g>
              <path d="M92 255 C 115 230, 140 222, 170 222 L 215 222 C 240 222, 260 232, 277 252 L 292 270 C 296 275, 294 282, 286 286 L 82 286 C 70 286, 64 280, 68 270 L 78 250 C 82 243, 86 259, 92 255 Z"
                fill="rgba(59,130,246,0.55)" stroke="rgba(255,255,255,0.16)" />
              <path d="M138 228 C 152 212, 170 204, 194 204 L 214 204 C 234 204, 248 212, 260 228 L 232 228 L 138 228 Z"
                fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.12)" />
              <rect x="94" y="254" width="196" height="30" rx="14" fill="rgba(15,23,42,0.35)" />
              <circle cx="120" cy="288" r="16" fill="rgba(15,23,42,0.85)" />
              <circle cx="120" cy="288" r="8" fill="rgba(255,255,255,0.12)" />
              <circle cx="250" cy="288" r="16" fill="rgba(15,23,42,0.85)" />
              <circle cx="250" cy="288" r="8" fill="rgba(255,255,255,0.12)" />
              <motion.circle
                cx="288" cy="270" r="6"
                fill="rgba(249,115,22,0.75)"
                animate={shouldAnimate ? { opacity: [0.4, 0.9, 0.4] } : { opacity: 0.7 }}
                transition={shouldAnimate ? { duration: 1.1, repeat: Infinity } : { duration: 0 }}
              />
            </g>
          </motion.g>
        </svg>
      </div>

      <div className="home-hero-footer">
        <div className="home-hero-kpi">
          <div className="kpi-title">Gate</div>
          <div className="kpi-value" style={{ color: '#22c55e' }}>
            <span style={{ fontSize: 8, marginRight: 6 }}>●</span>Ready
          </div>
        </div>
        <div className="home-hero-kpi">
          <div className="kpi-title">Mode</div>
          <div className="kpi-value">{reduce ? 'Reduced motion' : 'Animated'}</div>
        </div>
        <div className="home-hero-kpi">
          <div className="kpi-title">Loop</div>
          <div className="kpi-value">{visible ? 'Active' : 'Paused'}</div>
        </div>
      </div>
    </div>
  )
}
