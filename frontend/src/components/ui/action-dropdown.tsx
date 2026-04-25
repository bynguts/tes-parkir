"use client";

import { useState, useRef, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { ChevronDown, Check } from "lucide-react";
import { cn } from "@/lib/utils";

// Identical variants to the ActionSearchBar for the staggered effect
const containerVariants = {
    hidden: { opacity: 0, height: 0 },
    show: {
        opacity: 1,
        height: "auto",
        transition: {
            height: {
                duration: 0.4,
            },
            staggerChildren: 0.1,
        },
    },
    exit: {
        opacity: 0,
        height: 0,
        transition: {
            height: {
                duration: 0.3,
            },
            opacity: {
                duration: 0.2,
            },
        },
    },
};

const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    show: {
        opacity: 1,
        y: 0,
        transition: {
            duration: 0.3,
        },
    },
    exit: {
        opacity: 0,
        y: -10,
        transition: {
            duration: 0.2,
        },
    },
};

export interface DropdownOption {
    id: string;
    label: string;
    icon: React.ReactNode;
    description?: string;
    short?: string;
}

interface ActionDropdownProps {
    options: DropdownOption[];
    value: string;
    onChange: (value: string) => void;
    label?: string;
}

export function ActionDropdown({ options, value, onChange, label }: ActionDropdownProps) {
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    
    const selectedOption = options.find(opt => opt.id === value) || options[0];

    // Close when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    return (
        <div className="relative w-full" ref={containerRef}>
            {label && (
                <label className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 block px-1">
                    {label}
                </label>
            )}
            
            {/* Trigger Button */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={cn(
                    "w-full flex items-center justify-between px-4 py-3.5 bg-white border border-slate-200 rounded-xl transition-all duration-200 shadow-sm",
                    isOpen ? "ring-2 ring-red-500/20 border-red-500" : "hover:bg-slate-50"
                )}
            >
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-500 border border-slate-100">
                        {selectedOption.icon}
                    </div>
                    <div className="text-left leading-tight">
                        <div className="text-sm font-bold text-slate-900">{selectedOption.label}</div>
                        {selectedOption.description && (
                            <div className="text-[10px] text-slate-400 font-normal uppercase tracking-tighter">{selectedOption.description}</div>
                        )}
                    </div>
                </div>
                <ChevronDown className={cn("h-4 w-4 text-slate-400 transition-transform duration-200", isOpen && "rotate-180")} />
            </button>

            {/* Animated Dropdown Menu with Staggered Items */}
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        variants={containerVariants}
                        initial="hidden"
                        animate="show"
                        exit="exit"
                        className="absolute z-50 w-full mt-2 bg-white/95 backdrop-blur-md border border-slate-200 rounded-2xl shadow-2xl overflow-hidden p-1.5"
                    >
                        <motion.ul>
                            {options.map((option) => (
                                <motion.li
                                    key={option.id}
                                    variants={itemVariants}
                                    layout
                                    onClick={() => {
                                        onChange(option.id);
                                        setIsOpen(false);
                                    }}
                                    className={cn(
                                        "flex items-center justify-between px-3 py-2.5 rounded-xl cursor-pointer transition-all group mb-1 last:mb-0",
                                        value === option.id ? "bg-slate-900 text-white" : "hover:bg-slate-100 text-slate-700"
                                    )}
                                >
                                    <div className="flex items-center gap-3 text-inherit">
                                        <span className={cn("transition-colors", value === option.id ? "text-white" : "text-slate-400 group-hover:text-slate-900")}>
                                            {option.icon}
                                        </span>
                                        <div className="leading-tight">
                                            <div className="text-sm font-bold">{option.label}</div>
                                            {option.description && (
                                                <div className={cn("text-[10px] opacity-60 font-medium")}>
                                                    {option.description}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {option.short && (
                                            <span className="text-[10px] font-mono opacity-30 group-hover:opacity-100 transition-opacity">
                                                {option.short}
                                            </span>
                                        )}
                                        {value === option.id && <Check className="h-4 w-4 text-emerald-400" />}
                                    </div>
                                </motion.li>
                            ))}
                        </motion.ul>
                        <div className="mt-1 px-3 py-2 border-t border-slate-100">
                            <div className="flex items-center justify-between text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                <span>Security Level</span>
                                <span>Action Required</span>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
