@extends('layouts.app')

@section('title', config('branding.name').' - Buy, sell and deliver local goods')

@section('content')
    <section class="container hero">
        <div>
            <h1>Local goods, honest sourcing, direct connections.</h1>
            <p>
                A free, open platform that connects buyers, sellers, logistics and
                ground-truth verification. Listings from farmers, traditional and
                agro vendors, rentals and homestays. The platform never touches
                money and charges no commission.
            </p>
            <a class="btn btn-primary" href="{{ route('catalog') }}">Browse catalog</a>
            <a class="btn btn-secondary" href="{{ route('about') }}">How verification works</a>
        </div>
        <img class="hero-art" src="{{ asset('img/home-landscape-cutout.png') }}" alt="Illustration of rolling hills, cultivated fields, hay bales and a rural building" width="1254" height="1254">
    </section>

    <section class="section section-alt">
        <div class="container">
            <h2>Built around the people who make local trade work</h2>
            <div class="value-grid">
                <div class="card">
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 6h4v4M22 6l-9 9-4-4-6 6"/></svg>
                    <h3>Sell without fees</h3>
                    <p>Traditional products, agro produce, rentals and homestays. List items with your price and minimum order quantity. The platform takes nothing.</p>
                </div>
                <div class="card">
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6l9 0M3 12l9 0M3 18l9 0M17 6h4v4M21 6l-5 5"/></svg>
                    <h3>Logistics that fits your base</h3>
                    <p>Drivers and collectors work from their own district and up to five localities, toggling online when they are available.</p>
                </div>
                <div class="card">
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="5"/><path d="M8 9h2l1-2 2 4 1-2h2M9 14l-2 8 5-3 5 3-2-8"/></svg>
                    <h3>Ground-truth verification</h3>
                    <p>Volunteers visit sites and publish what they find. Verified listings carry the volunteer's name - trust you can check.</p>
                </div>
                <div class="card">
                    <svg class="card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21c-4.5-3-8-6.5-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 3.5C20 14.5 16.5 18 12 21z"/></svg>
                    <h3>Runs on donations</h3>
                    <p>Free for everyone. The platform is funded through voluntary UPI donations from people who want local trade to grow.</p>
                </div>
            </div>
        </div>
    </section>

    @if ($stays->isNotEmpty())
        <section class="section section-alt">
            <div class="container">
                <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-2);">
                    <div>
                        <h2>PG, rentals &amp; homestays</h2>
                        <p style="color: var(--color-muted); max-width: 65ch;">
                            Rooms, paying-guest accommodation, rentals and
                            homestays. Search them on their own page.
                        </p>
                    </div>
                    <a class="btn btn-secondary" href="{{ route('stays') }}">See all stays</a>
                </div>
                <div class="product-grid">
                    @foreach ($stays as $product)
                        @include('components.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($farmProduce->isNotEmpty())
        <section class="section">
            <div class="container">
                <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: var(--space-2);">
                    <div>
                        <h2>Farm produce for resellers</h2>
                        <p style="color: var(--color-muted); max-width: 65ch;">
                            Bulk farm produce direct from farmers. A collector
                            can bring it in from the sub-division to a hub
                            district.
                        </p>
                    </div>
                    <a class="btn btn-secondary" href="{{ route('reseller.produce') }}">See all farm produce</a>
                </div>
                <div class="product-grid">
                    @foreach ($farmProduce as $product)
                        @include('components.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <h2>Browse without an account</h2>
            <p style="color: var(--color-muted); max-width: 65ch;">
                Buyers and resellers never need to register. Find what you need,
                book with a phone number, and settle directly with the seller.
            </p>
            <a class="btn btn-primary" href="{{ route('catalog') }}">Open the catalog</a>
        </div>
    </section>
@endsection
