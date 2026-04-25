import { useEffect, useState } from 'react'
import { motion } from 'motion/react'
import { endpoints, type DashboardData as BaseDashboardData } from '../lib/api'
import { ThemeToggle } from '../components/ui/ThemeToggle'
import { Download, LayoutDashboard, Car, Bike, History, Map as MapIcon, Wallet, Timer, FireExtinguisher as Fire, Clock, Users, ArrowUpRight, ArrowDownRight, Video } from 'lucide-react'

// Extended type for new fields
interface DashboardData extends BaseDashboardData {
  yesterday_rev?: number
  peak_time?: string
  peak_vol?: number
  peak_dom?: string
  avg_duration_str?: string
  duration_trend?: number
  recent_logs?: any[]
  active_staff?: any[]
}

function fmtIdr(amount: number) {
  return 'Rp ' + amount.toLocaleString('id-ID')
}

function StatCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
      className={`card-premium ${className}`}
    >
      {children}
    </motion.div>
  )
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    endpoints.dashboard()
      .then(setData)
      .catch(console.error)
      .finally(() => setLoading(false))

    // Poll every 30s
    const interval = setInterval(() => {
      endpoints.dashboard().then(setData).catch(() => {})
    }, 30_000)
    return () => clearInterval(interval)
  }, [])

  if (loading) {
    return (
      <div className="p-10 flex items-center justify-center min-h-[400px]">
        <div className="text-center text-text-muted">
          <span className="material-symbols-outlined text-4xl animate-spin block mb-3 text-primary">progress_activity</span>
          <p className="font-inter text-sm font-medium tracking-wide">Memuat dashboard...</p>
        </div>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="p-10">
        <div className="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4">
          <span className="material-symbols-outlined text-red-500">error</span>
          <p className="font-inter font-semibold text-red-700 text-sm">Gagal memuat data dashboard.</p>
        </div>
      </div>
    )
  }

  const carPct = data.car_pct ?? 100
  const motoPct = data.moto_pct ?? 100
  const revTrend = data.yesterday_rev ? ((data.today_rev - data.yesterday_rev) / data.yesterday_rev) * 100 : 100

  return (
    <div className="p-10 max-w-[1440px] mx-auto space-y-6">

      {/* Page header */}
      <motion.div
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4 }}
        className="flex flex-col md:flex-row md:items-center justify-between gap-4"
      >
        <div>
          <h1 className="font-manrope text-2xl font-extrabold text-text-main">Dashboard</h1>
          <p className="text-text-muted text-sm font-inter mt-1">{data.page_subtitle}</p>
        </div>
        <div className="flex items-center gap-3">
          <button className="flex items-center gap-2 bg-surface-alt border border-border px-4 py-2 rounded-xl text-sm font-bold text-text-main hover:bg-border/50 transition-all shadow-sm">
            <Download className="h-4 w-4 text-primary" />
            Export
          </button>
          <ThemeToggle />
        </div>
      </motion.div>

      {/* Alerts */}
      {carPct <= 20 && data.car_total > 0 && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="flex items-center gap-3 bg-red-50 rounded-xl px-5 py-4"
        >
          <span className="material-symbols-outlined text-red-500">warning</span>
          <div>
            <p className="font-inter font-semibold text-red-700 text-sm">Kapasitas Mobil Hampir Penuh!</p>
            <p className="font-inter text-red-500 text-xs">Hanya {data.car_avail} dari {data.car_total} slot tersedia.</p>
          </div>
        </motion.div>
      )}
      {motoPct <= 20 && data.moto_total > 0 && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="flex items-center gap-3 bg-amber-50 rounded-xl px-5 py-4"
        >
          <span className="material-symbols-outlined text-amber-500">warning</span>
          <div>
            <p className="font-inter font-semibold text-amber-700 text-sm">Kapasitas Motor Hampir Penuh!</p>
            <p className="font-inter text-amber-500 text-xs">Hanya {data.moto_avail} dari {data.moto_total} slot tersedia.</p>
          </div>
        </motion.div>
      )}

      {/* Bento Grid Row 1 */}
      <div className="grid grid-cols-12 gap-4">
        {/* Active Vehicles */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col justify-between">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                <Car className="text-primary h-5 w-5" />
              </div>
              <p className="text-sm font-bold text-text-main font-manrope">Kendaraan Aktif</p>
            </div>
          </div>
          <div className="flex items-end justify-between">
            <div className="font-manrope font-extrabold text-5xl text-text-main leading-none">{data.active}</div>
            <p className="text-text-muted text-xs font-inter mb-1">Sedang Parkir</p>
          </div>
        </StatCard>

        {/* Today Revenue */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col justify-between relative overflow-hidden group">
          <div className="absolute -right-16 -top-16 w-32 h-32 bg-primary/20 rounded-full blur-3xl transition-all"></div>
          <div className="flex items-center gap-3 mb-4 relative z-10">
            <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
              <Wallet className="text-primary h-5 w-5" />
            </div>
            <p className="text-sm font-bold text-text-main font-manrope">Pendapatan Hari Ini</p>
          </div>
          <div className="relative z-10 flex flex-col justify-end">
            <div className="font-manrope font-extrabold text-4xl text-primary leading-none mb-2">
              {fmtIdr(data.today_rev)}
            </div>
            <div className="flex items-center gap-2">
              <span className={`flex items-center gap-1 text-xs font-bold ${revTrend >= 0 ? 'text-emerald-500' : 'text-red-500'}`}>
                {revTrend >= 0 ? <ArrowUpRight className="w-3 h-3" /> : <ArrowDownRight className="w-3 h-3" />}
                {Math.abs(revTrend).toFixed(1)}%
              </span>
              <span className="text-text-muted text-xs font-inter">Vs Kemarin</span>
            </div>
          </div>
        </StatCard>

        {/* Slots Available */}
        <div className="col-span-12 lg:col-span-4 flex flex-col gap-4">
          <StatCard className="flex items-center gap-4 flex-1 py-4">
            <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
              <Car className="text-primary h-5 w-5" />
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-2xl font-manrope font-extrabold text-text-main leading-none mb-1">{data.car_avail}</span>
              <span className="text-[11px] font-inter text-text-muted">Slot Mobil</span>
              <div className="w-full bg-surface-alt rounded-full h-1.5 mt-2">
                <div className={`h-1.5 rounded-full ${carPct > 20 ? 'bg-primary' : 'bg-red-500'}`} style={{ width: `${carPct}%` }}></div>
              </div>
            </div>
          </StatCard>
          <StatCard className="flex items-center gap-4 flex-1 py-4">
            <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
              <Bike className="text-primary h-5 w-5" />
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-2xl font-manrope font-extrabold text-text-main leading-none mb-1">{data.moto_avail}</span>
              <span className="text-[11px] font-inter text-text-muted">Slot Motor</span>
              <div className="w-full bg-surface-alt rounded-full h-1.5 mt-2">
                <div className={`h-1.5 rounded-full ${motoPct > 20 ? 'bg-primary' : 'bg-red-500'}`} style={{ width: `${motoPct}%` }}></div>
              </div>
            </div>
          </StatCard>
        </div>
      </div>

      {/* Bento Grid Row 2 */}
      <div className="grid grid-cols-12 gap-4">
        {/* Peak Trend */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col justify-between">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
              <Fire className="text-orange-500 h-5 w-5" />
            </div>
            <p className="text-sm font-bold text-text-main font-manrope">Tren Puncak Hari Ini</p>
          </div>
          <div className="flex flex-col">
            <span className="font-manrope font-extrabold text-3xl text-text-main leading-none mb-1">{data.peak_time || '--:--'}</span>
            <span className="text-text-muted text-xs font-inter mb-4">Waktu Tersibuk</span>
            
            <div className="flex justify-between items-center text-xs font-inter border-t border-border pt-3">
              <span className="text-text-muted">Volume: <strong className="text-text-main">{data.peak_vol || 0} Unit</strong></span>
              <span className="text-text-muted">Dominan: <strong className="text-text-main">{data.peak_dom || 'N/A'}</strong></span>
            </div>
          </div>
        </StatCard>

        {/* Avg Duration */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col justify-between">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
              <Clock className="text-blue-500 h-5 w-5" />
            </div>
            <p className="text-sm font-bold text-text-main font-manrope">Rata-rata Durasi</p>
          </div>
          <div className="flex flex-col">
            <span className="font-manrope font-extrabold text-4xl text-text-main leading-none mb-2">{data.avg_duration_str || '0m'}</span>
            <span className="text-text-muted text-xs font-inter mb-3">Per Sesi Parkir</span>
            
            <div className="flex items-center gap-2">
              <span className={`flex items-center gap-1 text-xs font-bold ${(data.duration_trend || 0) >= 0 ? 'text-emerald-500' : 'text-blue-500'}`}>
                {(data.duration_trend || 0) >= 0 ? <ArrowUpRight className="w-3 h-3" /> : <ArrowDownRight className="w-3 h-3" />}
                {Math.abs(data.duration_trend || 0).toFixed(1)}%
              </span>
              <span className="text-text-muted text-xs font-inter">Vs Bulan Lalu</span>
            </div>
          </div>
        </StatCard>

        {/* Live CCTV Dummy */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col justify-between overflow-hidden p-0">
          <div className="flex items-center justify-between p-4 pb-2">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                <Video className="text-red-500 h-4 w-4" />
              </div>
              <p className="text-sm font-bold text-text-main font-manrope">Live Gate View</p>
            </div>
            <span className="flex items-center gap-1.5 px-2 py-1 rounded-full bg-red-500/10 text-red-500 text-[10px] font-bold uppercase tracking-widest">
              <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
              Live
            </span>
          </div>
          <div className="relative w-full h-32 bg-slate-900 mt-2">
            <div className="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1506521781263-d8422e82f27a?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-40 grayscale-[30%] mix-blend-luminosity"></div>
            <div className="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-transparent h-full w-full animate-[scan_3s_ease-in-out_infinite] opacity-20 pointer-events-none"></div>
            <div className="absolute bottom-2 left-2 px-1.5 py-0.5 bg-black/60 rounded text-[8px] font-mono text-white/80">CAM_01_ENTRY</div>
          </div>
        </StatCard>
      </div>

      {/* Bento Grid Row 3 */}
      <div className="grid grid-cols-12 gap-4">
        {/* Recent Activity */}
        <StatCard className="col-span-12 lg:col-span-8 flex flex-col">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <History className="text-primary h-5 w-5" />
              <p className="text-sm font-bold text-text-main font-manrope">Log Aktivitas Terbaru</p>
            </div>
            <a href="/scan-log" className="text-xs text-primary hover:underline font-inter">Lihat Semua</a>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-left font-inter border-collapse">
              <thead>
                <tr className="border-b border-border">
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase">Tipe</th>
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase">Plat / Kode</th>
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase text-right">Masuk</th>
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase text-right">Keluar</th>
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase text-right">Biaya</th>
                  <th className="py-3 px-2 text-[11px] font-semibold text-text-muted uppercase text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {data.recent_logs?.map((log: any, idx: number) => (
                  <tr key={idx} className="hover:bg-surface-alt/30 transition-colors">
                    <td className="py-2 px-2">
                      <div className="w-8 h-8 rounded-lg bg-surface-alt flex items-center justify-center">
                        {log.vehicle_type === 'car' ? <Car className="w-4 h-4 text-text-muted" /> : <Bike className="w-4 h-4 text-text-muted" />}
                      </div>
                    </td>
                    <td className="py-2 px-2">
                      <div className="font-manrope font-bold text-sm text-text-main">{log.plate_number || '--'}</div>
                      <div className="text-[10px] text-text-muted">{log.code}</div>
                    </td>
                    <td className="py-2 px-2 text-right text-sm font-manrope font-medium text-text-main">
                      {log.entry_time ? new Date(log.entry_time).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'}) : '--:--'}
                    </td>
                    <td className="py-2 px-2 text-right text-sm font-manrope font-medium text-text-main">
                      {log.exit_time ? new Date(log.exit_time).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'}) : '--:--'}
                    </td>
                    <td className="py-2 px-2 text-right text-sm font-manrope font-medium text-primary">
                      {log.total_fee ? fmtIdr(parseFloat(log.total_fee)) : 'Rp 0'}
                    </td>
                    <td className="py-2 px-2 text-center">
                      {log.log_type === 'reservation' ? (
                        <span className="px-2 py-0.5 rounded-full bg-violet-500/10 text-violet-500 text-[10px] font-bold">Reserved</span>
                      ) : !log.exit_time ? (
                        <span className="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-bold">Parked</span>
                      ) : (
                        <span className="px-2 py-0.5 rounded-full bg-slate-500/10 text-slate-500 text-[10px] font-bold">Departed</span>
                      )}
                    </td>
                  </tr>
                ))}
                {(!data.recent_logs || data.recent_logs.length === 0) && (
                  <tr>
                    <td colSpan={6} className="py-8 text-center text-text-muted text-sm font-inter">Belum ada aktivitas.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </StatCard>

        {/* Active Duty */}
        <StatCard className="col-span-12 lg:col-span-4 flex flex-col">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <Users className="text-primary h-5 w-5" />
              <p className="text-sm font-bold text-text-main font-manrope">Petugas Aktif</p>
            </div>
            <span className="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-bold">{data.active_staff?.length || 0} Online</span>
          </div>
          
          <div className="space-y-3 flex-1 overflow-y-auto pr-1">
            {data.active_staff?.map((staff: any, i: number) => (
              <div key={i} className="flex items-center justify-between p-3 rounded-xl border border-border bg-surface-alt">
                <div className="flex items-center gap-3">
                  <div className="relative">
                    <div className="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs font-manrope">
                      {staff.full_name.charAt(0).toUpperCase()}
                    </div>
                    <div className="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-surface"></div>
                  </div>
                  <div>
                    <p className="text-sm font-bold text-text-main leading-tight">{staff.full_name}</p>
                    <p className="text-[10px] text-text-muted uppercase tracking-wider">{staff.shift} Shift</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="text-xs text-text-muted font-mono">{new Date(staff.check_in_time).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})}</p>
                </div>
              </div>
            ))}
            {(!data.active_staff || data.active_staff.length === 0) && (
              <div className="flex flex-col items-center justify-center h-32 text-text-muted">
                <Users className="h-8 w-8 opacity-20 mb-2" />
                <p className="text-xs font-inter">Tidak ada petugas aktif.</p>
              </div>
            )}
          </div>
        </StatCard>
      </div>

      <style>{`
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes scan { 0% { transform: translateY(-100%); } 100% { transform: translateY(400%); } }
      `}</style>
    </div>
  )
}

