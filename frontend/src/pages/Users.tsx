import { useState, useEffect } from 'react'
import { ActionSearchBar } from '../components/ui/action-search-bar'
import { 
  Shield, 
  UserCog, 
  Users as UsersIcon, 
  Key, 
  Lock, 
  Unlock, 
  UserPlus, 
  ShieldCheck,
  Monitor,
  Download
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { ThemeToggle } from '../components/ui/ThemeToggle'

interface User {
  user_id: number
  username: string
  role: string
  full_name: string
  last_login: string | null
  is_active: number
  created_at: string
}

const ROLE_ACTIONS = [
  { id: 'operator', label: 'Operator', description: 'Standard Access', icon: <Monitor className="h-4 w-4 text-blue-500" />, end: 'Level 1' },
  { id: 'admin', label: 'Admin', description: 'Management Access', icon: <UserCog className="h-4 w-4 text-amber-500" />, end: 'Level 2' },
  { id: 'superadmin', label: 'Superadmin', description: 'Full Access', icon: <Shield className="h-4 w-4 text-red-500" />, end: 'Root' },
]

export default function UsersPage() {
  const [users, setUsers] = useState<User[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  // Form state
  const [formData, setFormData] = useState({
    username: '',
    password: '',
    full_name: '',
    role: ''
  })

  const fetchUsers = async () => {
    try {
      const res = await fetch('/api/users.php')
      const data = await res.json()
      setUsers(data)
    } catch (err) {
      setError('Gagal memuat data user.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchUsers()
  }, [])

  const handleAddUser = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(''); setSuccess('')
    try {
      const res = await fetch('/api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', ...formData })
      })
      const data = await res.json()
      if (data.success) {
        setSuccess(data.message)
        setFormData({ username: '', password: '', full_name: '', role: 'operator' })
        fetchUsers()
      } else {
        setError(data.error)
      }
    } catch (err) {
      setError('Koneksi gagal.')
    }
  }

  const toggleStatus = async (user_id: number) => {
    try {
      const res = await fetch('/api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle', user_id })
      })
      const data = await res.json()
      if (data.success) fetchUsers()
      else alert(data.error)
    } catch (err) {
      alert('Koneksi gagal.')
    }
  }

  return (
    <div className="p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-text-main">User Management</h1>
          <p className="text-text-muted text-sm">Kelola identitas sistem dan kontrol akses operasional.</p>
        </div>
        <div className="flex items-center gap-3">
          <button className="flex items-center gap-2 bg-surface border border-border px-4 py-2 rounded-xl text-sm font-bold text-text-main hover:bg-surface-alt transition-all shadow-sm">
            <Download className="h-4 w-4 text-primary" />
            Export
          </button>
          <ThemeToggle />
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[400px_1fr] gap-8">
        
        {/* Sidebar: Provision Form */}
        <div className="space-y-6">
          <section className="card-premium !p-0 overflow-hidden">
            <div className="h-1.5 bg-primary" />
            <div className="p-6">
              <div className="flex items-center gap-3 mb-6">
                <div className="w-10 h-10 rounded-xl bg-primary-subtle flex items-center justify-center">
                  <UserPlus className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <h2 className="font-bold text-text-main">Provision Account</h2>
                  <p className="text-xs text-text-muted uppercase tracking-wider font-semibold">New Identity</p>
                </div>
              </div>

              <form onSubmit={handleAddUser} className="space-y-4">
                <div className="space-y-2">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-text-muted px-1">Username</label>
                  <input 
                    type="text" 
                    value={formData.username}
                    onChange={e => setFormData({...formData, username: e.target.value})}
                    className="w-full bg-surface-alt border border-border rounded-xl px-4 py-2.5 text-sm text-text-main focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    placeholder="e.g. john_doe"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-text-muted px-1">Full Name</label>
                  <input 
                    type="text" 
                    value={formData.full_name}
                    onChange={e => setFormData({...formData, full_name: e.target.value})}
                    className="w-full bg-surface-alt border border-border rounded-xl px-4 py-2.5 text-sm text-text-main focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    placeholder="Optional"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-text-muted px-1">Authorization Role</label>
                  <ActionSearchBar 
                    value={formData.role}
                    actions={ROLE_ACTIONS.map(a => ({
                      ...a,
                      onClick: () => setFormData({...formData, role: a.id})
                    })) as any} 
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-[10px] font-bold uppercase tracking-widest text-text-muted px-1">Password</label>
                  <input 
                    type="password" 
                    value={formData.password}
                    onChange={e => setFormData({...formData, password: e.target.value})}
                    className="w-full bg-surface-alt border border-border rounded-xl px-4 py-2.5 text-sm text-text-main focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    placeholder="Min 8 characters"
                    required
                  />
                </div>

                <button 
                  type="submit"
                  className="w-full bg-primary text-white rounded-xl py-3 text-sm font-bold hover:opacity-90 transition-all flex items-center justify-center gap-2"
                >
                  <ShieldCheck className="h-4 w-4" />
                  Provision Account
                </button>

                {error && <p className="text-xs text-red-500 font-medium text-center">{error}</p>}
                {success && <p className="text-xs text-emerald-500 font-medium text-center">{success}</p>}
              </form>
            </div>
          </section>
        </div>

        {/* Main: User Table */}
        <div className="card-premium !p-0 overflow-hidden">
          <div className="p-6 border-b border-border flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-surface-alt flex items-center justify-center">
                <UsersIcon className="h-5 w-5 text-text-muted" />
              </div>
              <h2 className="font-bold text-text-main">System Identities ({users.length})</h2>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="bg-surface-alt/50">
                  <th className="text-left px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">Identity</th>
                  <th className="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">Role</th>
                  <th className="text-left px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">Last Login</th>
                  <th className="text-center px-4 py-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">Status</th>
                  <th className="text-right px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {users.map(u => (
                  <tr key={u.user_id} className={cn("hover:bg-slate-50 transition-colors", u.is_active === 0 && "opacity-60 grayscale-[0.5]")}>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-bold text-slate-600">
                          {u.username[0].toUpperCase()}
                        </div>
                        <div>
                          <div className="text-sm font-bold text-slate-900">{u.username}</div>
                          <div className="text-xs text-slate-500">{u.full_name}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-4">
                      <span className={cn(
                        "text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full",
                        u.role === 'superadmin' ? "bg-red-50 text-red-600" :
                        u.role === 'admin' ? "bg-amber-50 text-amber-600" : "bg-blue-50 text-blue-600"
                      )}>
                        {u.role}
                      </span>
                    </td>
                    <td className="px-4 py-4 text-xs text-slate-500">
                      {u.last_login ? new Date(u.last_login).toLocaleString() : '—'}
                    </td>
                    <td className="px-4 py-4 text-center">
                      <div className={cn(
                        "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase",
                        u.is_active ? "bg-emerald-50 text-emerald-600" : "bg-slate-100 text-slate-400"
                      )}>
                        <div className={cn("w-1.5 h-1.5 rounded-full", u.is_active ? "bg-emerald-500" : "bg-slate-300")} />
                        {u.is_active ? 'Active' : 'Inactive'}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center justify-end gap-2">
                        <button 
                          onClick={() => toggleStatus(u.user_id)}
                          className={cn(
                            "p-2 rounded-lg transition-all",
                            u.is_active ? "text-amber-600 hover:bg-amber-50" : "text-emerald-600 hover:bg-emerald-50"
                          )}
                          title={u.is_active ? 'Disable' : 'Enable'}
                        >
                          {u.is_active ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
                        </button>
                        <button className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-all">
                          <Key className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  )
}
