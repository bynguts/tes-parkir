import { Outlet } from 'react-router-dom'
import { Sidebar } from './Sidebar'

export function AppShell() {
  return (
    <div className="app-shell">
      <Sidebar />
      <div className="app-main">
        <div className="app-content">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
