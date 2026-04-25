import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { motion } from 'motion/react'
import { 
  CheckCircle, 
  MapPin, 
  Star, 
  ShieldCheck, 
  Zap, 
  Camera, 
  ArrowUpToLine, 
  Car, 
  Bike, 
  Receipt,
  Bell,
  Search,
  Ticket,
  User as UserIcon,
  ParkingCircle,
  Calendar,
  Clock,
  Map as MapIcon,
  Download
} from 'lucide-react'
import { ThemeToggle } from '../components/ui/ThemeToggle'
import { Button } from '../components/ui/button'
import { useEffect, useRef } from 'react'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.min.css'
import type { Instance } from 'flatpickr/dist/types/instance'

export default function ReservePage() {
  const navigate = useNavigate()
  const [vehicleType, setVehicleType] = useState<'car' | 'motorcycle'>('car')
  
  // Initialize with current date/time
  const now = new Date()
  const todayStr = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().split('T')[0]
  const hourStr = String(now.getHours()).padStart(2, '0') + ':00'
  const exitHourStr = String((now.getHours() + 3) % 24).padStart(2, '0') + ':00'

  const [entryDate, setEntryDate] = useState(todayStr)
  const [entryTime, setEntryTime] = useState(hourStr)
  const [exitDate, setExitDate] = useState(todayStr)
  const [exitTime, setExitTime] = useState(exitHourStr)
  
  const [plateNumber, setPlateNumber] = useState('')

  const [loading, setLoading] = useState(false)
  const [success, setSuccess] = useState(false)
  const [bookingResult, setBookingResult] = useState<any>(null)

  // Refs for calendar inputs
  const entryRef = useRef<HTMLInputElement>(null)
  const exitRef = useRef<HTMLInputElement>(null)

  // Custom Flatpickr Helpers (same as dashboard)
  const buildDropdowns = (instance: Instance) => {
    try {
      const cal = instance.calendarContainer
      if (!cal) return
      const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']
      cal.querySelectorAll('.fp-inject').forEach(el => el.remove())
      const makeSelect = (options: {v: any, l: string}[], currentVal: any, onChange: (val: number) => void, extraStyle = '') => {
        const sel = document.createElement('select')
        sel.className = 'fp-inject'
        if (extraStyle) sel.style.cssText += extraStyle
        options.forEach(item => {
          const o = document.createElement('option')
          o.value = item.v; o.textContent = item.l
          if (String(item.v) === String(currentVal)) o.selected = true
          sel.appendChild(o)
        })
        sel.addEventListener('change', () => onChange(parseInt(sel.value)))
        return sel
      }
      const builtinMonth = cal.querySelector('.flatpickr-monthDropdown-months')
      if (builtinMonth && builtinMonth.parentNode) {
        const monthSel = makeSelect(MONTHS.map((l, i) => ({v: i, l})), instance.currentMonth, (val) => instance.changeMonth(val - instance.currentMonth, true))
        builtinMonth.parentNode.insertBefore(monthSel, builtinMonth.nextSibling)
      }
      const yearWrapper = cal.querySelector('.flatpickr-current-month .numInputWrapper')
      if (yearWrapper && yearWrapper.parentNode) {
        const curY = new Date().getFullYear()
        const yearOpts = []
        for (let y = curY; y <= curY + 10; y++) yearOpts.push({v: y, l: String(y)})
        const yearSel = makeSelect(yearOpts, instance.currentYear, (val) => instance.changeYear(val))
        yearWrapper.parentNode.insertBefore(yearSel, yearWrapper.nextSibling)
      }
      const hourInput = cal.querySelector('input.flatpickr-hour') as HTMLInputElement
      if (hourInput) {
        const hw = hourInput.closest('.numInputWrapper')
        if (hw && hw.parentNode) {
          const hourOpts = []
          for (let h = 0; h < 24; h++) hourOpts.push({v: h, l: String(h).padStart(2,'0')})
          const hourSel = makeSelect(hourOpts, parseInt(hourInput.value) || 0, (val) => {
            hourInput.value = String(val).padStart(2,'0')
            hourInput.dispatchEvent(new Event('input', {bubbles: true}))
          }, 'font-size:20px;min-width:76px;text-align:center;')
          hw.parentNode.insertBefore(hourSel, hw)
        }
      }
      const minInput = cal.querySelector('input.flatpickr-minute') as HTMLInputElement
      if (minInput) {
        const mw = minInput.closest('.numInputWrapper')
        if (mw && mw.parentNode) {
          const curMin = Math.round((parseInt(minInput.value) || 0) / 15) * 15 % 60
          const minSel = makeSelect([{v:0,l:'00'},{v:15,l:'15'},{v:30,l:'30'},{v:45,l:'45'}], curMin, (val) => {
            minInput.value = String(val).padStart(2,'0')
            minInput.dispatchEvent(new Event('input', {bubbles: true}))
          }, 'font-size:20px;min-width:76px;text-align:center;')
          mw.parentNode.insertBefore(minSel, mw)
        }
      }
    } catch (e) { console.error('Flatpickr helper error:', e) }
  }

  const addButtons = (instance: Instance) => {
    if (instance.calendarContainer.querySelector('.flatpickr-custom-btn')) return
    const d = document.createElement('div')
    d.className = 'flatpickr-custom-btn'
    ;['Clear','Today','OK'].forEach(lbl => {
      const b = document.createElement('button')
      b.type = 'button'; b.innerText = lbl
      if (lbl === 'OK') b.className = 'ok'
      b.onclick = () => {
        if (lbl === 'Clear') instance.clear()
        else if (lbl === 'Today') instance.setDate(new Date())
        else instance.close()
      }
      d.appendChild(b)
    })
    instance.calendarContainer.appendChild(d)
  }

  useEffect(() => {
    const config = (defaultDate: string, onValueChange: (d: string, t: string) => void) => ({
      enableTime: true,
      time_24hr: true,
      minuteIncrement: 15,
      dateFormat: "Y-m-d H:i",
      defaultDate: defaultDate,
      onReady: (_: Date[], __: string, instance: Instance) => {
        addButtons(instance)
        buildDropdowns(instance)
      },
      onChange: (dates: Date[], dateStr: string) => {
        if (dates.length > 0) {
          const [d, t] = dateStr.split(' ')
          onValueChange(d, t)
        }
      }
    })

    const entryInstance = entryRef.current ? flatpickr(entryRef.current, config(`${entryDate} ${entryTime}`, (d, t) => { setEntryDate(d); setEntryTime(t); })) : null
    const exitInstance = exitRef.current ? flatpickr(exitRef.current, config(`${exitDate} ${exitTime}`, (d, t) => { setExitDate(d); setExitTime(t); })) : null

    return () => {
      if (entryInstance && typeof entryInstance === 'object' && 'destroy' in entryInstance) (entryInstance as Instance).destroy()
      if (exitInstance && typeof exitInstance === 'object' && 'destroy' in exitInstance) (exitInstance as Instance).destroy()
    }
  }, [])

  const hourlyRate = vehicleType === 'car' ? 4000 : 2000
  const hours = 3
  const totalFee = hourlyRate * hours

  const handleBooking = async () => {
    if (!plateNumber) {
      alert('Please enter your plate number')
      return
    }

    setLoading(true)
    try {
      const entry_datetime = `${entryDate} ${entryTime}:00`
      const exit_datetime = `${exitDate} ${exitTime}:00`

      const response = await fetch('/api/reserve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          plate_number: plateNumber,
          entry_datetime,
          exit_datetime,
          vehicle_type: vehicleType
        })
      })

      const result = await response.json()
      if (result.success) {
        setBookingResult(result.data)
        setSuccess(true)
        // No auto-redirect, let user see the receipt
      } else {
        alert(result.message)
      }
    } catch (error) {
      console.error('Booking failed:', error)
      alert('Failed to connect to server')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-bg-page text-text-main font-manrope pb-24 md:pb-8">
      {/* Top Bar - Enhanced with Navigation */}
      <header className="fixed top-0 left-0 w-full z-50 bg-surface/80 backdrop-blur-md border-b border-border shadow-sm h-16">
        <div className="max-w-7xl mx-auto px-6 h-full flex justify-between items-center">
          {/* Logo */}
          <div className="flex items-center gap-3">
            <Link to="/" className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white shadow-lg shadow-primary/20">
                <ParkingCircle className="h-5 w-5" />
              </div>
              <span className="text-lg font-black tracking-tighter text-text-main uppercase">
                Park<span className="text-primary">Smart</span>
              </span>
            </Link>
          </div>

          {/* Center Navigation Links */}
          <div className="hidden md:flex items-center gap-8 text-[11px] font-black uppercase tracking-widest text-text-muted">
            <Link to="/" className="nav-link-stretching hover:text-primary transition-colors">HOME</Link>
            <Link to="/reserve" className="nav-link-stretching text-primary underline underline-offset-8 decoration-2">RESERVE</Link>
            <Link to="/bookings" className="nav-link-stretching hover:text-primary transition-colors">MY BOOKINGS</Link>
          </div>

          {/* Right Side Actions */}
          <div className="flex items-center gap-4">
            <ThemeToggle />
            <button className="p-2 rounded-full hover:bg-surface-alt transition-colors text-text-muted relative">
              <Bell className="h-5 w-5" />
              <span className="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-surface"></span>
            </button>
            <button 
              onClick={() => navigate('/account')}
              className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden border border-primary/20 hover:border-primary transition-colors active:scale-90"
            >
              <UserIcon className="h-4 w-4 text-primary" />
            </button>
          </div>
        </div>
      </header>

      <main className="pt-24 px-6 max-w-7xl mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* Left Column: Venue Info */}
          <div className="lg:col-span-7 space-y-6">
            <motion.div 
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="bg-surface rounded-2xl overflow-hidden border border-border shadow-sm group"
            >
              <div className="relative h-72 w-full overflow-hidden">
                <img 
                  src="https://images.unsplash.com/photo-1506521781263-d8422e82f27a?auto=format&fit=crop&q=80&w=1200" 
                  alt="Parking Facility" 
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                />
                <div className="absolute top-4 left-4 flex gap-2">
                  <span className="bg-primary text-white px-4 py-1.5 rounded-full text-xs font-bold flex items-center gap-2 shadow-lg backdrop-blur-md bg-primary/90">
                    <CheckCircle className="h-3.5 w-3.5" /> Recommended
                  </span>
                </div>
              </div>
              
              <div className="p-8">
                <div className="flex justify-between items-start mb-6">
                  <div>
                    <h1 className="text-3xl font-black text-text-main tracking-tight">Berserk Store</h1>
                    <div className="flex items-center gap-2 text-text-muted mt-2">
                      <MapPin className="h-4 w-4 text-primary" />
                      <span className="text-sm font-medium">Jl. Terbaik No. 67, Central District</span>
                    </div>
                  </div>
                  <div className="flex flex-col items-end">
                    <div className="flex items-center gap-1.5 bg-yellow-400/10 text-yellow-600 px-3 py-1 rounded-lg border border-yellow-400/20">
                      <Star className="h-4 w-4 fill-current" />
                      <span className="font-bold">4.9</span>
                    </div>
                    <div className="mt-3 text-right">
                      <p className="text-2xl font-black text-primary">Rp {hourlyRate.toLocaleString()}<span className="text-xs font-medium text-text-muted ml-1">/hr</span></p>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-6 py-6 border-y border-border/50">
                  <div className="flex flex-col items-center text-center group/icon">
                    <div className="w-12 h-12 rounded-xl bg-primary/5 flex items-center justify-center mb-2 transition-colors group-hover/icon:bg-primary/10">
                      <ShieldCheck className="h-6 w-6 text-primary" />
                    </div>
                    <span className="text-[10px] font-black text-text-muted uppercase tracking-widest">24/7 Security</span>
                  </div>
                  <div className="flex flex-col items-center text-center group/icon">
                    <div className="w-12 h-12 rounded-xl bg-primary/5 flex items-center justify-center mb-2 transition-colors group-hover/icon:bg-primary/10">
                      <Zap className="h-6 w-6 text-primary" />
                    </div>
                    <span className="text-[10px] font-black text-text-muted uppercase tracking-widest">EV Charging</span>
                  </div>
                  <div className="flex flex-col items-center text-center group/icon">
                    <div className="w-12 h-12 rounded-xl bg-primary/5 flex items-center justify-center mb-2 transition-colors group-hover/icon:bg-primary/10">
                      <Camera className="h-6 w-6 text-primary" />
                    </div>
                    <span className="text-[10px] font-black text-text-muted uppercase tracking-widest">CCTV Active</span>
                  </div>
                </div>
              </div>
            </motion.div>

            {/* Feature Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <motion.div 
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.1 }}
                className="bg-surface p-6 rounded-2xl border border-border shadow-sm flex flex-col justify-between"
              >
                <span className="text-[10px] font-black text-primary uppercase tracking-widest mb-4 block">Live Status</span>
                <div className="flex items-end justify-between">
                  <div>
                    <div className="flex items-center gap-3">
                      <span className="w-3 h-3 bg-green-500 rounded-full animate-pulse shadow-[0_0_12px_rgba(34,197,94,0.5)]"></span>
                      <span className="text-2xl font-black text-text-main">Available</span>
                    </div>
                    <p className="text-xs text-text-muted mt-2 font-medium">24 slots remaining today</p>
                  </div>
                  <Ticket className="h-10 w-10 text-primary/10" />
                </div>
              </motion.div>

              <motion.div 
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.2 }}
                className="bg-surface p-6 rounded-2xl border border-border shadow-sm flex flex-col justify-between"
              >
                <span className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-4 block">Entry Clearance</span>
                <div className="flex items-end justify-between">
                  <div>
                    <div className="flex items-center gap-3">
                      <ArrowUpToLine className="h-6 w-6 text-primary" />
                      <span className="text-2xl font-black text-text-main">2.4m</span>
                    </div>
                    <p className="text-xs text-text-muted mt-2 font-medium">Maximum height limit</p>
                  </div>
                  <ShieldCheck className="h-10 w-10 text-text-muted/10" />
                </div>
              </motion.div>
            </div>
          </div>

          {/* Right Column: Reservation Form */}
          <div className="lg:col-span-5">
            {!success ? (
              <motion.div 
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.3 }}
                className="bg-surface p-8 rounded-3xl border border-border shadow-2xl shadow-primary/5 lg:sticky lg:top-24"
              >
                <h2 className="text-2xl font-black text-text-main mb-8 flex items-center gap-3">
                  Reservation Details
                </h2>
                
                <form className="space-y-8" onSubmit={(e) => { e.preventDefault(); handleBooking(); }}>
                  {/* Vehicle Selection */}
                  <div className="space-y-4">
                    <label className="text-[10px] font-black text-text-muted uppercase tracking-widest ml-1">Vehicle Type</label>
                    <div className="grid grid-cols-2 gap-4">
                      <button 
                        type="button"
                        onClick={() => setVehicleType('car')}
                        className={`flex flex-col items-center justify-center p-6 rounded-2xl border-2 transition-all duration-300 ${
                          vehicleType === 'car' 
                          ? 'border-primary bg-primary/5 text-primary shadow-lg shadow-primary/5' 
                          : 'border-border bg-surface-alt text-text-muted hover:border-primary/50'
                        }`}
                      >
                        <Car className={`h-8 w-8 mb-2 ${vehicleType === 'car' ? 'text-primary' : 'text-text-muted'}`} />
                        <span className="text-sm font-bold">Car</span>
                      </button>
                      <button 
                        type="button"
                        onClick={() => setVehicleType('motorcycle')}
                        className={`flex flex-col items-center justify-center p-6 rounded-2xl border-2 transition-all duration-300 ${
                          vehicleType === 'motorcycle' 
                          ? 'border-primary bg-primary/5 text-primary shadow-lg shadow-primary/5' 
                          : 'border-border bg-surface-alt text-text-muted hover:border-primary/50'
                        }`}
                      >
                        <Bike className={`h-8 w-8 mb-2 ${vehicleType === 'motorcycle' ? 'text-primary' : 'text-text-muted'}`} />
                        <span className="text-sm font-bold">Motorcycle</span>
                      </button>
                    </div>
                  </div>

                  {/* Date/Time Selection */}
                  <div className="grid grid-cols-2 gap-6">
                    <div className="space-y-3">
                      <label className="text-[10px] font-black text-text-muted uppercase tracking-widest ml-1">Entry</label>
                      <div className="bg-surface-alt p-3 rounded-xl border border-border focus-within:border-primary transition-colors cursor-pointer group">
                        <div className="flex flex-col gap-2">
                          <div className="flex items-center gap-2 text-primary">
                            <Calendar className="h-3 w-3" />
                            <input 
                              ref={entryRef}
                              type="text" 
                              defaultValue={`${entryDate} ${entryTime}`}
                              readOnly
                              placeholder="Select entry time"
                              className="bg-transparent border-none text-xs font-bold text-text-main focus:ring-0 w-full p-0 cursor-pointer" 
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="space-y-3">
                      <label className="text-[10px] font-black text-text-muted uppercase tracking-widest ml-1">Exit</label>
                      <div className="bg-surface-alt p-3 rounded-xl border border-border focus-within:border-primary transition-colors cursor-pointer group">
                        <div className="flex flex-col gap-2">
                          <div className="flex items-center gap-2 text-primary">
                            <Calendar className="h-3 w-3" />
                            <input 
                              ref={exitRef}
                              type="text" 
                              defaultValue={`${exitDate} ${exitTime}`}
                              readOnly
                              placeholder="Select exit time"
                              className="bg-transparent border-none text-xs font-bold text-text-main focus:ring-0 w-full p-0 cursor-pointer" 
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* License Plate */}
                  <div className="space-y-3">
                    <label className="text-[10px] font-black text-text-muted uppercase tracking-widest ml-1">License Plate Number</label>
                    <div className="relative group">
                      <div className="absolute left-4 inset-y-0 flex items-center pointer-events-none text-text-muted group-focus-within:text-primary transition-colors">
                        <ShieldCheck className="h-5 w-5" />
                      </div>
                      <input 
                        type="text"
                        value={plateNumber}
                        onChange={(e) => setPlateNumber(e.target.value.toUpperCase())}
                        placeholder="B 1234 XYZ"
                        className="w-full bg-surface-alt p-4 pl-12 rounded-2xl border border-border focus:border-primary focus:ring-4 focus:ring-primary/5 transition-all text-text-main font-black placeholder:text-text-muted/40 placeholder:font-normal"
                      />
                    </div>
                  </div>

                  {/* Price Summary */}
                  <motion.div 
                    whileHover={{ scale: 1.02 }}
                    className="mt-10 p-8 bg-sidebar rounded-3xl text-white shadow-xl shadow-sidebar/20 relative overflow-hidden group"
                  >
                    <div className="absolute top-0 right-0 p-8 opacity-5 transition-transform group-hover:scale-125 group-hover:rotate-12">
                      <Receipt className="h-32 w-32" />
                    </div>
                    <div className="relative z-10">
                      <div className="flex justify-between items-center mb-6 pb-6 border-b border-white/10">
                        <span className="text-white/60 text-xs font-bold uppercase tracking-widest">Rate (Rp {hourlyRate} x {hours}h)</span>
                        <span className="font-black text-lg">Rp {totalFee.toLocaleString()}</span>
                      </div>
                      <div className="flex flex-col">
                        <span className="text-primary text-[10px] font-black uppercase tracking-widest mb-1">Total Estimated Fee</span>
                        <span className="text-4xl font-black">Rp {totalFee.toLocaleString()}</span>
                      </div>
                    </div>
                  </motion.div>

                  <Button 
                    type="submit"
                    disabled={loading}
                    size="lg"
                    className="w-full py-8 text-xl"
                  >
                    {loading ? (
                      <div className="w-6 h-6 border-4 border-black/30 border-t-black rounded-full animate-spin"></div>
                    ) : (
                      'Book Parking Spot'
                    )}
                  </Button>
                </form>
              </motion.div>
            ) : (
              <motion.div 
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                className="bg-surface p-8 rounded-3xl border border-border shadow-2xl lg:sticky lg:top-24 overflow-hidden"
              >
                {/* Receipt UI */}
                <div className="relative">
                  <div className="absolute -top-12 -right-12 w-32 h-32 bg-primary/10 rounded-full blur-3xl"></div>
                  <div className="absolute -bottom-12 -left-12 w-32 h-32 bg-primary/10 rounded-full blur-3xl"></div>
                  
                  <div className="text-center mb-8">
                    <div className="w-20 h-20 bg-green-500/10 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4 border border-green-500/20">
                      <CheckCircle className="h-10 w-10" />
                    </div>
                    <h2 className="text-2xl font-black text-text-main">Booking Successful!</h2>
                    <p className="text-sm text-text-muted mt-1 font-medium">Your spot is reserved at Berserk Store</p>
                  </div>

                  <div className="bg-surface-alt rounded-2xl p-6 border border-border space-y-6 relative">
                    {/* Decorative cutouts */}
                    <div className="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-surface rounded-full border border-border"></div>
                    <div className="absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-surface rounded-full border border-border"></div>
                    
                    <div className="flex justify-between items-center pb-6 border-b border-border/50 border-dashed">
                      <div>
                        <p className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1">Receipt No.</p>
                        <p className="text-sm font-bold text-text-main">#RES-{bookingResult?.reservation_id || '0000'}</p>
                      </div>
                      <div className="text-right">
                        <p className="text-[10px] font-black text-text-muted uppercase tracking-widest mb-1">Date</p>
                        <p className="text-sm font-bold text-text-main">{new Date().toLocaleDateString()}</p>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <div className="flex justify-between">
                        <span className="text-sm text-text-muted">License Plate</span>
                        <span className="text-sm font-black text-primary">{bookingResult?.plate_number}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-text-muted">Vehicle Type</span>
                        <span className="text-sm font-bold capitalize">{bookingResult?.vehicle_type}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-sm text-text-muted">Entry Time</span>
                        <span className="text-sm font-bold">{bookingResult?.entry_datetime}</span>
                      </div>
                    </div>

                    <div className="pt-6 border-t border-border/50 border-dashed">
                      <div className="flex justify-between items-center">
                        <span className="text-sm font-black text-text-main uppercase tracking-widest">Total Paid</span>
                        <span className="text-2xl font-black text-primary">Rp {totalFee.toLocaleString()}</span>
                      </div>
                    </div>
                  </div>

                  <div className="mt-8 space-y-3">
                    <p className="text-center text-[10px] text-text-muted font-bold uppercase tracking-widest px-8">
                      The gate will automatically open when our OCR system detects your plate number.
                    </p>
                    <Button 
                      onClick={() => navigate('/bookings')}
                      className="w-full py-6 text-lg font-black"
                    >
                      Go to My Bookings
                    </Button>
                    <button 
                      onClick={() => window.print()}
                      className="w-full py-4 text-xs font-black text-text-muted hover:text-primary transition-colors flex items-center justify-center gap-2"
                    >
                      <Download className="h-3.5 w-3.5" />
                      Download Receipt
                    </button>
                  </div>
                </div>
              </motion.div>
            )}
          </div>
        </div>
      </main>

      {/* Mobile Bottom Nav */}
      <nav className="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-6 py-4 bg-surface/80 backdrop-blur-xl border-t border-border shadow-[0_-8px_30px_rgba(0,0,0,0.04)] md:hidden">
        <button className="flex flex-col items-center gap-1 text-primary">
          <Search className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Find</span>
        </button>
        <button 
          onClick={() => navigate('/bookings')}
          className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors"
        >
          <Ticket className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Bookings</span>
        </button>
        <button 
          onClick={() => navigate('/account')}
          className="flex flex-col items-center gap-1 text-text-muted hover:text-primary transition-colors"
        >
          <UserIcon className="h-6 w-6" />
          <span className="text-[10px] font-bold uppercase tracking-widest">Account</span>
        </button>
      </nav>
    </div>
  )
}
