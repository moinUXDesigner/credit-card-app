import { useRef, useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import { logout as logoutApi } from '../../api/auth'
import FullscreenLottieOverlay from '../common/FullscreenLottieOverlay'
import logoutAnimation from '../../assets/lottie/logout.json'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard' },
  { to: '/cards', label: 'My Cards' },
  { to: '/recommendation', label: 'Recommendation' },
  { to: '/benefits', label: 'Benefits' },
  { to: '/calendar', label: 'Calendar' },
  { to: '/reports', label: 'Reports' },
  { to: '/comparison', label: 'Comparison' },
  { to: '/spend-analyzer', label: 'Spend Analyzer' },
]

export default function AppShell() {
  const user = useAuthStore((s) => s.user)
  const clearAuth = useAuthStore((s) => s.logout)
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)
  const finishedRef = useRef(false)

  const finishLogout = () => {
    if (finishedRef.current) return
    finishedRef.current = true
    clearAuth()
    navigate('/login')
  }

  const handleLogout = () => {
    setLoggingOut(true)
    logoutApi().catch(() => {
      // ignore — we clear local state regardless
    })
    // Fallback in case the animation's onComplete never fires.
    setTimeout(finishLogout, 3000)
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {loggingOut && (
        <FullscreenLottieOverlay animationData={logoutAnimation} message="Signing you out…" onComplete={finishLogout} />
      )}
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <span className="font-semibold text-gray-900">Credit Card Usage Optimizer</span>
          <div className="flex items-center gap-4 text-sm text-gray-600">
            <span>{user?.name}</span>
            <button onClick={handleLogout} className="text-red-600 hover:underline">
              Logout
            </button>
          </div>
        </div>
      </header>
      <div className="mx-auto flex max-w-6xl gap-6 px-4 py-6">
        <nav className="w-48 shrink-0 space-y-1">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                `block rounded px-3 py-2 text-sm ${
                  isActive ? 'bg-indigo-100 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-100'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
        <main className="flex-1">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
