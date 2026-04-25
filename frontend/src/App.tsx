import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { Suspense, lazy } from 'react'
import { AuthProvider, useAuth } from './hooks/useAuth'
import { AppShell } from './components/layout/AppShell'
import PhpPage from './components/ui/PhpPage'
import { AiChatbot } from './components/ui/AiChatbot'

// Eagerly loaded
import LoginPage from './pages/Login'
import HomePage from './pages/Home'

// Lazy loaded
const DashboardPage = lazy(() => import('./pages/Dashboard'))
const UsersPage = lazy(() => import('./pages/Users'))
const ReservePage = lazy(() => import('./pages/Reserve'))
const BookingsPage = lazy(() => import('./pages/Bookings'))
const AccountPage = lazy(() => import('./pages/Account'))

function RequireAuth({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <PageLoader />
  if (!user) return <Navigate to="/login" replace />
  return <>{children}</>
}

function PageLoader() {
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%', minHeight: 300 }}>
      <span className="material-symbols-outlined" style={{ fontSize: 36, color: 'hsl(var(--primary))', animation: 'spin 1s linear infinite' }}>
        progress_activity
      </span>
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  )
}

/** PHP module pages rendered inside the React shell via iframe */
function PhpRoute({ src, title }: { src: string; title: string }) {
  return (
    <RequireAuth>
      <PhpPage src={src} title={title} />
    </RequireAuth>
  )
}

export default function App() {
  return (
    <AuthProvider>
      <AiChatbot />
      <BrowserRouter>
        <Routes>
          {/* Public */}
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/reserve" element={
            <Suspense fallback={<PageLoader />}>
              <ReservePage />
            </Suspense>
          } />
          <Route path="/bookings" element={
            <Suspense fallback={<PageLoader />}>
              <BookingsPage />
            </Suspense>
          } />
          <Route path="/account" element={
            <Suspense fallback={<PageLoader />}>
              <AccountPage />
            </Suspense>
          } />

          {/* Protected — React Shell with Sidebar */}
          <Route element={
            <RequireAuth>
              <AppShell />
            </RequireAuth>
          }>
            <Route path="/dashboard" element={
              <Suspense fallback={<PageLoader />}>
                <DashboardPage />
              </Suspense>
            } />

            {/* PHP module pages via iframe */}
            <Route path="/gate" element={
              <PhpPage src="/modules/operations/gate_simulator.php" title="Smart Gate" />
            } />
            <Route path="/gate/exit" element={
              <PhpPage src="/modules/operations/gate_exit.php" title="Gate Exit" />
            } />
            <Route path="/vehicles" element={
              <PhpPage src="/modules/operations/active_vehicles.php" title="Active Vehicles" />
            } />
            <Route path="/reservations" element={
              <PhpPage src="/modules/operations/reservation.php" title="Reservations" />
            } />
            <Route path="/scan-log" element={
              <PhpPage src="/modules/operations/scan_log.php" title="Scan Log" />
            } />

            {/* Reports */}
            <Route path="/reports/revenue" element={
              <PhpPage src="/modules/reports/revenue.php" title="Revenue" />
            } />
            <Route path="/reports/slot-map" element={
              <PhpPage src="/modules/reports/slot_map.php" title="Slot Map" />
            } />
            <Route path="/reports/overview" element={
              <PhpPage src="/modules/reports/overview.php" title="Overview" />
            } />

            {/* Admin */}
            <Route path="/admin/slots" element={
              <PhpPage src="/modules/admin/slots.php" title="Manage Slots" />
            } />
            <Route path="/admin/rates" element={
              <PhpPage src="/modules/admin/rates.php" title="Manage Rates" />
            } />
            <Route path="/admin/operators" element={
              <PhpPage src="/modules/admin/operators.php" title="Operators" />
            } />
            <Route path="/admin/users" element={
              <Suspense fallback={<PageLoader />}>
                <UsersPage />
              </Suspense>
            } />
          </Route>

          {/* Fallback */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
