import { createBrowserRouter } from 'react-router-dom'
import CardChat from './pages/CardChat'
import AppShell from './components/layout/AppShell'
import ProtectedRoute from './components/common/ProtectedRoute'
import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import MyCards from './pages/MyCards'
import CardFormPage from './pages/CardFormPage'
import CardImportPage from './pages/CardImportPage'
import CardDetailPage from './pages/CardDetailPage'
import Recommendation from './pages/Recommendation'
import Benefits from './pages/Benefits'
import CalendarPage from './pages/CalendarPage'
import Reports from './pages/Reports'
import Comparison from './pages/Comparison'
import SpendAnalyzer from './pages/SpendAnalyzer'
import SyncCenter from './pages/SyncCenter'
import Admin from './pages/Admin'
import Sharing from './pages/Sharing'
import ImportMessages from './pages/ImportMessages'
import DevelopmentLedger from './pages/DevelopmentLedger'

export const router = createBrowserRouter([
  { path: '/login', element: <Login /> },
  { path: '/register', element: <Register /> },
  ...(import.meta.env.DEV ? [{
    element: <AppShell />,
    children: [{ path: '/development-ledger', element: <DevelopmentLedger /> }],
  }] : []),
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
          { path: '/cards/:id', element: <CardDetailPage /> },
          { path: '/recommendation', element: <Recommendation /> },
          { path: '/benefits', element: <Benefits /> },
          { path: '/calendar', element: <CalendarPage /> },
          { path: '/reports', element: <Reports /> },
          { path: '/chat', element: <CardChat /> },
          { path: '/comparison', element: <Comparison /> },
          { path: '/spend-analyzer', element: <SpendAnalyzer /> },
          { path: '/sync', element: <SyncCenter /> },
          { path: '/admin', element: <Admin /> },
          { path: '/sharing', element: <Sharing /> },
          { path: '/import-messages', element: <ImportMessages /> },
          ...(!import.meta.env.DEV ? [{ path: '/development-ledger', element: <DevelopmentLedger /> }] : []),
        ],
      },
    ],
  },
])
