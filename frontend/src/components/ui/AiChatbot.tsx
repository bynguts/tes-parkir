import { useState, useRef, useEffect } from 'react'
import { motion, AnimatePresence } from 'motion/react'
import { Bot, X, Send, Sparkles, Loader2, MinusSquare } from 'lucide-react'

interface Message {
  id: string
  role: 'user' | 'assistant'
  content: string
}

export function AiChatbot() {
  const [isOpen, setIsOpen] = useState(false)
  const [isMinimized, setIsMinimized] = useState(false)
  const [messages, setMessages] = useState<Message[]>([
    { id: '1', role: 'assistant', content: 'Halo! Saya Cereza, AI Assistant SmartParking. Ada yang bisa dibantu tentang operasional hari ini?' }
  ])
  const [input, setInput] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  useEffect(() => {
    if (isOpen && !isMinimized) {
      scrollToBottom()
    }
  }, [messages, isOpen, isMinimized])

  const handleSend = async () => {
    if (!input.trim() || isLoading) return
    
    const userMsg: Message = { id: Date.now().toString(), role: 'user', content: input }
    setMessages(prev => [...prev, userMsg])
    setInput('')
    setIsLoading(true)

    try {
      const res = await fetch('/api/ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: userMsg.content })
      })
      const data = await res.json()
      
      const aiMsg: Message = { 
        id: (Date.now() + 1).toString(), 
        role: 'assistant', 
        content: data.response || data.error || 'Maaf, saya tidak bisa merespons saat ini.'
      }
      setMessages(prev => [...prev, aiMsg])
    } catch (err: any) {
      setMessages(prev => [...prev, { id: Date.now().toString(), role: 'assistant', content: `Error: ${err.message}` }])
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <>
      {/* Floating Button */}
      <AnimatePresence>
        {(!isOpen || isMinimized) && (
          <motion.button
            initial={{ scale: 0, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0, opacity: 0 }}
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={() => { setIsOpen(true); setIsMinimized(false); }}
            className="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 rounded-full bg-primary text-white shadow-xl shadow-primary/30"
          >
            <Sparkles className="w-6 h-6" />
            <span className="absolute -top-1 -right-1 flex h-4 w-4">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
              <span className="relative inline-flex rounded-full h-4 w-4 bg-red-500 border-2 border-primary"></span>
            </span>
          </motion.button>
        )}
      </AnimatePresence>

      {/* Chat Window */}
      <AnimatePresence>
        {isOpen && !isMinimized && (
          <motion.div
            initial={{ opacity: 0, y: 20, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 20, scale: 0.95 }}
            transition={{ type: 'spring', damping: 25, stiffness: 300 }}
            className="fixed bottom-6 right-6 z-50 w-[380px] h-[550px] max-h-[80vh] flex flex-col bg-surface rounded-2xl shadow-2xl border border-border overflow-hidden"
          >
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-4 bg-primary text-white">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                  <Bot className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm leading-tight">Cereza AI</h3>
                  <p className="text-[10px] text-white/70 uppercase tracking-widest font-black">SmartParking Assistant</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button onClick={() => setIsMinimized(true)} className="p-1.5 hover:bg-white/20 rounded-lg transition-colors">
                  <MinusSquare className="w-4 h-4" />
                </button>
                <button onClick={() => setIsOpen(false)} className="p-1.5 hover:bg-white/20 rounded-lg transition-colors">
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4 bg-bg-page scroll-smooth">
              {messages.map((msg) => (
                <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                  <div className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm ${
                    msg.role === 'user' 
                      ? 'bg-primary text-white rounded-tr-sm' 
                      : 'bg-surface-alt text-text-main border border-border rounded-tl-sm'
                  }`}>
                    {/* Render markdown strictly by splitting newlines for simplicity or use ReactMarkdown. Doing simple formatting here */}
                    {msg.content.split('\n').map((line, i) => (
                      <p key={i} className="mb-1 last:mb-0 min-h-[1em]">
                        {line.replace(/\*\*(.*?)\*\*/g, '$1')} {/* Simple bold strip for now */}
                      </p>
                    ))}
                  </div>
                </div>
              ))}
              {isLoading && (
                <div className="flex justify-start">
                  <div className="bg-surface-alt border border-border rounded-2xl rounded-tl-sm px-4 py-3 flex items-center gap-2">
                    <Loader2 className="w-4 h-4 text-primary animate-spin" />
                    <span className="text-xs text-text-muted font-bold tracking-widest uppercase">Cereza is thinking...</span>
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Input Area */}
            <div className="p-4 bg-surface border-t border-border">
              <form 
                onSubmit={(e) => { e.preventDefault(); handleSend(); }}
                className="flex items-center gap-2 bg-surface-alt border border-border rounded-xl p-1 pr-2 focus-within:ring-2 focus-within:ring-primary/50 transition-all"
              >
                <input
                  type="text"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  placeholder="Ask about revenue, traffic..."
                  className="flex-1 bg-transparent border-none text-sm px-3 py-2 focus:outline-none text-text-main placeholder:text-text-muted/50"
                />
                <button 
                  type="submit"
                  disabled={!input.trim() || isLoading}
                  className="w-8 h-8 rounded-lg bg-primary text-white flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary/90 transition-colors"
                >
                  <Send className="w-4 h-4 ml-0.5" />
                </button>
              </form>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  )
}
