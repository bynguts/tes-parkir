"use client"

import React, { useEffect, useState } from "react"
import { AnimatePresence, motion, MotionConfig } from "framer-motion"
import { ChevronDownIcon, X } from "lucide-react"
import { cn } from "@/lib/utils"

export type TSelectData = {
  id: string
  label: string
  value: string
  description?: string
  icon?: React.ReactNode
  disabled?: boolean
}

type SelectProps = {
  data?: TSelectData[]
  onChange?: (value: string) => void
  defaultValue?: string
  placeholder?: string
}

const Select = ({ data, onChange, defaultValue, placeholder = "Select option..." }: SelectProps) => {
  const [open, setOpen] = React.useState(false)
  const ref = React.useRef<HTMLDivElement>(null)
  const [selected, setSelected] = useState<TSelectData | undefined>(undefined)

  useEffect(() => {
    if (defaultValue) {
      const item = data?.find((i) => i.value === defaultValue)
      if (item) {
        setSelected(item)
      }
    }
  }, [defaultValue, data])

  const onSelect = (value: string) => {
    const item = data?.find((i) => i.value === value)
    setSelected(item as TSelectData)
    setOpen(false)
    if (onChange) onChange(value)
  }

  return (
    <MotionConfig
      transition={{
        type: "spring",
        stiffness: 300,
        damping: 25,
      }}
    >
      <div className="flex items-center justify-start w-full">
        <AnimatePresence mode="popLayout">
          {!open ? (
            <motion.div
              whileTap={{ scale: 0.98 }}
              animate={{ borderRadius: 12 }}
              layout
              layoutId="dropdown-container"
              onClick={() => setOpen(true)}
              className="overflow-hidden w-full cursor-pointer border border-slate-200 bg-white shadow-sm hover:border-red-500/50 transition-colors"
            >
              <SelectItem item={selected} placeholder={placeholder} />
            </motion.div>
          ) : (
            <motion.div
              layout
              animate={{ borderRadius: 16 }}
              layoutId="dropdown-container"
              className="overflow-hidden w-full border border-slate-200 bg-white py-2 shadow-xl z-50"
              ref={ref}
            >
              <Head setOpen={setOpen} label={placeholder} />
              <div className="w-full max-h-60 overflow-y-auto px-2">
                {data?.map((item) => (
                  <SelectItem
                    key={item.id}
                    item={item}
                    isSelected={selected?.id === item.id}
                    onChange={() => onSelect(item.value)}
                    noDescription={false}
                  />
                ))}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </MotionConfig>
  )
}

const Head = ({ setOpen, label }: { setOpen: (open: boolean) => void; label: string }) => {
  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ delay: 0.1 }}
      layout
      className="flex items-center justify-between p-4 pb-2"
    >
      <motion.strong layout className="text-[10px] font-bold uppercase tracking-widest text-slate-400">
        {label}
      </motion.strong>
      <button
        onClick={(e) => {
          e.stopPropagation();
          setOpen(false);
        }}
        className="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 transition-colors"
      >
        <X className="text-slate-500" size={12} />
      </button>
    </motion.div>
  )
}

type SelectItemProps = {
  item?: TSelectData
  placeholder?: string
  noDescription?: boolean
  isSelected?: boolean
  onChange?: () => void
}

const itemVariants = {
  hidden: { opacity: 0, y: 10 },
  visible: { 
    opacity: 1, 
    y: 0,
    transition: { duration: 0.3 }
  },
  exit: { opacity: 0, y: 5 }
}

const SelectItem = ({
  item,
  placeholder,
  noDescription = true,
  isSelected,
  onChange,
}: SelectItemProps) => {
  return (
    <motion.div
      className={cn(
        "group flex cursor-pointer items-center justify-between gap-3 p-3 rounded-xl transition-all",
        !noDescription && "hover:bg-slate-900 hover:text-white mb-1",
        isSelected && !noDescription && "bg-slate-100",
        noDescription && "px-4 py-2.5 h-11"
      )}
      variants={!noDescription ? itemVariants : undefined}
      initial="hidden"
      animate="visible"
      exit="exit"
      onClick={onChange}
    >
      <div className="flex items-center gap-3 overflow-hidden">
        <motion.div
          layout
          layoutId={item ? `icon-${item.id}` : "icon-placeholder"}
          className={cn(
            "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-100 bg-slate-50",
            !noDescription && "group-hover:border-slate-700 group-hover:bg-slate-800"
          )}
        >
          {item?.icon || <ChevronDownIcon size={14} className="text-slate-300" />}
        </motion.div>
        <motion.div layout className="flex flex-col overflow-hidden">
          <motion.strong
            layoutId={item ? `label-${item.id}` : "label-placeholder"}
            className={cn(
              "text-sm font-bold truncate",
              !item && "text-slate-400 font-medium"
            )}
          >
            {item?.label || placeholder}
          </motion.strong>
          {!noDescription && item?.description && (
            <motion.span 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="truncate text-[10px] opacity-60 font-medium"
            >
              {item.description}
            </motion.span>
          )}
        </motion.div>
      </div>
      {noDescription && (
        <motion.div layout>
          <ChevronDownIcon className="text-slate-400" size={16} />
        </motion.div>
      )}
    </motion.div>
  )
}

export default Select
