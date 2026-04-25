import { motion } from 'motion/react'
import { 
  ArrowLeft, 
  MoreVertical, 
  Pencil, 
  Car, 
  ChevronRight, 
  User, 
  Wallet, 
  Bell, 
  HelpCircle, 
  Shield, 
  LogOut, 
  Search, 
  ParkingCircle 
} from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { ThemeToggle } from '../components/ui/ThemeToggle'
import { useAuth } from '../hooks/useAuth'

export default function AccountPage() {
  const navigate = useNavigate()
  const { logout } = useAuth()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="min-h-screen bg-bg-page text-text-main font-manrope pb-24">
      {/* Top Bar */}
      <header className="bg-surface/80 backdrop-blur-md border-b border-border flex justify-between items-center w-full px-4 h-16 sticky top-0 z-50">
        <button 
          onClick={() => navigate(-1)}
          className="p-2 text-text-muted hover:text-primary hover:bg-surface-alt rounded-full transition-all active:scale-90"
        >
          <ArrowLeft className="h-6 w-6" />
        </button>
        <h1 className="font-black text-lg tracking-tight">Account</h1>
        <div className="flex items-center gap-2">
          <ThemeToggle />
          <button className="p-2 text-text-muted hover:text-primary hover:bg-surface-alt rounded-full transition-all">
            <MoreVertical className="h-6 w-6" />
          </button>
        </div>
      </header>

      <main className="max-w-md mx-auto px-6 py-10 space-y-10">
        {/* Profile Section */}
        <section className="flex flex-col items-center text-center">
          <motion.div 
            initial={{ scale: 0.8, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="relative mb-6"
          >
            <div className="w-28 h-28 rounded-full border-4 border-primary/10 p-1.5 shadow-xl shadow-primary/10">
              <img 
                src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=300" 
                alt="Profile" 
                className="w-full h-full rounded-full object-cover"
              />
            </div>
            <button className="absolute bottom-1 right-1 bg-primary text-white p-2.5 rounded-full shadow-lg hover:bg-primary/90 active:scale-90 transition-all border-4 border-surface">
              <Pencil className="h-4 w-4" />
            </button>
          </motion.div>
          
          <motion.div
            initial={{ y: 10, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            transition={{ delay: 0.1 }}
          >
            <h2 className="text-2xl font-black tracking-tight text-text-main">Budi Santoso</h2>
            <p className="text-sm font-medium text-text-muted mt-1">budi.santoso@email.com</p>
          </motion.div>
        </section>

        {/* My Vehicles Section */}
        <section className="space-y-4">
          <div className="flex justify-between items-center px-1">
            <h3 className="text-[11px] font-black uppercase tracking-widest text-text-muted">My Vehicles</h3>
            <button className="text-primary text-xs font-bold hover:underline">Add New</button>
          </div>
          <motion.div 
            whileHover={{ scale: 1.02 }}
            className="bg-surface p-5 rounded-2xl border border-border shadow-sm flex items-center gap-4 cursor-pointer group"
          >
            <div className="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center border border-primary/10">
              <Car className="h-7 w-7 text-primary" />
            </div>
            <div className="flex-1">
              <p className="font-black text-text-main group-hover:text-primary transition-colors">Toyota Avanza</p>
              <p className="text-xs font-medium text-text-muted mt-1">B 1234 ABC • Silver</p>
            </div>
            <ChevronRight className="h-5 w-5 text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-1" />
          </motion.div>
        </section>

        {/* Settings Groups */}
        <div className="space-y-8">
          {/* Account Settings */}
          <section className="space-y-4">
            <h3 className="text-[11px] font-black uppercase tracking-widest text-text-muted px-1">Account Settings</h3>
            <div className="bg-surface border border-border rounded-3xl overflow-hidden shadow-sm">
              {[
                { label: 'Edit Profile', icon: User },
                { label: 'Payment Methods', icon: Wallet },
                { label: 'Notification Settings', icon: Bell }
              ].map((item, i, arr) => (
                <div 
                  key={item.label}
                  className={`flex items-center gap-4 p-5 hover:bg-surface-alt transition-all cursor-pointer group ${
                    i !== arr.length - 1 ? 'border-b border-border/50' : ''
                  }`}
                >
                  <div className="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
                    <item.icon className="h-5 w-5 text-text-muted group-hover:text-primary" />
                  </div>
                  <span className="flex-1 font-bold text-sm text-text-main">{item.label}</span>
                  <ChevronRight className="h-4 w-4 text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-1" />
                </div>
              ))}
            </div>
          </section>

          {/* Support */}
          <section className="space-y-4">
            <h3 className="text-[11px] font-black uppercase tracking-widest text-text-muted px-1">Support</h3>
            <div className="bg-surface border border-border rounded-3xl overflow-hidden shadow-sm">
              {[
                { label: 'Help Center', icon: HelpCircle },
                { label: 'Privacy Policy', icon: Shield }
              ].map((item, i, arr) => (
                <div 
                  key={item.label}
                  className={`flex items-center gap-4 p-5 hover:bg-surface-alt transition-all cursor-pointer group ${
                    i !== arr.length - 1 ? 'border-b border-border/50' : ''
                  }`}
                >
                  <div className="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
                    <item.icon className="h-5 w-5 text-text-muted group-hover:text-primary" />
                  </div>
                  <span className="flex-1 font-bold text-sm text-text-main">{item.label}</span>
                  <ChevronRight className="h-4 w-4 text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-1" />
                </div>
              ))}
            </div>
          </section>
        </div>

        {/* Log Out Button */}
        <button 
          onClick={handleLogout}
          className="w-full flex items-center justify-center gap-3 py-5 rounded-2xl border-2 border-red-500/20 text-red-500 font-black uppercase tracking-widest text-xs hover:bg-red-500/5 active:scale-[0.98] transition-all"
        >
          <LogOut className="h-4 w-4" />
          Log Out
        </button>
      </main>

      {/* Mobile Bottom Nav */}
      <nav className="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-6 py-4 bg-surface/80 backdrop-blur-xl border-t border-border shadow-[0_-8px_30px_rgba(0,0,0,0.04)] md:hidden">
        <button onClick={() => navigate('/reserve')} className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors">
          <Search className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Find</span>
        </button>
        <button onClick={() => navigate('/bookings')} className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors">
          <ParkingCircle className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Bookings</span>
        </button>
        <button className="flex flex-col items-center gap-1 text-primary">
          <User className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Account</span>
        </button>
      </nav>
    </div>
  )
}
