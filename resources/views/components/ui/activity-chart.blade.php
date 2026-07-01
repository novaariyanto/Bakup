@props(['data'])

@php
    $maxTotal = max(1, collect($data)->max('total') ?? 1);
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-4 text-xs text-zinc-500">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>
            Berhasil
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-sm bg-red-500"></span>
            Gagal
        </span>
    </div>

    <div class="flex h-48 items-end gap-1 sm:gap-1.5">
        @foreach ($data as $day)
            @php
                $successHeight = $maxTotal > 0 ? round(($day['success'] / $maxTotal) * 100) : 0;
                $failedHeight = $maxTotal > 0 ? round(($day['failed'] / $maxTotal) * 100) : 0;
            @endphp
            <div class="group flex flex-1 flex-col items-center justify-end" title="{{ $day['label'] }}: {{ $day['success'] }} sukses, {{ $day['failed'] }} gagal">
                <div class="flex w-full flex-col justify-end gap-0.5" style="height: 11rem;">
                    @if ($day['failed'] > 0)
                        <div class="w-full rounded-t bg-red-500/90 transition-all group-hover:bg-red-400" style="height: {{ max($failedHeight, $day['failed'] > 0 ? 4 : 0) }}%"></div>
                    @endif
                    @if ($day['success'] > 0)
                        <div @class([
                            'w-full bg-emerald-500/90 transition-all group-hover:bg-emerald-400',
                            'rounded-t' => $day['failed'] === 0,
                            'rounded-b' => true,
                        ]) style="height: {{ max($successHeight, $day['success'] > 0 ? 4 : 0) }}%"></div>
                    @endif
                    @if ($day['total'] === 0)
                        <div class="h-1 w-full rounded bg-zinc-800"></div>
                    @endif
                </div>
                @if ($loop->index % 5 === 0 || $loop->last)
                    <span class="mt-2 hidden text-[10px] text-zinc-500 sm:block">{{ $day['label'] }}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
