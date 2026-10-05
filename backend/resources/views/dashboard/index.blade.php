@extends('layouts.app')

@section('title', 'Dashboard - '.config('branding.name'))

@section('content')
    @php
        $roleLabel = match ($role) {
            'vendor' => 'Vendor',
            'driver' => 'Driver',
            'collector' => 'Collector',
            'skilled_worker' => 'Skilled worker',
            'volunteer' => 'Volunteer',
            default => \Illuminate\Support\Str::headline($role),
        };

        $lede = match ($role) {
            'vendor' => 'Manage your listings and shop details. Buyers contact you directly — no commission, no on-platform payments.',
            'driver' => 'Set your availability, review your base of operation and take delivery jobs.',
            'collector' => 'Pickup and delivery legs of logistics jobs, matched to your base.',
            'skilled_worker' => 'Your services and ground work in one place.',
            'volunteer' => 'Site visits, reports and the verified badge you sign your name to.',
            default => 'Your workspace.',
        };
    @endphp

    <section class="dash">
        <div class="container">
            <header class="dash-head">
                <div>
                    <div class="dash-eyebrow">
                        <span class="dash-role">{{ $roleLabel }}</span>
                        @if ($user->district)
                            <span class="muted small">{{ $user->district->name }}</span>
                        @endif
                    </div>
                    <h1>Welcome, {{ $user->name }}</h1>
                    <p class="dash-lede">{{ $lede }}</p>
                </div>
                <div class="dash-actions">
                    @if ($role === 'vendor')
                        <a class="btn btn-secondary" href="{{ route('dashboard.bookings') }}">Bookings</a>
                        <a class="btn btn-primary" href="{{ route('vendor.listings.create') }}">New listing</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-secondary" type="submit">Sign out</button>
                    </form>
                </div>
            </header>

            @if ($role === 'vendor')
                <div class="dash-grid dash-grid--stats">
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $products->count() }}</span>
                            <span class="dash-stat-label">Listings</span>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $products->where('status', 'active')->count() }}</span>
                            <span class="dash-stat-label">Live in the catalog</span>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $products->whereIn('status', ['draft', 'inactive'])->count() }}</span>
                            <span class="dash-stat-label">Drafts and paused</span>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $bookingsCount }}</span>
                            <span class="dash-stat-label">
                                <a href="{{ route('dashboard.bookings') }}">Bookings</a>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="dash-card dash-card--wide">
                    <div class="dash-card-head">
                        <h2>Sales</h2>
                        <a class="btn btn-secondary btn-sm" href="{{ route('dashboard.reports.sales') }}">Download PDF report</a>
                    </div>
                    <p class="muted small">
                        Completed orders only — you settle directly with buyers,
                        the platform holds no money.
                    </p>
                    <div class="dash-grid dash-grid--stats">
                        @foreach ($sales['periods'] as $period)
                            <div class="dash-stat">
                                <span class="dash-stat-value">{{ config('app.currency_symbol', 'Rs. ') }}{{ number_format($period['total'], 2) }}</span>
                                <span class="dash-stat-label">
                                    {{ $period['title'] }} · {{ $period['caption'] }}
                                    · {{ $period['count'] }} {{ \Illuminate\Support\Str::plural('order', $period['count']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                @include('dashboard._affiliate', ['referrals' => $referrals])

                <div class="dash-card dash-card--wide">
                    <div class="dash-card-head">
                        <h2>Farm-produce collections</h2>
                    </div>
                    <p class="muted small">
                        Ask a collector to bring a bulk farm-produce listing
                        from its sub-division to a hub district. The fee is
                        paid directly to the collector — the platform takes
                        nothing.
                    </p>

                    @if ($farmListings->isEmpty() || $hubs->isEmpty())
                        <div class="dash-empty">
                            <p>
                                @if ($hubs->isEmpty())
                                    No hub districts are set up yet. An administrator marks them.
                                @else
                                    Publish a farm-produce listing in the “Farm produce (reseller)” category first.
                                @endif
                            </p>
                        </div>
                    @else
                        <form method="post" action="{{ route('dashboard.collections.store') }}">
                            @csrf
                            <div class="dash-form-grid">
                                <div class="field field--full">
                                    <label for="collection-product">Farm-produce listing</label>
                                    <select id="collection-product" name="product_id" required>
                                        <option value="">Choose a listing</option>
                                        @foreach ($farmListings as $listing)
                                            <option value="{{ $listing->id }}" @selected((string) old('product_id') === (string) $listing->id)>
                                                {{ $listing->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('product_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="collection-hub">Hub district</label>
                                    <select id="collection-hub" name="destination_district_id" required>
                                        <option value="">Choose a hub</option>
                                        @foreach ($hubs as $hub)
                                            <option value="{{ $hub->id }}" @selected((string) old('destination_district_id') === (string) $hub->id)>{{ $hub->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('destination_district_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="collection-fee">Fee for the collector <span class="muted small">(₹, optional)</span></label>
                                    <input id="collection-fee" name="fee_inr" type="number" min="0" step="0.01"
                                           value="{{ old('fee_inr') }}" inputmode="decimal">
                                    @error('fee_inr')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <label for="collection-address">Pickup address or landmark <span class="muted small">(optional)</span></label>
                                    <input id="collection-address" name="address" type="text" maxlength="500"
                                           value="{{ old('address') }}">
                                    @error('address')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <button class="btn btn-primary" type="submit">Request collection</button>
                                </div>
                            </div>
                        </form>
                    @endif

                    @if ($collections->isNotEmpty())
                        <ul class="dash-list">
                            @foreach ($collections as $collection)
                                <li class="dash-row">
                                    <div class="dash-row-body">
                                        <div class="dash-row-title">
                                            {{ $collection->locality?->name ?? 'Farm' }}
                                            → {{ $collection->destinationDistrict?->name ?? 'hub' }}
                                        </div>
                                        <div class="dash-row-meta">
                                            Requested {{ $collection->created_at->format('d M Y') }}
                                            @if ($collection->fee_inr)
                                                &middot; ₹{{ number_format($collection->fee_inr, 2) }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="chip chip-{{ $collection->status }}">{{ \Illuminate\Support\Str::headline($collection->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="dash-card dash-card--wide">
                    <div class="dash-card-head">
                        <h2 id="my-listings">My listings</h2>
                        <a class="small" href="{{ route('vendor.listings.create') }}">Add another</a>
                    </div>

                    @if ($products->isEmpty())
                        <div class="dash-empty">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21l18 0"/><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"/><path d="M5 21l0 -10.15"/><path d="M19 21l0 -10.15"/><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"/></svg>
                            <p>No listings yet. Create your first listing and submit it for admin approval to appear in the catalog.</p>
                            <a class="btn btn-primary" href="{{ route('vendor.listings.create') }}">Create a listing</a>
                        </div>
                    @else
                        <ul class="dash-list">
                            @foreach ($products as $product)
                                <li class="dash-row">
                                    @if (collect($product->images ?? [])->isNotEmpty())
                                        <img class="dash-row-thumb"
                                             src="{{ Storage::disk('public')->url($product->images[0]) }}"
                                             alt="" loading="lazy">
                                    @else
                                        <span class="dash-row-thumb dash-row-thumb--empty" aria-hidden="true">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -12"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/></svg>
                                        </span>
                                    @endif
                                    <div class="dash-row-body">
                                        <div class="dash-row-title">
                                            @if ($product->isLive())
                                                <a href="{{ route('listing.show', $product) }}">{{ $product->title }}</a>
                                            @else
                                                {{ $product->title }}
                                            @endif
                                        </div>
                                        <div class="dash-row-meta">
                                            {{ \Illuminate\Support\Str::headline($product->category) }}
                                            @if ($product->price)
                                                &middot; ₹{{ number_format($product->price, 2) }}{{ $product->unit ? ' / '.$product->unit : '' }}
                                            @endif
                                            @if ($product->moq > 1)
                                                &middot; min {{ $product->moq }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="chip chip-{{ $product->status }}">{{ $product->canRenew() ? 'Expired' : ($product->status === 'pending' ? 'Awaiting admin approval' : \Illuminate\Support\Str::headline($product->status)) }}</span>
                                    @if ($product->deletion_scheduled_at && $product->status !== 'pending')
                                        <span class="muted small">Renew before deletion on {{ $product->deletion_scheduled_at->format('d M Y') }}</span>
                                    @elseif ($product->expires_at && $product->isLive())
                                        <span class="muted small">Expires {{ $product->expires_at->format('d M Y') }}</span>
                                    @endif
                                    <div class="dash-row-actions">
                                        <a class="btn btn-secondary btn-sm" href="{{ route('vendor.listings.edit', $product) }}">Edit</a>

                                        @if ($product->canRenew())
                                            <form method="post" action="{{ route('vendor.listings.renew', $product) }}">
                                                @csrf
                                                <button class="btn btn-primary" type="submit">Renew for admin review</button>
                                            </form>
                                        @elseif ($product->status === 'pending')
                                            <button class="btn btn-secondary" type="button" disabled>Awaiting approval</button>
                                        @elseif ($product->status === 'archived')
                                            <form method="post" action="{{ route('vendor.listings.status', $product) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="draft">
                                                <button class="btn btn-secondary btn-sm" type="submit">Restore</button>
                                            </form>
                                        @else
                                            @if ($product->status === 'active')
                                                <form method="post" action="{{ route('vendor.listings.status', $product) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button class="btn btn-secondary btn-sm" type="submit">Pause</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('vendor.listings.status', $product) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="active">
                                                    <button class="btn btn-primary" type="submit">Submit for approval</button>
                                                </form>
                                            @endif

                                            <form method="post" action="{{ route('vendor.listings.status', $product) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="archived">
                                                <button class="btn btn-secondary btn-sm" type="submit">Archive</button>
                                            </form>
                                        @endif
                                        @if ($product->isDeletionEligible())
                                            <form method="post" action="{{ route('vendor.listings.destroy', $product) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-secondary btn-sm" type="submit">Delete</button>
                                            </form>
                                        @elseif (in_array($product->status, ['inactive', 'archived'], true))
                                            <span class="muted small">Delete available after 7 days unpublished</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="dash-card dash-card--wide">
                    <div class="dash-card-head">
                        <h2>Shop profile</h2>
                        <div class="dash-tag-row">
                            @if ($vendor?->category)
                                <span class="dash-tag">{{ \Illuminate\Support\Str::headline($vendor->category) }}</span>
                            @endif
                            @if ($vendor?->district)
                                <span class="dash-tag">{{ $vendor->district->name }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="muted small">
                        This is what buyers see on your listings and vendor page.
                    </p>

                    <form method="post" action="{{ route('dashboard.vendor.profile') }}">
                        @csrf
                        @method('PUT')

                        <div class="dash-form-grid">
                            <div class="field">
                                <label for="vendor-name">Your name</label>
                                <input id="vendor-name" name="name" type="text" required
                                       maxlength="120" value="{{ old('name', $user->name) }}"
                                       autocomplete="name">
                                @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div class="field">
                                <label for="vendor-phone">Contact phone <span class="muted small">(optional)</span></label>
                                <input id="vendor-phone" name="phone" type="tel" maxlength="20"
                                       value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                                @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div class="field field--full">
                                <label for="vendor-display">Shop name</label>
                                <input id="vendor-display" name="display_name" type="text" required
                                       maxlength="120" value="{{ old('display_name', $vendor?->display_name) }}">
                                @error('display_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div class="field field--full">
                                <label for="vendor-description">Shop description <span class="muted small">(optional)</span></label>
                                <textarea id="vendor-description" name="vendor_description" rows="4"
                                          maxlength="2000"
                                          placeholder="What you sell and how you work">{{ old('vendor_description', $vendor?->description) }}</textarea>
                                @error('vendor_description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            </div>

                            <div class="field field--full">
                                <button class="btn btn-primary" type="submit">Save shop profile</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            @if ($role === 'driver')
                <div class="dash-grid">
                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2 id="my-status">Availability</h2>
                        </div>

                        <div class="dash-stat">
                            <span class="dash-stat-value {{ $availability?->is_online ? '' : 'muted' }}">
                                {{ $availability?->is_online ? 'Online' : 'Offline' }}
                            </span>
                            <span class="dash-stat-label">
                                @if ($availability?->is_online)
                                    You are in the matching pool for jobs and errands.
                                @elseif ($availability?->last_online_at)
                                    Last online {{ $availability->last_online_at->format('d M Y, H:i') }}.
                                @else
                                    Go online to start receiving jobs.
                                @endif
                            </span>
                        </div>

                        <form method="post" action="{{ route('dashboard.availability') }}" style="margin-top: var(--space-2);">
                            @csrf
                            @if ($availability?->is_online)
                                <input type="hidden" name="is_online" value="0">
                                <button class="btn btn-secondary" type="submit">Go offline</button>
                            @else
                                <input type="hidden" name="is_online" value="1">
                                <button class="btn btn-primary" type="submit">Go online</button>
                            @endif
                        </form>
                    </div>

                    <div class="dash-card">
                        <div class="dash-card-head">
                            <h2>Base of operation</h2>
                            @if ($base)
                                <span class="small muted">Currently {{ $base->district->name }}</span>
                            @endif
                        </div>

                        @if ($districts->isEmpty())
                            <div class="dash-empty">
                                <p>No service areas are configured yet. An administrator sets up districts and localities.</p>
                            </div>
                        @else
                            <p class="muted small">
                                Choose your district and up to five localities. Jobs in
                                those areas are offered to you first.
                            </p>

                            <form method="post" action="{{ route('dashboard.driver.base') }}">
                                @csrf
                                @method('PUT')

                                <div class="field">
                                    <label for="base-district">District</label>
                                    <select id="base-district" name="district_id" required>
                                        <option value="">Choose a district</option>
                                        @foreach ($districts as $district)
                                            <option value="{{ $district->id }}" @selected((string) old('district_id', $base?->district_id) === (string) $district->id)>{{ $district->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('district_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label>Localities <span class="muted small">(up to 5)</span></label>
                                    <div class="check-grid">
                                        @foreach ($districts as $district)
                                            @foreach ($district->localities as $locality)
                                                <label class="check-option" data-locality-district="{{ $district->id }}">
                                                    <input type="checkbox" name="locality_ids[]" value="{{ $locality->id }}"
                                                           @checked(in_array($locality->id, old('locality_ids', $base ? $base->localities->modelKeys() : [])))>
                                                    <span>{{ $locality->name }}</span>
                                                </label>
                                            @endforeach
                                        @endforeach
                                    </div>
                                    <p class="muted small" data-locality-empty hidden>No active localities in that district yet.</p>
                                    @error('locality_ids')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                    @error('locality_ids.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <button class="btn btn-primary" type="submit">Save base</button>
                            </form>
                        @endif
                    </div>

                    <div class="dash-card dash-card--wide">
                        <div class="dash-card-head">
                            <h2>Work you provide</h2>
                            <span class="small muted">Shown in the public transport directory</span>
                        </div>
                        <p class="muted small">
                            Tick the transport and errand work you take. Your phone
                            number is shown in the public directory so buyers can
                            call you directly.
                        </p>

                        <form method="post" action="{{ route('dashboard.driver.profile') }}">
                            @csrf
                            @method('PUT')

                            <div class="dash-form-grid">
                                <div class="field">
                                    <label for="driver-vehicle">Vehicle category</label>
                                    <select id="driver-vehicle" name="vehicle_category" required>
                                        <option value="">Choose a vehicle category</option>
                                        @foreach (\App\Models\User::VEHICLE_CATEGORIES as $value => $label)
                                            <option value="{{ $value }}" @selected(old('vehicle_category', $user->vehicle_category) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="muted small">Choose your vehicle before appearing in Transport &amp; errands.</p>
                                    @error('vehicle_category')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="driver-name">Your name</label>
                                    <input id="driver-name" name="name" type="text" required
                                           maxlength="120" value="{{ old('name', $user->name) }}"
                                           autocomplete="name">
                                    @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="driver-phone">Contact phone <span class="muted small">(shown publicly)</span></label>
                                    <input id="driver-phone" name="phone" type="tel" maxlength="20"
                                           value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                                    @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <label>Transport &amp; errand work <span class="muted small">(tick all that apply)</span></label>
                                    <div class="check-grid">
                                        @forelse ($transportCategories as $category)
                                            <label class="check-option">
                                                <input type="checkbox" name="transport_category_ids[]" value="{{ $category->id }}"
                                                       @checked(in_array($category->id, old('transport_category_ids', $user->transportCategories->modelKeys())))>
                                                <span>{{ $category->name }}</span>
                                            </label>
                                        @empty
                                            <p class="muted small">No categories have been set up yet.</p>
                                        @endforelse
                                    </div>
                                    @error('transport_category_ids')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                    @error('transport_category_ids.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <button class="btn btn-primary" type="submit">Save work profile</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @include('dashboard._affiliate', ['referrals' => $referrals])

                <script src="{{ \App\Support\AssetVersion::url('js/driver-base.js') }}" defer></script>
            @endif

            @if ($role === 'volunteer')
                <div class="dash-grid dash-grid--stats">
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $verifications->count() }}</span>
                            <span class="dash-stat-label">Reports filed</span>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $verifications->where('status', 'approved')->count() }}</span>
                            <span class="dash-stat-label">Approved visits</span>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-stat">
                            <span class="dash-stat-value">{{ $verifications->where('status', 'submitted')->count() }}</span>
                            <span class="dash-stat-label">Awaiting review</span>
                        </div>
                    </div>
                </div>

                <div class="dash-card dash-card--wide">
                    <div class="dash-card-head">
                        <h2 id="my-visits">My visits</h2>
                        @if ($volunteer?->availability)
                            <span class="small muted">Availability: {{ $volunteer?->availability ?? 'Not set yet' }}</span>
                        @endif
                    </div>

                    @if ($verifications->isEmpty())
                        <div class="dash-empty">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0"/></svg>
                            <p>No reports yet. File site-visit reports from the app after each visit.</p>
                        </div>
                    @else
                        <ul class="dash-list">
                            @foreach ($verifications as $verification)
                                <li class="dash-row">
                                    <span class="dash-row-thumb dash-row-thumb--empty" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2"/><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z"/><path d="M9 12l2 2l4 -4"/></svg>
                                    </span>
                                    <div class="dash-row-body">
                                        <div class="dash-row-title">
                                            {{ str_replace('App\\Models\\', '', $verification->subject_type) }} #{{ $verification->subject_id }}
                                        </div>
                                        <div class="dash-row-meta">Filed {{ $verification->created_at->format('d M Y') }}</div>
                                    </div>
                                    <span class="chip chip-{{ $verification->status }}">{{ \Illuminate\Support\Str::headline($verification->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($role === 'skilled_worker')
                <div class="dash-grid">
                    <div class="dash-card dash-card--wide">
                        <div class="dash-card-head">
                            <h2 id="my-profile">My profile</h2>
                            <div class="dash-tag-row">
                                <span class="dash-tag">{{ $roleLabel }}</span>
                                @if ($user->district)
                                    <span class="dash-tag">{{ $user->district->name }}</span>
                                @endif
                            </div>
                        </div>
                        <p class="muted small">
                            Keep your details current — this is what buyers and
                            coordinators see when matching work to you.
                        </p>

                        <form method="post" action="{{ route('dashboard.worker.profile') }}">
                            @csrf
                            @method('PUT')

                            <div class="dash-form-grid">
                                <div class="field">
                                    <label for="worker-name">Your name</label>
                                    <input id="worker-name" name="name" type="text" required
                                           maxlength="120" value="{{ old('name', $user->name) }}"
                                           autocomplete="name">
                                    @error('name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="worker-phone">Phone <span class="muted small">(optional)</span></label>
                                    <input id="worker-phone" name="phone" type="tel" maxlength="20"
                                           value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                                    @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <label>Work you provide <span class="muted small">(tick all that apply)</span></label>
                                    <div class="check-grid">
                                        @forelse ($skillCategories as $category)
                                            <label class="check-option">
                                                <input type="checkbox" name="skill_category_ids[]" value="{{ $category->id }}"
                                                       @checked(in_array($category->id, old('skill_category_ids', $workerProfile ? $workerProfile->skillCategories->modelKeys() : [])))>
                                                <span>{{ $category->name }}</span>
                                            </label>
                                        @empty
                                            <p class="muted small">No categories have been set up yet.</p>
                                        @endforelse
                                    </div>
                                    @error('skill_category_ids')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                    @error('skill_category_ids.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <label for="worker-services">Other work you provide <span class="muted small">(optional)</span></label>
                                    <textarea id="worker-services" name="services" rows="4"
                                              maxlength="2000"
                                              placeholder="Anything not in the list above, in your own words">{{ old('services', $workerProfile?->services) }}</textarea>
                                    @error('services')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <button class="btn btn-primary" type="submit">Save profile</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($role === 'collector')
                <div class="dash-grid">
                    <div class="dash-card dash-card--wide">
                        <div class="dash-card-head">
                            <h2 id="my-profile">My profile</h2>
                            <div class="dash-tag-row">
                                <span class="dash-tag">{{ $roleLabel }}</span>
                                @if ($user->district)
                                    <span class="dash-tag">{{ $user->district->name }}</span>
                                @endif
                            </div>
                        </div>
                        <p><strong>{{ $user->name }}</strong></p>
                        @if ($user->phone)
                            <p class="muted small">Phone: {{ $user->phone }}</p>
                        @endif
                        <div class="dash-empty">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M5 17h-2v-4m-1 -8h11v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"/><path d="M3 9l4 0"/></svg>
                            <p class="muted">Collector work is managed in the app — jobs matched to your base appear there.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
