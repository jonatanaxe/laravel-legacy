@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    @php
        $money = function ($value) {
            return 'R$ ' . number_format((float) $value, 2, '.', ',');
        };
        $statusBadge = ['Draft' => 'badge-muted', 'Issued' => 'badge-ok', 'Cancelled' => 'badge-danger', 'Unknown' => 'badge-warn'];
        $total = array_sum(array_column($summary, 'qty'));
    @endphp

    <div class="wrap page">
        <header class="page-head">
            <div>
                <p class="kicker">billing · SIG module</p>
                <h1>Service invoices</h1>
                <p class="sub">
                    Invoices imported from the SIG, the old billing system (2009–2016). Read only:
                    the code lives in <code>app/Legacy</code> and is frozen by tests until the migration.
                </p>
            </div>
        </header>

        <section class="stats" aria-label="Billing summary">
            <div class="stat is-ok">
                <div class="stat-label">Issued</div>
                <div class="stat-value">{{ $money($summary['Issued']['amount']) }}</div>
                <div class="stat-note">{{ $summary['Issued']['qty'] }} invoices</div>
            </div>
            <div class="stat">
                <div class="stat-label">Drafts</div>
                <div class="stat-value">{{ $money($summary['Draft']['amount']) }}</div>
                <div class="stat-note">{{ $summary['Draft']['qty'] }} invoices not sent yet</div>
            </div>
            <div class="stat {{ $summary['Cancelled']['qty'] ? 'is-danger' : '' }}">
                <div class="stat-label">Cancelled</div>
                <div class="stat-value">{{ $money($summary['Cancelled']['amount']) }}</div>
                <div class="stat-note">{{ $summary['Cancelled']['qty'] }} invoices</div>
            </div>
            <div class="stat">
                <div class="stat-label">Total invoices</div>
                <div class="stat-value">{{ $total }}</div>
                <div class="stat-note">status in two formats: 0/1/2 and draft/sent</div>
            </div>
        </section>

        <div class="split">
            <section class="panel" aria-labelledby="invoices-title">
                <div class="panel-pad pb-0">
                    <h2 class="panel-title" id="invoices-title">Invoices</h2>
                </div>

                @if ($invoices->count())
                    <div class="scroll mt-3">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th class="text-right">Total</th>
                                    <th>Issued on</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td><code>{{ $invoice->code() }}</code></td>
                                        <td>
                                            <span class="block text-ink">{{ $invoice->customer_name }}</span>
                                            <span class="block text-xs text-muted font-mono">{{ $invoice->customer_document }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusBadge[$invoice->statusName()] }}">{{ $invoice->statusName() }}</span>
                                            <span class="block text-xs text-muted font-mono mt-1" title="value stored in the database">status = {{ $invoice->status }}</span>
                                        </td>
                                        <td class="text-right font-mono whitespace-no-wrap">{{ $money($invoice->total) }}</td>
                                        <td class="whitespace-no-wrap">{{ optional($invoice->issued_at)->format('M j, Y') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 pb-4">
                        {{ $invoices->links('partials.pagination') }}
                    </div>
                @else
                    <div class="empty">
                        <h3>No invoices imported</h3>
                        <p>Run the SIG seeder: <code>php artisan db:seed --class=LegacyInvoicesSeeder</code></p>
                    </div>
                @endif
            </section>

            <aside class="split-aside">
                <section class="panel panel-pad" aria-labelledby="legacy-title">
                    <h2 class="panel-title" id="legacy-title">SIG report</h2>
                    <p class="text-sm text-muted mt-1">
                        Output of <code>BillingReport::byStatus()</code>, unchanged. It is what
                        accounting receives on paper today.
                    </p>

                    @if (count($legacyReport))
                        <ul class="list-none m-0 p-0 mt-3">
                            @foreach ($legacyReport as $line)
                                <li class="flex items-baseline justify-between gap-2 py-2 border-b border-line text-sm">
                                    <span class="text-ink">{{ $line['status'] }} <span class="text-muted">· {{ $line['qty'] }}</span></span>
                                    <span class="font-mono text-ink">{{ $money($line['amount']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-body mt-3">No invoices.</p>
                    @endif

                    <div class="alert alert-warn mt-4 text-sm" role="note">
                        <strong>Known bug, frozen under test.</strong>
                        Drafts show up as "Issued": a nested ternary without parentheses in
                        <code>StatusLabel.php</code>. On PHP 8 that line doesn't even compile. Fixing it
                        changes behavior and needs a heads-up to accounting.
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
