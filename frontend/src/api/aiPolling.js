export async function waitForAi(initial, read, { onProgress, signal } = {}) {
  let value = initial
  const started = Date.now()
  while (value.status === 'processing') {
    if (signal?.aborted) throw new DOMException('Canceled', 'AbortError')
    onProgress?.(value)
    if (Date.now() - started > 180000) throw new Error('Reading is still processing. Retry from the saved statement or conversation in a moment.')
    await new Promise((resolve, reject) => {
      const cancel = () => { clearTimeout(timer); reject(new DOMException('Canceled', 'AbortError')) }
      const timer = setTimeout(() => { signal?.removeEventListener('abort', cancel); resolve() }, 1500)
      signal?.addEventListener('abort', cancel, { once: true })
    })
    value = await read(value)
  }
  return value
}
