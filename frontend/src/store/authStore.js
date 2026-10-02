import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export const useAuthStore = create(
  persist(
    (set) => ({
      token: null,
      user: null,
      lastUserId: null,
      setAuth: (token, user) => set({ token, user, lastUserId: user?.id }),
      logout: () => set({ token: null, user: null }),
    }),
    { name: 'ccapp-auth' },
  ),
)
