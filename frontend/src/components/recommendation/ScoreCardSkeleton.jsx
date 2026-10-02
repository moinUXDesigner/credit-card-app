import Skeleton from '../common/Skeleton'

export default function ScoreCardSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Skeleton className="h-8 w-8 rounded-full" />
          <Skeleton className="h-5 w-32" />
        </div>
        <Skeleton className="h-6 w-16" />
      </div>
      <div className="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 md:grid-cols-5">
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i}>
            <Skeleton className="h-3 w-20" />
            <Skeleton className="mt-1 h-3 w-10" />
          </div>
        ))}
      </div>
    </div>
  )
}
