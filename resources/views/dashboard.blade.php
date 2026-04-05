<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Sentinel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-gray-950 text-gray-100 font-mono text-sm">

<div class="max-w-5xl mx-auto p-6 space-y-8">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold tracking-tight">Cron Sentinel</h1>
        <span class="text-gray-500 text-xs">Last checked: {{ now()->format('H:i:s') }}</span>
    </div>

    {{-- Horizon --}}
    @if($horizon)
    <section>
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Horizon</h2>
        <div class="bg-gray-900 rounded-lg p-4 flex items-center gap-6">
            <div>
                <div class="text-xs text-gray-500">Status</div>
                <div class="mt-1 font-semibold {{ $horizon->running ? 'text-green-400' : 'text-red-400' }}">
                    {{ $horizon->running ? 'Running' : 'Stopped' }}
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Supervisors</div>
                <div class="mt-1 font-semibold">{{ count($horizon->supervisors) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Failed / min</div>
                <div class="mt-1 font-semibold {{ $horizon->failedJobsPerMinute > 0 ? 'text-yellow-400' : 'text-gray-200' }}">
                    {{ number_format($horizon->failedJobsPerMinute, 1) }}
                </div>
            </div>
            @if($horizon->pausedAt)
            <div class="ml-auto text-yellow-400 text-xs">⚠ Paused {{ $horizon->pausedAt->diffForHumans() }}</div>
            @endif
        </div>
    </section>
    @endif

    {{-- Queues --}}
    @if(count($queues))
    <section>
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Queues</h2>
        <div class="bg-gray-900 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-800">
                    <tr class="text-xs text-gray-500">
                        <th class="text-left px-4 py-2">Queue</th>
                        <th class="text-right px-4 py-2">Depth</th>
                        <th class="text-right px-4 py-2">Failed</th>
                        <th class="text-right px-4 py-2">Oldest Job</th>
                        <th class="text-right px-4 py-2">Health</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($queues as $queue)
                    <tr class="border-b border-gray-800/50 last:border-0">
                        <td class="px-4 py-3">{{ $queue->queue }}</td>
                        <td class="px-4 py-3 text-right {{ $queue->depth > 100 ? 'text-yellow-400' : '' }}">{{ number_format($queue->depth) }}</td>
                        <td class="px-4 py-3 text-right {{ $queue->failedCount > 0 ? 'text-red-400' : '' }}">{{ number_format($queue->failedCount) }}</td>
                        <td class="px-4 py-3 text-right text-gray-400">{{ $queue->oldestJobAgeSeconds ? $queue->oldestJobAgeSeconds.'s' : '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($queue->isHealthy())
                                <span class="text-green-400">✓ ok</span>
                            @else
                                <span class="text-red-400">✗ alert</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    {{-- Cron Jobs --}}
    @if(count($crons))
    <section>
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Scheduled Commands</h2>
        <div class="bg-gray-900 rounded-lg overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-800">
                    <tr class="text-xs text-gray-500">
                        <th class="text-left px-4 py-2">Command</th>
                        <th class="text-left px-4 py-2">Schedule</th>
                        <th class="text-right px-4 py-2">Last Run</th>
                        <th class="text-right px-4 py-2">Duration</th>
                        <th class="text-right px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($crons as $cron)
                    <tr class="border-b border-gray-800/50 last:border-0">
                        <td class="px-4 py-3 text-gray-300">{{ $cron->command }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $cron->expression }}</td>
                        <td class="px-4 py-3 text-right text-gray-400">{{ $cron->lastRunAt?->diffForHumans() ?? 'never' }}</td>
                        <td class="px-4 py-3 text-right text-gray-400">{{ $cron->lastDurationMs ? $cron->lastDurationMs.'ms' : '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($cron->status === 'ok') <span class="text-green-400">✓ ok</span>
                            @elseif($cron->status === 'failed') <span class="text-red-400">✗ failed</span>
                            @elseif($cron->status === 'late') <span class="text-yellow-400">⚠ late</span>
                            @else <span class="text-gray-500">– never run</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

</div>

</body>
</html>
