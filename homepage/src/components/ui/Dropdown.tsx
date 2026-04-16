import React, { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface DropdownProps {
  label: string;
  items: { label: string; onClick: () => void; icon?: string }[];
  icon?: string;
}

export const Dropdown = ({ label, items, icon }: DropdownProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="relative inline-block text-left" ref={dropdownRef}>
      <motion.button
        whileTap={{ scale: 0.95 }}
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm font-inter text-slate-700 shadow-sm hover:bg-slate-50 transition-all"
      >
        {icon && <i className={`${icon} text-base opacity-60`}></i>}
        <span className="font-semibold">{label}</span>
        <motion.i 
          animate={{ rotate: isOpen ? 180 : 0 }}
          className="fa-solid fa-chevron-down text-[10px] opacity-40 ml-1"
        />
      </motion.button>

      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.95 }}
            animate={{ opacity: 1, y: 4, scale: 1 }}
            exit={{ opacity: 0, y: 8, scale: 0.95 }}
            transition={{ type: "spring", stiffness: 350, damping: 25 }}
            className="absolute left-0 mt-1 w-56 rounded-2xl bg-white/90 backdrop-blur-xl border border-slate-100 shadow-2xl p-2 z-[60] origin-top-left"
          >
            {items.map((item, idx) => (
              <motion.button
                key={idx}
                whileHover={{ x: 4, backgroundColor: 'rgba(241, 245, 249, 1)' }}
                onClick={() => {
                  item.onClick();
                  setIsOpen(false);
                }}
                className="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-inter text-slate-600 hover:text-slate-900 transition-colors text-left"
              >
                {item.icon && <i className={`${item.icon} text-xs opacity-40`}></i>}
                {item.label}
              </motion.button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

interface PopupProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
}

export const Popup = ({ isOpen, onClose, title, children }: PopupProps) => {
  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100]"
          />
          <div className="fixed inset-0 flex items-center justify-center pointer-events-none z-[101]">
            <motion.div
              initial={{ opacity: 0, scale: 0.9, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.9, y: 20 }}
              transition={{ type: "spring", stiffness: 300, damping: 30 }}
              className="w-full max-w-lg bg-white rounded-[32px] shadow-[0_32px_128px_-16px_rgba(0,0,0,0.3)] border border-slate-100 pointer-events-auto overflow-hidden"
            >
              <div className="px-8 pt-8 pb-4 flex items-center justify-between">
                <h3 className="text-xl font-bold font-inter text-slate-900 tracking-tight">{title}</h3>
                <motion.button
                  whileHover={{ rotate: 90, backgroundColor: 'rgba(241, 245, 249, 0.8)' }}
                  whileTap={{ scale: 0.9 }}
                  onClick={onClose}
                  className="w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors"
                >
                  <i className="fa-solid fa-xmark"></i>
                </motion.button>
              </div>
              <div className="px-8 pb-8 font-inter">
                {children}
              </div>
            </motion.div>
          </div>
        </>
      )}
    </AnimatePresence>
  );
};
