import { prepareLogout } from '../../sync/engine'
import SyncStatus from '../common/SyncStatus'
import { useRef, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import { logout as logoutApi } from '../../api/auth'
import FullscreenLottieOverlay from '../common/FullscreenLottieOverlay'
import logoutAnimation from '../../assets/lottie/logout.json'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard' },
  { to: '/cards', label: 'My Cards' },
  { to: '/chat', label: 'Card Chat' },
  { to: '/recommendation', label: 'Recommendation' },
  { to: '/benefits', label: 'Benefits' },
  { to: '/calendar', label: 'Calendar' },
  { to: '/reports', label: 'Reports' },
  { to: '/comparison', label: 'Comparison' },
  { to: '/spend-analyzer', label: 'Spend Analyzer' },
  { to: '/sync', label: 'Sync Center' },
  { to: '/sharing', label: 'Card Sharing' },
  { to: '/import-messages', label: 'Import Messages' },
  { to: '/admin', label: 'Admin' },
  { to: '/development-ledger', label: 'Development Ledger' },
]

export default function AppShell() {
  const user = useAuthStore((s) => s.user)
  const token = useAuthStore((s) => s.token)
  const clearAuth = useAuthStore((s) => s.logout)
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const menuButtonRef = useRef(null)
  const finishedRef = useRef(false)

  const finishLogout = () => {
    if (finishedRef.current) return
    finishedRef.current = true
    clearAuth()
    navigate('/login')
  }

  const handleLogout = async () => {
    if (!await prepareLogout()) return
    setLoggingOut(true)
    logoutApi().catch(() => {
      // ignore — we clear local state regardless
    })
    // Fallback in case the animation's onComplete never fires.
    setTimeout(finishLogout, 3000)
  }

  return (
    <div className="min-h-dvh bg-gray-50">
      {loggingOut && (
        <FullscreenLottieOverlay animationData={logoutAnimation} message="Signing you out…" onComplete={finishLogout} />
      )}
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
          <span className="min-w-0 flex-1 text-sm font-semibold text-gray-900 sm:text-base">Credit Card Usage Optimizer</span>
          <button
            ref={menuButtonRef}
            type="button"
            aria-controls="app-navigation"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((open) => !open)}
            className="min-h-11 rounded border px-3 text-sm text-gray-700 hover:bg-gray-50 lg:hidden"
          >
            {menuOpen ? 'Close menu' : 'Menu'}
          </button>
          <div className="flex w-full flex-wrap items-center justify-between gap-3 text-sm text-gray-600 sm:w-auto sm:justify-end sm:gap-4">
            {token ? <>
              <span className="min-w-0 wrap-anywhere">{user?.name}</span>
              <button onClick={handleLogout} className="min-h-11 text-red-600 hover:underline">Logout</button>
            </> : <>
              <span>Development preview</span>
              <Link to="/login" className="text-indigo-600 hover:underline">Log in</Link>
            </>}
          </div>
        </div>
      </header>
      <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:flex-row lg:gap-6">
        <nav
          id="app-navigation"
          aria-label="Main navigation"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setMenuOpen(false)
              menuButtonRef.current?.focus()
            }
          }}
          className={`${menuOpen ? 'grid' : 'hidden'} grid-cols-1 gap-1 rounded-lg border bg-white p-2 sm:grid-cols-2 lg:block lg:w-48 lg:shrink-0 lg:space-y-1 lg:border-0 lg:bg-transparent lg:p-0`}
        >
          {NAV_ITEMS.filter(item => item.to !== '/admin' || user?.role === 'admin').map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={() => setMenuOpen(false)}
              className={({ isActive }) =>
                `block min-h-11 rounded px-3 py-3 text-sm lg:py-2 ${
                  isActive ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-100'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
        <main className="min-w-0 flex-1 wrap-anywhere">
          <SyncStatus />
          <Outlet />
        </main>
      </div>
    </div>
  )
}
