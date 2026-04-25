import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'motion/react'
import { endpoints } from '../lib/api'
import { 
  Menu, 
  Car, 
  Wallet, 
  Receipt, 
  Calendar, 
  ChevronRight, 
  History, 
  Search, 
  Ticket, 
  User,
  Bell,
  ParkingCircle,
  MapPin,
  Clock
} from 'lucide-react'
import { ThemeToggle } from '../components/ui/ThemeToggle'

export default function BookingsPage() {
  const navigate = useNavigate()
  const [activeTab, setActiveTab] = useState('active')
  const [bookings, setBookings] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    endpoints.bookings()
      .then(res => {
        if (res.success) setBookings(res.data)
      })
      .catch(console.error)
      .finally(() => setLoading(false))
  }, [])

  const activeBookings = bookings.filter(b => b.status === 'IN_PARK')
  const upcomingBookings = bookings.filter(b => b.status === 'BOOKED')
  const pastBookings = bookings.filter(b => b.status === 'COMPLETED' || b.status === 'CANCELLED')

  const currentList = activeTab === 'active' ? activeBookings : activeTab === 'upcoming' ? upcomingBookings : pastBookings

  return (
    <div className="min-h-screen bg-bg-page text-text-main font-manrope pb-32">
      {/* Top Bar */}
      <header className="bg-surface/80 backdrop-blur-md border-b border-border flex justify-between items-center w-full px-6 py-4 sticky top-0 z-40">
        <div className="flex items-center gap-4">
          <button className="text-text-muted hover:text-primary transition-colors">
            <Menu className="h-6 w-6" />
          </button>
          <h1 className="font-black text-lg tracking-tight text-text-main flex items-center gap-2">
            My <span className="text-primary">Bookings</span>
          </h1>
        </div>
        <div className="flex items-center gap-4">
          <ThemeToggle />
          <button 
            onClick={() => navigate('/account')}
            className="w-10 h-10 rounded-full overflow-hidden border-2 border-primary/20 shadow-lg shadow-primary/5 hover:border-primary transition-colors active:scale-90"
          >
            <img 
              src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=200" 
              alt="Profile" 
              className="w-full h-full object-cover"
            />
          </button>
        </div>
      </header>

      <main className="px-6 pt-8 max-w-2xl mx-auto space-y-8">
        {/* Tab Switcher */}
        <div className="flex p-1.5 bg-surface-alt rounded-2xl border border-border">
          {['active', 'upcoming', 'past'].map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`flex-1 py-3 text-xs font-black uppercase tracking-widest rounded-xl transition-all duration-300 ${
                activeTab === tab 
                ? 'bg-primary text-white shadow-lg shadow-primary/20' 
                : 'text-text-muted hover:text-primary'
              }`}
            >
              {tab}
            </button>
          ))}
        </div>

        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 text-text-muted">
            <div className="w-10 h-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin mb-4"></div>
            <p className="text-sm font-bold uppercase tracking-widest">Loading your bookings...</p>
          </div>
        ) : currentList.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-text-muted text-center">
            <div className="w-20 h-20 bg-surface-alt rounded-full flex items-center justify-center mb-6">
              <Ticket className="h-10 w-10 opacity-20" />
            </div>
            <h3 className="text-lg font-black text-text-main mb-2">No {activeTab} bookings</h3>
            <p className="text-sm font-medium max-w-[240px]">You don't have any {activeTab} parking reservations at the moment.</p>
            <button 
              onClick={() => navigate('/reserve')}
              className="mt-8 text-primary font-black text-xs uppercase tracking-widest border-b-2 border-primary pb-1 hover:opacity-80 transition-opacity"
            >
              Reserve a spot now
            </button>
          </div>
        ) : (
          <div className="space-y-8">
            {currentList.map((booking, idx) => (
              <motion.div 
                key={booking.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: idx * 0.1 }}
                className="card-premium relative overflow-hidden group !p-8"
              >
                <div className="absolute top-0 right-0 p-8 opacity-5 transition-transform group-hover:scale-125 group-hover:rotate-12">
                  <ParkingCircle className="h-24 w-24" />
                </div>

                <div className="relative z-10">
                  <div className="flex items-start justify-between mb-8">
                    <div>
                      <h3 className="text-2xl font-black text-primary mb-1">Berserk Store</h3>
                      <div className="flex items-center gap-2 text-text-muted">
                        <MapPin className="h-3.5 w-3.5" />
                        <span className="text-xs font-medium">Jl. Terbaik No. 67, Central District</span>
                      </div>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                      <div className={`px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider border ${
                        booking.status === 'BOOKED' 
                        ? 'bg-blue-500/10 text-blue-500 border-blue-500/20' 
                        : booking.status === 'IN_PARK'
                        ? 'bg-green-500/10 text-green-500 border-green-500/20'
                        : 'bg-text-muted/10 text-text-muted border-text-muted/20'
                      }`}>
                        {booking.status === 'IN_PARK' && <span className="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse mr-2"></span>}
                        {booking.status.replace('_', ' ')}
                      </div>
                      <div className="bg-primary/10 p-3 rounded-2xl border border-primary/20">
                        {booking.vehicle_type === 'motorcycle' ? <Clock className="h-6 w-6 text-primary" /> : <Car className="h-6 w-6 text-primary" />}
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-8 mb-8 border-y border-border/50 py-6">
                    <div>
                      <p className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Planned Entry</p>
                      <p className="text-sm font-bold flex items-center gap-2">
                        <Clock className="h-3.5 w-3.5 text-primary" /> {new Date(booking.jam_masuk_rencana).toLocaleString()}
                      </p>
                    </div>
                    <div>
                      <p className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1.5">Vehicle</p>
                      <p className="text-sm font-black text-primary bg-primary/5 px-3 py-1 rounded-lg border border-primary/10 inline-block">
                        {booking.plat_nomor}
                      </p>
                    </div>
                  </div>

                  {booking.status === 'IN_PARK' && (
                    <div className="bg-surface-alt rounded-2xl p-5 flex items-center justify-between mb-8 border-l-4 border-primary shadow-inner">
                      <div>
                        <p className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1">Estimated Fee</p>
                        <p className="text-3xl font-black text-text-main">IDR 12.000</p>
                      </div>
                      <Wallet className="h-8 w-8 text-primary/20" />
                    </div>
                  )}

                  <button 
                    onClick={() => {
                      // Navigate to a dedicated receipt/detail page or show modal
                      alert('Showing receipt for ' + booking.plat_nomor)
                    }}
                    className="w-full bg-primary hover:bg-primary/90 text-white py-5 rounded-2xl font-black flex items-center justify-center gap-3 shadow-xl shadow-primary/20 transition-all active:scale-[0.98]"
                  >
                    <Receipt className="h-5 w-5" />
                    View Digital Receipt
                  </button>
                </div>
              </motion.div>
            ))}
          </div>
        )}

        <button 
          onClick={() => navigate('/reserve')}
          className="w-full py-5 border-2 border-border text-text-muted font-black uppercase tracking-widest text-xs rounded-2xl hover:border-primary/30 hover:text-primary hover:bg-primary/5 transition-all flex items-center justify-center gap-3"
        >
          <History className="h-4 w-4" />
          Make Another Reservation
        </button>
      </main>

      {/* Mobile Bottom Nav */}
      <nav className="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-6 py-4 bg-surface/80 backdrop-blur-xl border-t border-border shadow-[0_-8px_30px_rgba(0,0,0,0.04)] md:hidden">
        <button 
          onClick={() => navigate('/reserve')}
          className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors"
        >
          <Search className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Find</span>
        </button>
        <button className="flex flex-col items-center gap-1 text-primary">
          <Ticket className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Bookings</span>
        </button>
        <button 
          onClick={() => navigate('/account')}
          className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors"
        >
          <User className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Account</span>
        </button>
      </nav>
    </div>
  )
}
