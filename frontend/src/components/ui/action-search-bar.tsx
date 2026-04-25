"use client";

import { useState, useEffect } from "react";
import { Input } from "../ui/input";
import { motion, AnimatePresence } from "motion/react";
import {
    ChevronDown,
    Check
} from "lucide-react";

function useDebounce<T>(value: T, delay: number = 500): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(timer);
        };
    }, [value, delay]);

    return debouncedValue;
}

export interface Action {
    id: string;
    label: string;
    icon: React.ReactNode;
    description?: string;
    short?: string;
    end?: string;
}

interface SearchResult {
    actions: Action[];
}

function ActionSearchBar({ actions = [], value = "" }: { actions?: Action[], value?: string }) {
    const [query, setQuery] = useState("");
    const [result, setResult] = useState<SearchResult | null>(null);
    const [isFocused, setIsFocused] = useState(false);
    const [selectedAction, setSelectedAction] = useState<Action | null>(null);
    const debouncedQuery = useDebounce(query, 200);

    // Sync input text with the external value (selected role)
    useEffect(() => {
        if (!isFocused) {
            const selected = actions.find(a => a.id === value);
            if (selected) setQuery(selected.label);
        }
    }, [value, actions, isFocused]);

    useEffect(() => {
        if (!isFocused) {
            setResult(null);
            return;
        }

        const selected = actions.find(a => a.id === value);
        // If query is empty OR matches the selected action label exactly, show ALL actions
        if (!debouncedQuery || (selected && debouncedQuery === selected.label)) {
            setResult({ actions: actions });
            return;
        }

        const normalizedQuery = debouncedQuery.toLowerCase().trim();
        const filteredActions = actions.filter((action) => {
            const searchableText = action.label.toLowerCase();
            return searchableText.includes(normalizedQuery);
        });

        setResult({ actions: filteredActions });
    }, [debouncedQuery, isFocused, actions, value]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setQuery(e.target.value);
    };

    const container = {
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

    const item = {
        hidden: { opacity: 0, y: 20 },
        show: {
            opacity: 1,
            y: 0,
            transition: {
                duration: 0.3,
            },
        },
        exit: {
            opacity: 0, y: -10,
            transition: {
                duration: 0.2,
            },
        },
    };

    const handleFocus = () => {
        setSelectedAction(null);
        setIsFocused(true);
    };

    return (
        <div className="w-full">
            <div className="relative flex flex-col justify-start items-center">
                <div className="w-full sticky top-0 bg-transparent z-10">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Select role..."
                            value={query}
                            onChange={handleInputChange}
                            onFocus={handleFocus}
                            onBlur={() =>
                                setTimeout(() => setIsFocused(false), 200)
                            }
                            className="pl-4 pr-10 py-2 h-11 text-sm rounded-xl border-border bg-surface text-text-main focus-visible:ring-brand/20 focus-visible:border-brand transition-all shadow-sm"
                        />
                        <div className="absolute right-4 top-1/2 -translate-y-1/2 text-text-muted">
                             <ChevronDown className={cn("w-4 h-4 transition-transform duration-200", isFocused && "rotate-180")} />
                        </div>
                    </div>
                </div>

                <div className="w-full mt-2">
                    <AnimatePresence>
                        {isFocused && result && !selectedAction && (
                            <motion.div
                                className="w-full border border-border rounded-xl shadow-2xl overflow-hidden bg-surface/95 backdrop-blur-md"
                                variants={container}
                                initial="hidden"
                                animate="show"
                                exit="exit"
                            >
                                <motion.ul className="p-1.5">
                                    {result.actions.map((action) => (
                                        <motion.li
                                            key={action.id}
                                            className="px-3 py-2.5 flex items-center justify-between hover:bg-brand hover:text-white cursor-pointer rounded-lg transition-colors group"
                                            variants={item}
                                            layout
                                            onClick={() => {
                                                setSelectedAction(action);
                                                setQuery(action.label);
                                                if ((action as any).onClick) (action as any).onClick();
                                            }}
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="text-text-muted group-hover:text-white transition-colors">
                                                    {action.icon}
                                                </span>
                                                <div className="leading-tight text-text-main group-hover:text-white">
                                                    <span className="text-sm font-bold block">
                                                        {action.label}
                                                    </span>
                                                    <span className="text-[10px] opacity-60">
                                                        {action.description}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-[10px] font-mono opacity-40 group-hover:opacity-100">
                                                    {action.short}
                                                </span>
                                                <span className="text-[10px] font-bold opacity-60">
                                                    {action.end}
                                                </span>
                                            </div>
                                        </motion.li>
                                    ))}
                                </motion.ul>
                                <div className="px-3 py-2 border-t border-border bg-surface-alt/50">
                                    <div className="flex items-center justify-between text-[10px] font-bold text-text-muted uppercase tracking-widest">
                                        <span>Pick an identity</span>
                                        <span>ESC to close</span>
                                    </div>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </div>
    );
}

// Utility for internal class merging
function cn(...inputs: any[]) {
    return inputs.filter(Boolean).join(" ");
}

export { ActionSearchBar };
