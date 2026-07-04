// Imported from the package's ESM build directly: lottie-react's "browser" field
// points esbuild/Vite's dep optimizer at its UMD bundle, whose default export ends up
// being the whole CJS exports object (not the Lottie component) under Vite's dep
// pre-bundling — importing the ESM build sidesteps that mis-resolution.
import Lottie from 'lottie-react/build/index.es.js'

export default function FullscreenLottieOverlay({ animationData, message, onComplete }) {
  return (
    <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-white">
      <div className="h-48 w-48">
        <Lottie animationData={animationData} loop={false} autoplay onComplete={onComplete} />
      </div>
      <p className="text-lg font-medium text-gray-700">{message}</p>
    </div>
  )
}
