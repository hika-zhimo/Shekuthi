@extends('layouts.app')

@section('title', 'Transport & errands - '.config('branding.name'))

@section('content')
    <section class="dash">
        <div class="container">
            <header class="dash-head">
                <div>
                    <h1>Transport &amp; errands</h1>
                    <p class="dash-lede">
                        Drivers and errand runners you can call directly. Filter
                        by the work they do — arrangements and payment happen
                        between you.
                    </p>
                </div>
            </header>

            @if ($categories->isNotEmpty())
                <nav class="dash-tag-row" aria-label="Filter by work" style="margin-bottom: var(--space-3);">
                    <a class="dash-tag {{ $selected ? '' : 'dash-tag--active' }}"
                       href="{{ route('transport') }}">All work</a>
                    @foreach ($categories as $category)
                        <a class="dash-tag {{ $selected === $category->id ? 'dash-tag--active' : '' }}"
                           href="{{ route('transport', ['category_id' => $category->id]) }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($drivers->isEmpty())
                <div class="dash-card">
                    <div class="dash-empty">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"/><path d="M3 9l4 0"/></svg>
                        <p>No drivers listed{{ $selected ? ' for that work' : '' }} yet.</p>
                    </div>
                </div>
            @else
                <div class="dash-grid">
                    @foreach ($drivers as $driver)
                        <div class="dash-card">
                            <div class="dash-card-head">
                                <h2>{{ $driver->name }}</h2>
                                @if ($driver->driverAvailability?->is_online)
                                    <span class="chip chip-active">Online</span>
                                @else
                                    <span class="chip">Offline</span>
                                @endif
                            </div>

                            <p>{{ \App\Models\User::VEHICLE_CATEGORIES[$driver->vehicle_category] }}</p>

                            @if ($driver->riderBaseOperation?->district)
                                <p class="muted small" style="margin: 0 0 var(--space-1);">
                                    Based in {{ $driver->riderBaseOperation->district->name }}
                                </p>
                            @endif

                            @if ($driver->transportCategories->isNotEmpty())
                                <div class="dash-tag-row">
                                    @foreach ($driver->transportCategories as $category)
                                        <span class="dash-tag">{{ $category->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="dash-actions" style="margin-top: var(--space-2);">
                                @if ($driver->phone)
                                    <a class="btn btn-primary btn-sm" href="tel:{{ $driver->phone }}">
                                        Call {{ $driver->phone }}
                                    </a>
                                @else
                                    <span class="muted small">No number shared yet.</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
