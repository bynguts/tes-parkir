import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams, Link } from 'react-router-dom'
import { motion } from 'motion/react'
import { endpoints } from '../lib/api'
import { useAuth } from '../hooks/useAuth'
import {
  ShieldCheck,
  User,
  Lock,
  Eye,
  EyeOff,
  ArrowRight,
  ParkingCircle,
  AlertCircle,
  Loader2
} from 'lucide-react'
import { ThemeToggle } from '../components/ui/ThemeToggle'

export default function LoginPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const { refetch } = useAuth()

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [csrfToken, setCsrfToken] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [showPw, setShowPw] = useState(false)

  useEffect(() => {
    endpoints.csrf().then(d => setCsrfToken(d.token)).catch(() => { })
  }, [])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!username || !password) { setError('Username and password are required.'); return }
    setLoading(true); setError('')
    try {
      const res = await endpoints.login(username, password, csrfToken)
      if (res.success) {
        await refetch()
        const next = params.get('next') ?? '/dashboard'
        navigate(next, { replace: true })
      } else {
        setError(res.error ?? 'Authentication failed.')
        endpoints.csrf().then(d => setCsrfToken(d.token)).catch(() => { })
      }
    } catch (err: unknown) {
      setError('Connection to server failed. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="home-page flex items-center justify-center p-6">
      <div className="mesh-gradient" />

      {/* Theme Toggle Top Right */}
      <div className="absolute top-8 right-8 z-20">
        <ThemeToggle />
      </div>

      {/* Background patterns */}
      <div className="absolute inset-0 z-0 pointer-events-none" style={{
        backgroundImage: 'radial-gradient(var(--border) 1px, transparent 1px)',
        backgroundSize: '40px 40px',
        opacity: 0.2
      }} />

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
        className="relative z-10 w-full max-w-[440px]"
      >
        {/* Logo */}
        <Link to="/" className="flex items-center justify-center gap-3 mb-10 group">
          <div className="w-12 h-12 rounded-2xl bg-primary flex items-center justify-center text-white shadow-xl shadow-primary/20 transition-transform group-hover:scale-110">
            <ParkingCircle className="h-7 w-7" />
          </div>
          <span className="font-manrope font-black text-2xl tracking-tight text-text-main">
            Smart<span className="text-primary">Parking</span>
          </span>
        </Link>

        {/* Card */}
        <div className="card-premium !p-10 shadow-2xl shadow-primary/5 backdrop-blur-xl">
          <div className="mb-10">
            <h1 className="font-manrope text-2xl font-black text-text-main mb-2">Secure Access</h1>
            <p className="text-text-muted text-sm leading-relaxed font-inter">
              Enter your enterprise credentials to access the parking management workspace.
            </p>
          </div>

          {error && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              className="mb-8 flex items-center gap-3 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3 text-red-500 text-sm font-bold"
            >
              <AlertCircle className="h-4 w-4 flex-shrink-0" />
              <span>{error}</span>
            </motion.div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-2">
              <label className="text-[10px] font-black uppercase tracking-widest text-text-muted ml-1">
                Username
              </label>
              <div className="relative group">
                <div className="absolute left-4 inset-y-0 flex items-center pointer-events-none text-text-muted group-focus-within:text-primary transition-colors">
                  <User className="h-5 w-5" />
                </div>
                <input
                  type="text"
                  value={username}
                  onChange={e => setUsername(e.target.value)}
                  className="w-full bg-surface-alt border border-border rounded-xl py-3.5 pl-12 pr-4 text-text-main text-sm font-bold outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all"
                  placeholder="admin_id"
                />
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-[10px] font-black uppercase tracking-widest text-text-muted ml-1">
                Password
              </label>
              <div className="relative group">
                <div className="absolute left-4 inset-y-0 flex items-center pointer-events-none text-text-muted group-focus-within:text-primary transition-colors">
                  <Lock className="h-5 w-5" />
                </div>
                <input
                  type={showPw ? 'text' : 'password'}
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  className="w-full bg-surface-alt border border-border rounded-xl py-3.5 pl-12 pr-12 text-text-main text-sm font-bold outline-none focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  onClick={() => setShowPw(!showPw)}
                  className="absolute right-4 inset-y-0 flex items-center text-text-muted hover:text-text-main transition-colors"
                >
                  {showPw ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                </button>
              </div>
            </div>

            <div className="pt-4">
              <motion.button
                type="submit"
                disabled={loading}
                whileHover={{ scale: 1.01 }}
                whileTap={{ scale: 0.98 }}
                className="w-full home-btn home-btn-primary flex justify-center !rounded-xl"
              >
                {loading ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <>
                    Sign In to Workspace
                    <ArrowRight className="h-4 w-4" />
                  </>
                )}
              </motion.button>
            </div>
          </form>

          <div className="mt-10 pt-8 border-t border-border">
            <p className="text-center text-xs text-text-muted">
              Need assistance? <a href="#" className="text-primary font-bold hover:underline">Contact System Admin</a>
            </p>
          </div>
        </div>

        <p className="text-center mt-12 text-[10px] font-black uppercase tracking-widest text-text-muted opacity-50">
          SmartParking Enterprise • Infrastructure Node A
        </p>
      </motion.div>
    </div>
  )
}
