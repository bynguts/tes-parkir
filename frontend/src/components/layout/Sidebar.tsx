import { NavLink, useNavigate, useLocation } from 'react-router-dom'
import { motion } from 'motion/react'
import { endpoints } from '../../lib/api'
import { useAuth } from '../../hooks/useAuth'
import { cn } from '../../lib/utils'
import {
  LayoutDashboard,
  DoorOpen,
  Car,
  CalendarCheck,
  ClipboardList,
  BarChart4,
  Map as MapIcon,
  Settings,
  CreditCard,
  UserSquare,
  Users,
  LogOut
} from 'lucide-react'
import { ThemeToggle } from '../ui/ThemeToggle'

interface NavItem {
  to: string
  icon: any
  label: string
  roles?: string[]
}

const NAV_SECTIONS: Array<{ title: string; items: NavItem[] }> = [
  {
    title: 'Operations',
    items: [
      { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard' },
      { to: '/gate', icon: DoorOpen, label: 'Smart Gate' },
      { to: '/vehicles', icon: Car, label: 'Active Vehicles' },
      { to: '/reservations', icon: CalendarCheck, label: 'Reservations' },
      { to: '/scan-log', icon: ClipboardList, label: 'Scan Log' },
    ],
  },
  {
    title: 'Reports',
    items: [
      { to: '/reports/revenue', icon: BarChart4, label: 'Revenue' },
      { to: '/reports/slot-map', icon: MapIcon, label: 'Slot Map' },
    ],
  },
  {
    title: 'Administration',
    items: [
      { to: '/admin/slots', icon: Settings, label: 'Manage Slots', roles: ['superadmin', 'admin'] },
      { to: '/admin/rates', icon: CreditCard, label: 'Manage Rates', roles: ['superadmin', 'admin'] },
      { to: '/admin/operators', icon: UserSquare, label: 'Operators', roles: ['superadmin', 'admin'] },
      { to: '/admin/users', icon: Users, label: 'Users', roles: ['superadmin'] },
    ],
  },
]

export function Sidebar() {
  const { user, refetch } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const role = user?.role ?? 'operator'
  const username = user?.full_name ?? user?.username ?? 'User'

  const handleLogout = async () => {
    try {
      await endpoints.logout()
    } finally {
      await refetch()
      navigate('/login')
    }
  }

  return (
    <aside className="fixed top-0 left-0 w-64 h-screen bg-sidebar flex flex-col z-40 border-r border-white/5">
      {/* Brand */}
      <div className="px-8 h-20 flex items-center gap-3">
        <div className="w-10 h-10 bg-brand rounded-2xl flex items-center justify-center shadow-lg shadow-brand/20">
          <LayoutDashboard className="text-white h-6 w-6" />
        </div>
        <div>
          <h1 className="font-outfit font-extrabold text-white text-xl tracking-tight leading-none">Smart</h1>
          <p className="text-brand text-[10px] font-bold uppercase tracking-[0.2em] mt-1">Parking</p>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-4 py-6 space-y-8 no-scrollbar">
        {NAV_SECTIONS.map(section => {
          const visibleItems = section.items.filter(
            item => !item.roles || item.roles.includes(role)
          )
          if (visibleItems.length === 0) return null
          return (
            <div key={section.title}>
              <div className="px-4 mb-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 font-inter">
                {section.title}
              </div>
              <ul className="space-y-1">
                {visibleItems.map(item => {
                  const isActive = location.pathname === item.to
                  const Icon = item.icon
                  return (
                    <li key={item.to}>
                      <NavLink
                        to={item.to}
                        className={cn(
                          "group flex items-center gap-3 px-4 py-3 rounded-xl transition-all relative overflow-hidden",
                          isActive
                            ? "sidebar-btn-trace bg-white/5 text-white"
                            : "text-slate-400 hover:text-white hover:bg-white/5"
                        )}
                      >
                        <Icon className={cn(
                          "h-4 w-4 transition-colors",
                          isActive ? "text-brand" : "text-slate-500 group-hover:text-slate-300"
                        )} />
                        <span className="font-inter font-semibold text-sm">{item.label}</span>
                      </NavLink>
                    </li>
                  )
                })}
              </ul>
            </div>
          )
        })}
      </nav>

      {/* Footer Info */}
      <div className="p-6 border-t border-white/5 bg-black/20">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center border border-white/10 overflow-hidden">
            <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-brand to-violet-400 flex items-center justify-center text-white text-xs font-bold">
              {username.charAt(0).toUpperCase()}
            </div>
          </div>
          <div className="min-w-0">
            <p className="text-white text-sm font-bold truncate">{username}</p>
            <p className="text-slate-500 text-[10px] font-bold uppercase tracking-wider">{role}</p>
          </div>
        </div>

        <div className="mb-4">
          <ThemeToggle />
        </div>

        <button
          onClick={handleLogout}
          className="flex items-center justify-center gap-2 w-full bg-white/5 hover:bg-white/10 text-white text-xs font-bold font-inter uppercase tracking-widest rounded-xl py-3 transition-all cursor-pointer border border-white/5"
        >
          <LogOut className="h-4 w-4 text-brand" />
          Logout
        </button>
      </div>
    </aside>
  )
}
