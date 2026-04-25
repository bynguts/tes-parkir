import { createContext, useContext, useEffect, useState, ReactNode } from 'react'
import { endpoints, type AuthUser } from '../lib/api'

interface AuthContextValue {
  user: AuthUser | null
  loading: boolean
  refetch: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue>({
  user: null,
  loading: true,
  refetch: async () => {},
})

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

  const refetch = async () => {
    try {
      const data = await endpoints.me()
      setUser(data.logged_in ? data : null)
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { refetch() }, [])

  return (
    <AuthContext.Provider value={{ user, loading, refetch }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
