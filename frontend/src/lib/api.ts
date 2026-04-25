/**
 * lib/api.ts
 * Centralized API client — all requests go to the PHP backend.
 * In dev: Vite proxies /api → http://localhost:8000
 * In prod: same-origin requests (frontend served from backend root or nginx proxy).
 */

const BASE = import.meta.env.VITE_API_BASE ?? ''

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message)
    this.name = 'ApiError'
  }
}

async function request<T>(
  path: string,
  init?: RequestInit,
): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(init?.headers ?? {}) },
    ...init,
  })
  if (!res.ok) {
    const text = await res.text().catch(() => res.statusText)
    throw new ApiError(res.status, text)
  }
  return res.json() as Promise<T>
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(body) }),
  postForm: <T>(path: string, data: FormData) =>
    request<T>(path, {
      method: 'POST',
      body: data,
      headers: {},
    }),
}

// ── Typed endpoint helpers ──────────────────────────────────────────────

export interface DashboardData {
  car_avail: number
  car_total: number
  car_pct: number
  moto_avail: number
  moto_total: number
  moto_pct: number
  active: number
  today_rev: number
  total_reservations: number
  active_reservations: number
  page_title: string
  page_subtitle: string
  on_duty: boolean
  username: string
  role: string
  revenue_weekly: Array<{ day: string; revenue: number }>
}

export interface AuthUser {
  logged_in: boolean
  user_id: number
  username: string
  full_name: string
  role: 'superadmin' | 'admin' | 'operator'
}

export interface SlotSummary {
  floor_name: string
  slot_type: 'car' | 'motorcycle'
  total: number
  available: number
  occupied: number
  reserved: number
  maintenance: number
}

export interface Transaction {
  transaction_id: number
  ticket_code: string
  plate_number: string
  slot_type: string
  check_in_time: string
  check_out_time: string | null
  total_fee: number
  payment_status: 'unpaid' | 'paid'
}

export interface AiChatResponse {
  reply: string
  error?: string
}

export const endpoints = {
  dashboard: () => api.get<DashboardData>('/api/dashboard.php'),
  me: () => api.get<AuthUser>('/auth/me.php'),
  login: (username: string, password: string, csrf_token: string) =>
    api.post<{ success: boolean; error?: string }>('/auth/login.php', {
      username,
      password,
      csrf_token,
    }),
  logout: () => api.post<void>('/auth/logout.php', {}),
  csrf: () => api.get<{ token: string }>('/auth/csrf.php'),
  slotSummary: () => api.get<SlotSummary[]>('/api/slots/summary.php'),
  activeVehicles: () => api.get<Transaction[]>('/api/vehicles/active.php'),
  aiChat: (query: string, csrf_token: string) =>
    api.post<AiChatResponse>('/api/ai_chat.php', { query, csrf_token }),
  bookings: () => api.get<{ success: boolean; data: any[] }>('/api/bookings.php'),
}
