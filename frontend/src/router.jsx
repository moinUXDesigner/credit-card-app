import { createBrowserRouter } from 'react-router-dom'
import AppShell from './components/layout/AppShell'
import ProtectedRoute from './components/common/ProtectedRoute'
import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import MyCards from './pages/MyCards'
import CardFormPage from './pages/CardFormPage'
import CardImportPage from './pages/CardImportPage'
import Recommendation from './pages/Recommendation'
import Benefits from './pages/Benefits'
import CalendarPage from './pages/CalendarPage'
import Reports from './pages/Reports'
import Comparison from './pages/Comparison'

export const router = createBrowserRouter([
  { path: '/login', element: <Login /> },
  { path: '/register', element: <Register /> },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppShell />,
        children: [
          { path: '/', element: <Dashboard /> },
          { path: '/cards', element: <MyCards /> },
          { path: '/cards/new', element: <CardFormPage /> },
          { path: '/cards/import', element: <CardImportPage /> },
          { path: '/cards/:id/edit', element: <CardFormPage /> },
          { path: '/recommendation', element: <Recommendation /> },
          { path: '/benefits', element: <Benefits /> },
          { path: '/calendar', element: <CalendarPage /> },
          { path: '/reports', element: <Reports /> },
          { path: '/comparison', element: <Comparison /> },
        ],
      },
    ],
  },
])
