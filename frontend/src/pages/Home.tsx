import { motion, useScroll, useTransform, useSpring, useInView, Variants } from 'framer-motion'
import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { FloatingPaths } from '../components/ui/background-paths'
import { useAuth } from '../hooks/useAuth'
import {
  ShieldCheck,
  LayoutDashboard,
  Scan,
  Map as MapIcon,
  TrendingUp,
  ChevronRight,
  ArrowRight,
  ParkingCircle,
  Clock,
  Database,
  Smartphone,
  Cpu,
  ChevronLeft,
  Search,
  Globe
} from 'lucide-react'
import { ThemeToggle } from '../components/ui/ThemeToggle'
import { Button } from '../components/ui/button'

function Counter({ value, duration = 2 }: { value: number | string, duration?: number }) {
  const ref = useRef<HTMLSpanElement>(null)
  const isInView = useInView(ref, { once: true, margin: "-100px" })
  const [displayValue, setDisplayValue] = useState(0)

  useEffect(() => {
    if (!isInView) return
    
    const target = typeof value === 'string' ? parseFloat(value.replace(/[^0-9.]/g, '')) : value
    const suffix = typeof value === 'string' ? value.replace(/[0-9.]/g, '') : ''
    const prefix = typeof value === 'string' && value.startsWith('+') ? '+' : ''

    let start = 0
    const end = target
    const increment = end / (60 * duration)
    
    const timer = setInterval(() => {
      start += increment
      if (start >= end) {
        setDisplayValue(end)
        clearInterval(timer)
      } else {
        setDisplayValue(Math.floor(start))
      }
    }, 1000 / 60)

    return () => clearInterval(timer)
  }, [value, duration, isInView])

  return (
    <span ref={ref}>
      {typeof value === 'string' && value.startsWith('+') ? '+' : ''}
      {displayValue.toLocaleString()}
      {typeof value === 'string' ? value.replace(/[0-9.+]/g, '') : ''}
    </span>
  )
}

const FEATURES = [
  {
    icon: <Scan className="h-6 w-6" />,
    label: 'Smart Gate',
    desc: 'Simulasi gerbang otomatis dengan validasi barcode real-time dan kontrol presisi.',
    to: '/gate',
    className: 'md:col-span-2 md:row-span-2 bg-indigo-500/5 border-indigo-500/20'
  },
  {
    icon: <MapIcon className="h-6 w-6" />,
    label: 'Live Slot Map',
    desc: 'Visualisasi ketersediaan slot parkir di berbagai zona secara langsung.',
    to: '/reports/slot-map',
    className: 'bg-amber-500/5 border-amber-500/20'
  },
  {
    icon: <TrendingUp className="h-6 w-6" />,
    label: 'Revenue Analytics',
    desc: 'Laporan finansial detail dengan tren harian dan ekspor data.',
    to: '/reports/revenue',
    className: 'bg-blue-500/5 border-blue-500/20'
  },
  {
    icon: <Smartphone className="h-6 w-6" />,
    label: 'Mobile Ready',
    desc: 'Akses dashboard dan kontrol gate langsung dari perangkat mobile operator.',
    to: '#',
    className: 'bg-emerald-500/5 border-emerald-500/20'
  },
  {
    icon: <Cpu className="h-6 w-6" />,
    label: 'AI Automation',
    desc: 'Optimasi alokasi slot menggunakan algoritma pintar untuk efisiensi maksimal.',
    to: '#',
    className: 'bg-purple-500/5 border-purple-500/20'
  },
]

import { Win98Button } from '../components/ui/Win98Button'

