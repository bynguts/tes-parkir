import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface SidebarItemProps {
  icon: string;
  label: string;
  isActive?: boolean;
  href: string;
  alert?: boolean;
  isLogout?: boolean;
}

const NavItem = ({ icon, label, isActive, href, alert, isLogout }: SidebarItemProps) => {
  return (
    <motion.li
      whileHover="hover"
      whileTap={{ scale: 0.96 }}
      className="relative list-none"
    >
      <a
        href={href}
        className={`
          group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-inter 
          transition-all duration-300 relative
          ${isActive 
            ? 'bg-emerald-50 text-emerald-700 font-semibold' 
            : isLogout
              ? 'text-slate-500 hover:bg-red-50 hover:text-red-600'
              : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900'}
        `}
      >
        {/* Discord-style Active Indicator */}
        {isActive && (
          <motion.div
            layoutId="active-pill"
            className="absolute -left-3 w-1.5 h-6 bg-emerald-50 rounded-r-full"
            initial={{ opacity: 0, x: -5 }}
            animate={{ opacity: 1, x: 0 }}
          />
        )}

        <motion.i
          variants={{
            hover: { x: 4, scale: 1.1 }
          }}
          transition={{ type: "spring", stiffness: 400, damping: 20 }}
          className={`${icon} text-lg ${
            isActive ? 'text-emerald-500' : isLogout ? 'group-hover:text-red-500' : 'text-slate-400 group-hover:text-slate-600'
          }`}
        />
        
        <span className="flex-1 truncate">{label}</span>

        {alert && (
          <span className="w-1.5 h-1.5 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.5)]" />
        )}
      </a>
    </motion.li>
  );
};

interface SidebarProps {
  username: string;
  role: string;
  currentPath?: string;
}

export const Sidebar = ({ username, role, currentPath = "index.php" }: SidebarProps) => {
  const isSuper = role === 'superadmin' || role === 'admin';

  return (
    <aside className="fixed top-0 left-0 h-screen w-64 bg-white/80 backdrop-blur-xl border-r border-slate-100 flex flex-col z-40">
      {/* Brand Section */}
      <div className="px-6 h-20 border-b border-slate-100 flex items-center gap-3">
        <motion.div 
          whileHover={{ rotate: 90 }}
          className="w-10 h-10 bg-slate-900 rounded-xl flex items-center justify-center shadow-lg"
        >
          <i className="fa-solid fa-square-p text-white text-xl"></i>
        </motion.div>
        <div>
          <h1 className="font-inter font-bold text-slate-900 text-base leading-tight tracking-tight">
            Smart<span className="text-slate-400">Parking</span>
          </h1>
          <div className="text-[10px] text-slate-400 font-inter font-bold uppercase tracking-[0.2em] leading-tight">
            Enterprise
          </div>
        </div>
      </div>

      {/* Navigation section */}
      <nav className="flex-1 overflow-y-auto px-4 py-6 space-y-8 custom-scrollbar">
        {/* Operations */}
        <div>
          <h2 className="px-3 mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Operations</h2>
          <ul className="space-y-1">
            <NavItem icon="fa-solid fa-grip" label="Dashboard" href="index.php" isActive={currentPath === 'index.php'} />
            <NavItem icon="fa-solid fa-door-open" label="Smart Gate" href="modules/operations/gate_simulator.php" isActive={currentPath.includes('gate_simulator')} />
            <NavItem icon="fa-solid fa-car" label="Active Vehicles" href="modules/operations/active_vehicles.php" isActive={currentPath.includes('active_vehicles')} alert />
            <NavItem icon="fa-solid fa-calendar-check" label="Reservations" href="modules/operations/reservation.php" isActive={currentPath.includes('reservation')} />
            <NavItem icon="fa-solid fa-receipt" label="Scan Log" href="modules/operations/scan_log.php" isActive={currentPath.includes('scan_log')} />
          </ul>
        </div>

        {/* Reports */}
        <div>
          <h2 className="px-3 mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Analytics</h2>
          <ul className="space-y-1">
            <NavItem icon="fa-solid fa-chart-simple" label="Revenue" href="modules/reports/revenue.php" isActive={currentPath.includes('revenue')} />
            <NavItem icon="fa-solid fa-map-location-dot" label="Slot Map" href="modules/reports/slot_map.php" isActive={currentPath.includes('slot_map')} />
          </ul>
        </div>

        {/* Admin section */}
        {isSuper && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <h2 className="px-3 mb-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Management</h2>
            <ul className="space-y-1">
              <NavItem icon="fa-solid fa-table-list" label="Slots" href="modules/admin/slots.php" isActive={currentPath.includes('slots')} />
              <NavItem icon="fa-solid fa-money-bill-wave" label="Rates" href="modules/admin/rates.php" isActive={currentPath.includes('rates')} />
              <NavItem icon="fa-solid fa-headset" label="Operators" href="modules/admin/operators.php" isActive={currentPath.includes('operators')} />
              {role === 'superadmin' && (
                <NavItem icon="fa-solid fa-users" label="Users" href="modules/admin/users.php" isActive={currentPath.includes('users')} />
              )}
            </ul>
          </motion.div>
        )}
      </nav>

      {/* Footer Profile Section */}
      <div className="p-4 border-t border-slate-100 bg-white/40">
        <div className="flex items-center gap-3 p-2 mb-3 bg-slate-50/50 rounded-2xl border border-slate-100">
          <div className="relative">
            <div className="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white font-inter font-bold shadow-md">
              {username.charAt(0).toUpperCase()}
            </div>
            <div className="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-50 border-2 border-white rounded-full shadow-sm" />
          </div>
          <div className="min-w-0">
            <div className="text-sm font-inter font-bold text-slate-800 truncate">{username}</div>
            <div className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">{role}</div>
          </div>
        </div>
        
        <NavItem 
          icon="fa-solid fa-right-from-bracket" 
          label="Exit Session" 
          href="logout.php" 
          isLogout 
        />
      </div>
    </aside>
  );
};
