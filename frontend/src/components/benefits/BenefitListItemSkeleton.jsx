import Skeleton from '../common/Skeleton'

export default function BenefitListItemSkeleton() {
  return (
    <div className="flex items-center justify-between rounded-lg border bg-white p-4 shadow-sm">
      <div>
        <div className="flex items-center gap-2">
          <Skeleton className="h-5 w-36" />
          <Skeleton className="h-5 w-14 rounded-full" />
          <Skeleton className="h-5 w-16 rounded-full" />
        </div>
        <Skeleton className="mt-2 h-4 w-48" />
      </div>
      <div className="flex gap-3">
        <Skeleton className="h-4 w-16" />
        <Skeleton className="h-4 w-12" />
      </div>
    </div>
  )
}