export default function HomePage() {
  const { user } = useAuth()
  const [isScrolled, setIsScrolled] = useState(false)
  const { scrollY } = useScroll()
  
  // Parallax for Hero
  const y1 = useTransform(scrollY, [0, 500], [0, 200])
  const y2 = useTransform(scrollY, [0, 500], [0, -150])
  const opacity = useTransform(scrollY, [0, 300], [1, 0])
  const scale = useTransform(scrollY, [0, 500], [1, 1.1])

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 50)
    }
    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  const containerVariants: Variants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.3
      }
    }
  }

  const itemVariants: Variants = {
    hidden: { y: 20, opacity: 0 },
    visible: {
      y: 0,
      opacity: 1,
      transition: { duration: 0.8, ease: [0.16, 1, 0.3, 1] }
    }
  }

  return (
    <div className="min-h-screen bg-bg-page text-text-main font-inter">
      
      {/* Top Header / Nav (Following Secure Parking Style) */}
      <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
        isScrolled 
          ? "bg-white/80 dark:bg-sidebar/80 backdrop-blur-xl h-16 border-b border-border shadow-lg" 
          : "bg-transparent h-24 border-b border-white/10"
      } px-6`}>
        <div className="max-w-7xl mx-auto flex items-center justify-between h-full">
          <div className="flex items-center gap-2">
             <motion.div 
               whileHover={{ scale: 1.05 }}
               whileTap={{ scale: 0.95 }}
               className="flex items-center bg-primary px-3 py-2 rounded-sm text-white cursor-pointer shadow-lg shadow-primary/20"
             >
                <span className="font-black text-xl italic tracking-tighter">smart</span>
                <span className="font-light text-xl border-l border-white/30 ml-2 pl-2">P</span>
             </motion.div>
          </div>

          <div className={`hidden lg:flex items-center gap-8 text-[11px] font-black uppercase tracking-widest transition-colors duration-500 ${
            isScrolled ? "text-text-main dark:text-white" : "text-white/70"
          }`}>
            <Link to="/" className={`nav-link-stretching ${isScrolled ? "text-primary" : "text-white"}`}>HOME</Link>
            <Link to="/reserve" className={`nav-link-stretching hover:text-primary ${!isScrolled && "hover:text-white"}`}>RESERVE</Link>
            <a href="#" className={`nav-link-stretching hover:text-primary ${!isScrolled && "hover:text-white"}`}>TENTANG KAMI</a>
            <div className={`nav-link-stretching flex items-center cursor-pointer hover:text-primary ${!isScrolled && "hover:text-white"}`}>
              INFORMASI
            </div>
            <a href="#" className={`nav-link-stretching hover:text-primary ${!isScrolled && "hover:text-white"}`}>KONTAK</a>
            <div className={`flex items-center gap-2 border-l pl-8 ml-4 transition-colors ${
              isScrolled ? "text-text-main dark:text-white border-border/50" : "text-white border-white/20"
            }`}>
               <Globe className={`h-4 w-4 ${isScrolled ? "text-primary" : "text-white"}`} />
               <span>INDONESIA</span>
            </div>
          </div>

          <div className="flex items-center gap-4">
             <ThemeToggle />
             <Link to={user ? "/dashboard" : "/login"}>
                <Button 
                  variant="default" 
                  size="sm" 
                  className={`font-black tracking-widest text-[10px] uppercase border-black ${
                    !isScrolled && !user && "bg-white text-black border-transparent shadow-none hover:bg-white/90"
                  }`}
                >
                   {user ? 'DASHBOARD' : 'SIGN IN'}
                </Button>
             </Link>
          </div>
        </div>
      </nav>

      {/* Hero Section with Real Background Image */}
      <section className="relative h-[90vh] flex items-center overflow-hidden">
        {/* Real Parking Image Background with Parallax */}
        <motion.div style={{ y: y1, scale }} className="absolute inset-0 z-0">
          <img 
            src="https://images.unsplash.com/photo-1590674899484-d5640e854abe?auto=format&fit=crop&q=80&w=2000" 
            alt="Parking Gate" 
            className="w-full h-full object-cover"
          />
          {/* Dark Overlay for Text Readability */}
          <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent" />
        </motion.div>

        <div className="relative z-10 max-w-7xl mx-auto px-10 w-full">
           <motion.div
             style={{ y: y2, opacity }}
             initial="hidden"
             animate="visible"
             variants={containerVariants}
             className="max-w-3xl"
           >
              <motion.h3 variants={itemVariants} className="text-primary text-2xl md:text-3xl font-black mb-0 italic tracking-tight">Indonesia</motion.h3>
              <motion.h1 variants={itemVariants} className="text-white text-7xl md:text-[120px] font-black leading-[0.85] tracking-tighter mb-8 drop-shadow-2xl">
                 secure <br />
                 parking<span className="text-primary">.</span>
              </motion.h1>
              <motion.div variants={itemVariants} className="flex items-center gap-4 text-white font-black tracking-[0.3em] uppercase text-sm md:text-lg opacity-80 mb-12">
                 <div className="w-12 h-[2px] bg-primary" />
                 NO PARKING WORRIES
              </motion.div>
              
              <motion.div variants={itemVariants} className="flex gap-4">
                 <Button className="group relative overflow-hidden bg-primary hover:bg-primary/90 text-white px-10 py-7 rounded-sm text-lg font-black italic shadow-2xl transition-all duration-300 hover:translate-y-[-4px] active:translate-y-[0px]">
                    <span className="relative z-10">PELAJARI LEBIH LANJUT</span>
                    <div className="absolute inset-0 bg-white/20 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700" />
                 </Button>
              </motion.div>
           </motion.div>
        </div>

        {/* Prev/Next Sliders UI (Aesthetic Only) */}
        <div className="absolute inset-y-0 left-0 w-20 hidden md:flex items-center justify-center">
           <div className="rotate-90 text-[10px] font-black tracking-[0.5em] text-white/40 uppercase">PREV</div>
        </div>
        <div className="absolute inset-y-0 right-0 w-20 hidden md:flex items-center justify-center">
           <div className="rotate-90 text-[10px] font-black tracking-[0.5em] text-white/40 uppercase">NEXT</div>
        </div>
      </section>

      {/* Blue Banner Section (Following Reference) */}
      <section className="bg-primary py-16 px-10 relative z-20 -mt-10 max-w-5xl mx-auto rounded-sm shadow-2xl shadow-primary/20 grid grid-cols-1 md:grid-cols-3 gap-10">
         <div className="flex flex-col gap-4 text-white">
            <h2 className="text-3xl font-black italic leading-tight">Parkir Aman & Mudah</h2>
            <div className="w-10 h-1 bg-white/30" />
         </div>
         <div className="col-span-2 grid grid-cols-3 gap-6">
            <motion.div 
               whileHover={{ y: -5, scale: 1.02, backgroundColor: "rgba(255, 255, 255, 0.2)" }}
               whileTap={{ scale: 0.98 }}
               className="bg-white/10 backdrop-blur-md p-6 rounded-sm flex flex-center flex-col items-center justify-center group transition-all cursor-pointer border border-white/5"
            >
               <ParkingCircle className="h-8 w-8 text-white mb-2 group-hover:rotate-12 transition-transform" />
               <span className="text-[10px] font-black text-white/80 uppercase tracking-widest">Find Spot</span>
            </motion.div>
            <motion.div 
               whileHover={{ y: -5, scale: 1.02, backgroundColor: "rgba(255, 255, 255, 0.2)" }}
               whileTap={{ scale: 0.98 }}
               className="bg-white/10 backdrop-blur-md p-6 rounded-sm flex flex-center flex-col items-center justify-center group transition-all cursor-pointer border border-white/5"
            >
               <Scan className="h-8 w-8 text-white mb-2 group-hover:rotate-12 transition-transform" />
               <span className="text-[10px] font-black text-white/80 uppercase tracking-widest">Auto Gate</span>
            </motion.div>
            <motion.div 
               whileHover={{ y: -5, scale: 1.02, backgroundColor: "rgba(255, 255, 255, 0.2)" }}
               whileTap={{ scale: 0.98 }}
               className="bg-white/10 backdrop-blur-md p-6 rounded-sm flex flex-center flex-col items-center justify-center group transition-all cursor-pointer border border-white/5"
            >
               <ShieldCheck className="h-8 w-8 text-white mb-2 group-hover:rotate-12 transition-transform" />
               <span className="text-[10px] font-black text-white/80 uppercase tracking-widest">Secure</span>
            </motion.div>
         </div>
      </section>

      {/* Animation Section BELOW the Hero Section */}
      <section id="features" className="relative py-40 px-6 overflow-hidden">
        {/* The Animation Background - placed behind features */}
        <div className="absolute inset-0 pointer-events-none z-0 opacity-40 dark:opacity-20">
           <FloatingPaths position={1} />
           <FloatingPaths position={-1} />
        </div>

        <div className="relative z-10 max-w-7xl mx-auto">
          <div className="flex flex-col items-center text-center mb-20">
             <h4 className="text-primary font-black uppercase tracking-[0.3em] text-xs mb-4">Integrasi Teknologi</h4>
             <h2 className="text-4xl md:text-6xl font-black text-text-main tracking-tighter">Solusi Parkir Cerdas.</h2>
             <div className="w-20 h-1 bg-primary mt-6" />
          </div>

          <motion.div 
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-100px" }}
            variants={containerVariants}
            className="grid grid-cols-1 md:grid-cols-3 gap-6"
          >
            {FEATURES.map((f, i) => (
              <motion.div
                key={i}
                variants={itemVariants}
                whileHover={{ y: -10, transition: { duration: 0.3 } }}
                className={`group relative p-10 rounded-sm border border-border/50 overflow-hidden bg-surface/50 backdrop-blur-xl shadow-xl flex flex-col justify-between hover:border-primary/40 transition-all duration-500 ${f.className}`}
              >
                <div className="relative z-10">
                  <div className="w-14 h-14 rounded-sm bg-primary flex items-center justify-center text-white mb-8 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-500 shadow-lg shadow-primary/30">
                    {f.icon}
                  </div>
                  <h3 className="text-2xl font-black mb-4 text-text-main italic">{f.label}</h3>
                  <p className="text-sm font-medium text-text-muted leading-relaxed">{f.desc}</p>
                </div>
                
                <Link to={f.to} className="relative z-10 mt-10 inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-primary group-hover:gap-4 transition-all">
                   LIHAT DETAIL <ArrowRight className="h-4 w-4" />
                </Link>

                <div className="absolute bottom-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                   {f.icon}
                </div>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* Stats / Counter Section */}
      <section className="bg-sidebar py-20 px-6 relative overflow-hidden">
         <div className="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-12 relative z-10">
            <div className="text-center">
               <div className="text-white text-5xl font-black mb-2 italic">
                 <Counter value="250+" />
               </div>
               <div className="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]">Enterprise Sites</div>
            </div>
            <div className="text-center">
               <div className="text-white text-5xl font-black mb-2 italic">
                 <Counter value="1.2M" />
               </div>
               <div className="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]">Monthly Vehicles</div>
            </div>
            <div className="text-center">
               <div className="text-white text-5xl font-black mb-2 italic">
                 <Counter value="99.9%" />
               </div>
               <div className="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]">System Uptime</div>
            </div>
            <div className="text-center">
               <div className="text-white text-5xl font-black mb-2 italic">
                 <Counter value="15K+" />
               </div>
               <div className="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]">Managed Slots</div>
            </div>
         </div>
      </section>

      {/* Footer (Following Secure Parking Style) */}
      <footer className="bg-white dark:bg-sidebar pt-24 pb-12 border-t border-border">
        <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-20">
          <div>
             <div className="flex items-center bg-primary px-3 py-2 rounded-sm text-white w-fit mb-8">
                <span className="font-black text-xl italic tracking-tighter">smart</span>
                <span className="font-light text-xl border-l border-white/30 ml-2 pl-2">P</span>
             </div>
             <p className="text-text-muted text-sm leading-relaxed font-medium">
                Penyedia solusi manajemen perparkiran terpadu dengan standar keamanan enterprise.
             </p>
          </div>
          <div>
            <h4 className="font-black text-[11px] uppercase tracking-widest text-text-main mb-8 border-b-2 border-primary w-fit pb-2">LAYANAN</h4>
            <ul className="space-y-4 text-[12px] font-black text-text-muted uppercase tracking-wider">
              <li><a href="#" className="hover:text-primary transition-colors">On-Street Parking</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Off-Street Parking</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Valet Service</a></li>
            </ul>
          </div>
          <div>
            <h4 className="font-black text-[11px] uppercase tracking-widest text-text-main mb-8 border-b-2 border-primary w-fit pb-2">PERUSAHAAN</h4>
            <ul className="space-y-4 text-[12px] font-black text-text-muted uppercase tracking-wider">
              <li><a href="#" className="hover:text-primary transition-colors">Tentang Kami</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Visi & Misi</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Struktur Organisasi</a></li>
            </ul>
          </div>
          <div>
            <h4 className="font-black text-[11px] uppercase tracking-widest text-text-main mb-8 border-b-2 border-primary w-fit pb-2">HUBUNGI KAMI</h4>
            <div className="text-sm font-medium text-text-muted leading-loose">
               Jl. Digital Innovation No. 88 <br />
               Jakarta, Indonesia <br />
               T: +62 21 555 1234 <br />
               E: info@smartparking.co.id
            </div>
          </div>
        </div>
        
        <div className="max-w-7xl mx-auto px-6 pt-12 border-t border-border flex flex-col md:flex-row justify-between items-center gap-6">
          <p className="text-[10px] font-black text-text-muted uppercase tracking-[0.1em]">
            © 2026 SMARTPARKING ENTERPRISE. ALL RIGHTS RESERVED.
          </p>
          <div className="flex items-center gap-8 text-[10px] font-black text-text-muted uppercase tracking-[0.1em]">
             <a href="#" className="hover:text-primary transition-colors">PRIVACY POLICY</a>
             <a href="#" className="hover:text-primary transition-colors">TERMS OF SERVICE</a>
          </div>
        </div>
      </footer>
    </div>
  )
}
