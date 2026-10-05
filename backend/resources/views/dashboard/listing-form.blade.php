@extends('layouts.app')

@section('title', ($product ? 'Edit listing' : 'New listing').' - '.config('branding.name'))

@section('content')
    <section class="dash">
        <div class="container">
            <header class="dash-head">
                <div>
                    <div class="dash-eyebrow">
                        <span class="dash-role">Vendor</span>
                        @if ($product)
                            <span class="muted small">Editing “{{ $product->title }}”</span>
                        @endif
                    </div>
                    <h1>{{ $product ? 'Edit listing' : 'New listing' }}</h1>
                    <p class="dash-lede">
                        Published listings appear in the public catalog. Buyers
                        contact you directly — the platform takes no commission
                        and handles no payments.
                    </p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Back to dashboard</a>
                </div>
            </header>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif

            <form method="post"
                  action="{{ $product ? route('vendor.listings.update', $product) : route('vendor.listings.store') }}"
                  enctype="multipart/form-data">
                @csrf
                @if ($product)
                    @method('PUT')
                @endif

                <div class="dash-form">
                    <div class="dash-form-main">
                        <section class="dash-form-section">
                            <h2>The basics</h2>
                            <div class="dash-form-grid">
                                <div class="field field--full">
                                    <label for="listing-title">Title</label>
                                    <input id="listing-title" name="title" type="text" required
                                           maxlength="120" value="{{ old('title', $product?->title) }}"
                                           placeholder="e.g. Organic rice, 5 kg bag">
                                    @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field field--full">
                                    <label for="listing-description">Description</label>
                                    <textarea id="listing-description" name="description" rows="5"
                                              maxlength="5000"
                                              placeholder="What it is, quality, how buyers collect it">{{ old('description', $product?->description) }}</textarea>
                                    @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>

                        <section class="dash-form-section">
                            <h2>Category and pricing</h2>
                            <div class="dash-form-grid">
                                <div class="field">
                                    <label for="listing-category">What are you listing</label>
                                    <select id="listing-category" name="category" required>
                                        @foreach ($categories as $value => $label)
                                            <option value="{{ $value }}" @selected(old('category', $product?->category ?? 'agro') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field">
                                    <label for="listing-unit">Unit <span class="muted small">(kg, jar, dozen, night)</span></label>
                                    <input id="listing-unit" name="unit" type="text" maxlength="20"
                                           value="{{ old('unit', $product?->unit) }}">
                                </div>

                                <div class="field field--full">
                                    <label for="listing-price">Price <span class="muted small">(per unit, or per night for a rental)</span></label>
                                    <input id="listing-price" name="price" type="number" step="0.01" min="0"
                                           value="{{ old('price', $product?->price) }}" placeholder="0.00">
                                    @error('price')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field" data-listing-stock>
                                    <label for="listing-moq">Minimum order</label>
                                    <input id="listing-moq" name="moq" type="number" min="1"
                                           value="{{ old('moq', $product?->moq ?? 1) }}">
                                    @error('moq')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field" data-listing-stock>
                                    <label for="listing-stock">Stock <span class="muted small">(optional)</span></label>
                                    <input id="listing-stock" name="stock" type="number" min="0"
                                           value="{{ old('stock', $product?->stock) }}">
                                    @error('stock')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field" data-listing-rental>
                                    <label for="listing-from">Available from</label>
                                    <input id="listing-from" name="available_from" type="date"
                                           value="{{ old('available_from', $product?->available_from?->format('Y-m-d')) }}">
                                    @error('available_from')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>

                                <div class="field" data-listing-rental>
                                    <label for="listing-to">Available to</label>
                                    <input id="listing-to" name="available_to" type="date"
                                           value="{{ old('available_to', $product?->available_to?->format('Y-m-d')) }}">
                                    @error('available_to')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside class="dash-form-main dash-sticky">
                        <section class="dash-form-section">
                            <h2>Photos</h2>

                            @if ($product && collect($product->images ?? [])->isNotEmpty())
                                <div class="photo-grid" style="margin-bottom: var(--space-2);">
                                    @foreach ($product->images as $path)
                                        <label class="photo-option">
                                            <img src="{{ Storage::disk('public')->url($path) }}" alt="Listing photo">
                                            <span class="small">
                                                <input type="checkbox" name="remove_photos[]" value="{{ $path }}">
                                                Remove
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            <label class="dash-dropzone" for="listing-photos">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 8h.01"/><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -12"/><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/></svg>
                                <span>Choose photos</span>
                                <span class="small" data-photo-count>No photos selected yet</span>
                                <input id="listing-photos" name="photos[]" type="file"
                                       accept="image/jpeg,image/png,image/webp" multiple>
                            </label>
                            <p class="muted small" style="margin: var(--space-1) 0 0;">
                                JPEG, PNG or WebP, up to 2 MB each, 4 in total. The
                                server resizes them and converts them to WebP before publishing.
                            </p>
                            <label class="check-option" style="margin-top: var(--space-2);">
                                <input type="checkbox" name="image_public_consent" value="1"
                                       @checked(old('image_public_consent'))>
                                <span>I agree that these listing images will be publicly visible.</span>
                            </label>
                            @error('image_public_consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('photos')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @error('photos.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        </section>

                        <section class="dash-form-section">
                            <h2>Admin approval</h2>
                            <p>Every new listing and content change needs admin approval before publication.
                                Approved listings expire after one year and can be renewed.</p>
                            <input type="hidden" name="status" value="active">

                            <div class="dash-actions">
                                <button class="btn btn-primary" type="submit">Submit for admin approval</button>
                                <a class="btn btn-secondary" href="{{ route('dashboard') }}">Cancel</a>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        </div>
    </section>

    <script src="{{ \App\Support\AssetVersion::url('js/listing-form.js') }}" defer></script>
@endsection
