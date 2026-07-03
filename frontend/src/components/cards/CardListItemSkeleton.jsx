import Skeleton from '../common/Skeleton'

export default function CardListItemSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <Skeleton className="h-5 w-40" />
            <Skeleton className="h-5 w-14 rounded-full" />
            <Skeleton className="h-4 w-16" />
          </div>
          <Skeleton className="mt-2 h-4 w-56" />
        </div>
        <div className="flex gap-3">
          <Skeleton className="h-4 w-10" />
          <Skeleton className="h-4 w-12" />
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-6">
        <div>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-2 h-2 w-full rounded-full" />
          <Skeleton className="mt-2 h-3 w-40" />
        </div>
        <div>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-2 h-2 w-full rounded-full" />
          <Skeleton className="mt-2 h-3 w-40" />
        </div>
      </div>
    </div>
  )
}
