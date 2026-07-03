import { useState } from 'react'

function EyeIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6Z"
      />
      <circle cx="10" cy="10" r="2.5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function EyeOffIcon() {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="h-5 w-5">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M2.5 2.5l15 15M8.28 8.34a2.5 2.5 0 0 0 3.38 3.38M6.36 6.42C3.86 7.72 2 10 2 10s3 6 8 6c1.42 0 2.68-.31 3.77-.8M10 4c5 0 8.5 6 8.5 6a13.7 13.7 0 0 1-2.16 2.83"
      />
    </svg>
  )
}

export default function PasswordInput({ className = '', ...props }) {
  const [visible, setVisible] = useState(false)

  return (
    <div className="relative">
      <input
        type={visible ? 'text' : 'password'}
        className={`w-full rounded border px-3 py-2 pr-10 ${className}`}
        {...props}
      />
      <button
        type="button"
        onClick={() => setVisible((v) => !v)}
        tabIndex={-1}
        aria-label={visible ? 'Hide password' : 'Show password'}
        className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
      >
        {visible ? <EyeOffIcon /> : <EyeIcon />}
      </button>
    </div>
  )
}
