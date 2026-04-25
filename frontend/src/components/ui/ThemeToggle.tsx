import { useEffect, useState } from 'react'
import { Sun, Moon } from 'lucide-react'
import { motion, AnimatePresence } from 'motion/react'

export function ThemeToggle() {
  const [isDark, setIsDark] = useState(() => {
    if (typeof window !== 'undefined') {
      const savedTheme = localStorage.getItem('theme')
      if (savedTheme) {
        return savedTheme === 'dark'
      }
      return window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    return false
  })

  useEffect(() => {
    const root = window.document.documentElement
    if (isDark) {
      root.classList.add('dark')
      localStorage.setItem('theme', 'dark')
    } else {
      root.classList.remove('dark')
      localStorage.setItem('theme', 'light')
    }
  }, [isDark])

  return (
    <motion.button
      whileHover={{ scale: 1.05 }}
      whileTap={{ scale: 0.95 }}
      onClick={() => setIsDark(!isDark)}
      className="relative w-10 h-10 flex items-center justify-center rounded-xl bg-surface border border-border shadow-sm hover:border-brand/30 transition-colors overflow-hidden group"
      aria-label="Toggle theme"
    >
      <AnimatePresence mode="wait">
        {isDark ? (
          <motion.div
            key="moon"
            initial={{ y: 20, opacity: 0, rotate: 45 }}
            animate={{ y: 0, opacity: 1, rotate: 0 }}
            exit={{ y: -20, opacity: 0, rotate: -45 }}
            transition={{ duration: 0.2, ease: "backOut" }}
          >
            <Moon className="h-5 w-5 text-brand" />
          </motion.div>
        ) : (
          <motion.div
            key="sun"
            initial={{ y: 20, opacity: 0, rotate: -45 }}
            animate={{ y: 0, opacity: 1, rotate: 0 }}
            exit={{ y: -20, opacity: 0, rotate: 45 }}
            transition={{ duration: 0.2, ease: "backOut" }}
          >
            <Sun className="h-5 w-5 text-brand" />
          </motion.div>
        )}
      </AnimatePresence>
      
      {/* Subtle background glow on hover */}
      <div className="absolute inset-0 bg-brand/5 opacity-0 group-hover:opacity-100 transition-opacity" />
    </motion.button>
  )
}
