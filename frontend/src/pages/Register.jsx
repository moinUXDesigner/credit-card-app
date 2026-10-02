import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { register } from '../api/auth'
import { useAuthStore } from '../store/authStore'
import PasswordInput from '../components/common/PasswordInput'

export default function Register() {
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const setAuth = useAuthStore((s) => s.setAuth)
  const navigate = useNavigate()

  const update = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }))

  const handleSubmit = async (e) => {
    e.preventDefault()
    setErrors({})
    setLoading(true)
    try {
      const data = await register(form)
      setAuth(data.access_token, data.user)
      navigate('/')
    } catch (err) {
      setErrors(err.response?.data?.errors ?? { form: [err.response?.data?.message ?? 'Registration failed.'] })
    } finally {
      setLoading(false)
    }
  }

  const fieldError = (key) => errors[key]?.[0]

  return (
    <div className="flex min-h-dvh items-center justify-center bg-gray-50 px-4 py-6">
      <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded-lg border bg-white p-4 shadow-sm sm:p-6">
        <h1 className="text-xl font-semibold text-gray-900">Create account</h1>
        {errors.form && <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{errors.form[0]}</p>}
        {[
          { key: 'name', label: 'Name', type: 'text' },
          { key: 'email', label: 'Email', type: 'email' },
          { key: 'password', label: 'Password', type: 'password' },
          { key: 'password_confirmation', label: 'Confirm password', type: 'password' },
        ].map(({ key, label, type }) => (
          <div key={key}>
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            {type === 'password' ? (
              <PasswordInput required value={form[key]} onChange={update(key)} className="mt-1" />
            ) : (
              <input
                type={type}
                required
                value={form[key]}
                onChange={update(key)}
                className="mt-1 w-full rounded border px-3 py-2"
              />
            )}
            {fieldError(key) && <p className="mt-1 text-xs text-red-600">{fieldError(key)}</p>}
          </div>
        ))}
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded bg-indigo-600 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {loading ? 'Creating…' : 'Create account'}
        </button>
        <p className="text-center text-sm text-gray-600">
          Already have an account? <Link to="/login" className="text-indigo-600 hover:underline">Log in</Link>
        </p>
      </form>
    </div>
  )
}
