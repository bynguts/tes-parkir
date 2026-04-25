/**
 * PhpPage.tsx
 * Renders PHP module pages inside an iframe.
 * This allows the React app to host the routing while
 * PHP modules (gate, reservation, reports, admin) keep running server-side.
 *
 * Usage: <PhpPage src="/modules/operations/gate_simulator.php" title="Smart Gate" />
 */
import { useEffect, useRef, useState } from 'react'
import { motion } from 'motion/react'

interface PhpPageProps {
  src: string
  title: string
}

export default function PhpPage({ src, title }: PhpPageProps) {
  const iframeRef = useRef<HTMLIFrameElement>(null)
  const [loading, setLoading] = useState(true)

  // Strip the sidebar/header from PHP pages rendered in iframe
  // by appending ?embed=1 — the PHP modules check for this param
  const embedSrc = src.includes('?') ? `${src}&embed=1` : `${src}?embed=1`

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {loading && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          style={{
            position: 'absolute', inset: 0, zIndex: 10,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            background: '#f2f4f7',
          }}
        >
          <div style={{ textAlign: 'center', color: '#94a3b8' }}>
            <span
              className="material-symbols-outlined"
              style={{ fontSize: 36, display: 'block', marginBottom: 8, animation: 'spin 1s linear infinite' }}
            >progress_activity</span>
            <span style={{ fontSize: 13, fontFamily: 'Inter, sans-serif' }}>Memuat {title}...</span>
          </div>
        </motion.div>
      )}
      <iframe
        ref={iframeRef}
        src={embedSrc}
        title={title}
        onLoad={() => setLoading(false)}
        style={{
          flex: 1,
          border: 'none',
          width: '100%',
          height: '100%',
          background: '#f2f4f7',
          opacity: loading ? 0 : 1,
          transition: 'opacity 200ms ease',
        }}
      />
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  )
}
