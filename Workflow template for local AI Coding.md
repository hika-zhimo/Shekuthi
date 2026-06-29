# MultiVendor Delivery — Platform Architecture

> Production-ready specification | Laravel 11 + MySQL | Japandi Design System
> Generated: 2026-06-29

---

- [root] **MultiVendor Delivery**
  _Notes: Complete multi-vendor delivery marketplace platform. Customers browse vendors, order products, track delivery in real-time. Vendors manage inventory and orders. Admin oversees compliance._
  _Comment: Japandi design — warm neutrals, soft borders, micro-animations, wabi-sabi spacing._

  - [t1] **Tech Stack**
    _Notes: Production-grade Laravel 11, MySQL 8, Bootstrap 5 + custom Japandi layer._
    Markers: stack

    - [t1a] **Backend**
      _Notes: Laravel 11 with strict typing, Eloquent ORM, queue jobs, broadcasting._
      _Comment: PHP 8.2+, Sanctum auth_
      - [t1a1] **PHP 8.2+ / Laravel 11**
      - [t1a2] **MySQL 8 with full-text search**
      - [t1a3] **Redis for cache + queue + broadcast**
      - [t1a4] **Laravel Sanctum (API tokens + SPA auth)**
      - [t1a5] **Laravel Socialite (Google/Apple OAuth)**

    - [t1b] **Frontend**
      _Notes: Blade templates with Alpine.js for interactivity._
      _Comment: No heavy JS framework — SSR-first_
      - [t1b1] **Blade + components**
      - [t1b2] **Alpine.js (reactive widgets)**
      - [t1b3] **Bootstrap 5 (grid + utilities)**
      - [t1b4] **Custom Japandi CSS layer**
      - [t1b5] **Vite (build tool)**

    - [t1c] **Infrastructure**
      - [t1c1] **Laravel Horizon (queue monitoring)**
      - [t1c2] **Laravel Telescope (debug locally)**
      - [t1c3] **Spatie Media Library (image management)**
      - [t1c4] **Midtrans / Xendit (payment gateway)**
      - [t1c5] **Google Maps API (geolocation + distance)**

  - [t2] **Database Schema**
    _Notes: 11 core tables + standard Laravel tables. All timestamps + soft deletes._
    _Comment: Migration files at database/migrations/_

    - [t2a] **Users**
      _Notes: Customers, vendors, admins, drivers. Polymorphic roles._
      _Comment: Extends Laravel auth_
      - [t2a1] **Field** `role`: enum(customer, vendor, admin, driver)
      - [t2a2] **Field** `phone`, `address`, `city`, `province`, `postal_code`
      - [t2a3] **Field** `latitude`, `longitude` (geolocation)
      - [t2a4] **Field** `avatar` (Spatie Media Library)
      - [t2a5] **Field** `is_active` (soft block)

    - [t2b] **Vendors**
      _Notes: One vendor per user. Verified before going live._
      _Comment: Slug unique_
      - [t2b1] **FK** `user_id`
      - [t2b2] **Field** `store_name`, `slug`, `description`
      - [t2b3] **Field** `logo`, `cover_image`
      - [t2b4] **Field** `status`: pending→verified→suspended|rejected
      - [t2b5] **Field** `rating`, `total_products`, `total_orders`
      - [t2b6] **Field** `commission_rate` (default 10%)
      - [t2b7] **Field** `delivery_zones` (JSON polygon)
      - [t2b8] **Field** `business_hours` (JSON weekday schedule)
      - [t2b9] **Field** `is_open` (online/offline toggle)
      - [t2b10] **Field** `verified_at` timestamp

    - [t2c] **Categories**
      _Notes: Hierarchical (parent_id self-referential)._
      - [t2c1] **Field** `name`, `slug`, `description`
      - [t2c2] **Field** `icon`, `image`
      - [t2c3] **Field** `parent_id` nullable self-reference
      - [t2c4] **Field** `sort_order`, `is_active`

    - [t2d] **Products**
      _Notes: Each product belongs to one vendor + optional category._
      _Comment: Max 100 products per vendor (configurable)_
      - [t2d1] **FK** `vendor_id`, `category_id`
      - [t2d2] **Field** `name`, `slug` (unique per vendor)
      - [t2d3] **Field** `description`, `short_description`
      - [t2d4] **Field** `price`, `compare_price` (original price strikethrough)
      - [t2d5] **Field** `images` (JSON array), `thumbnail`
      - [t2d6] **Field** `variants` (JSON: size, color, etc.)
      - [t2d7] **Field** `addons` (JSON: extra charge options)
      - [t2d8] **Field** `stock`, `min_order`, `unit` (pcs, kg, etc.)
      - [t2d9] **Field** `status`: active / inactive / draft
      - [t2d10] **Field** `rating`, `total_sold`, `preparation_time`
      - [t2d11] **Index** composite (vendor_id, status) + (category_id, status)

    - [t2e] **Orders**
      _Notes: Split by vendor — each vendor gets own order row._
      _Comment: order_number = MVD + YYYYMMDD + 6-digit random_
      - [t2e1] **FK** `user_id`, `vendor_id`
      - [t2e2] **Field** `order_number` unique
      - [t2e3] **Field** `status` lifecycle: pending → confirmed → preparing → ready_for_pickup → in_delivery → delivered → completed
      - [t2e4] **Field** Cancellation: `cancelled`, `refunded`
      - [t2e5] **Field** `subtotal`, `delivery_fee`, `service_fee`, `tax`, `discount`, `total`
      - [t2e6] **Field** `note` (customer note to vendor)
      - [t2e7] **Field** Shipping: `name`, `phone`, `address`, `city`, `province`, `postal_code`, `lat`, `lng`
      - [t2e8] **Field** `payment_method`, `payment_status`
      - [t2e9] **Field** Timestamps: `paid_at`, `cancelled_at`, `delivered_at`, `completed_at`

    - [t2f] **Order Items**
      - [t2f1] **FK** `order_id`, `product_id`
      - [t2f2] **Field** `product_name` (snapshot), `price`, `quantity`
      - [t2f3] **Field** `variants`, `addons`, `note` (per item)
      - [t2f4] **Field** `subtotal` (price * quantity + addons)

    - [t2g] **Reviews**
      _Notes: One review per user per product per order (unique constraint)._
      - [t2g1] **FK** `user_id`, `product_id`, `order_id`
      - [t2g2] **Field** `rating` 1-5, `comment`, `images`
      - [t2g3] **Field** `status`: pending / approved / rejected

    - [t2h] **Carts**
      _Notes: Pre-checkout storage. One row per product per user._
      - [t2h1] **FK** `user_id`, `product_id`
      - [t2h2] **Field** `quantity`, `variants`, `addons`, `note`

    - [t2i] **Payments**
      _Notes: Transaction log for each payment attempt._
      - [t2i1] **FK** `order_id`
      - [t2i2] **Field** `method`, `status`, `amount`
      - [t2i3] **Field** `transaction_id` (from gateway), `reference`, `channel`
      - [t2i4] **Field** `metadata` (JSON: gateway raw response)

    - [t2j] **Deliveries**
      _Notes: Real-time tracking per order._
      - [t2j1] **FK** `order_id`, `driver_id` (nullable)
      - [t2j2] **Field** `status`: pending → assigned → picked_up → in_transit → delivered
      - [t2j3] **Field** `distance_km`, `delivery_fee`
      - [t2j4] **Field** `tracking_history` (JSON array of waypoints)
      - [t2j5] **Field** `proof_of_delivery` (JSON: recipient photo, signature)

  - [t3] **Role System**
    _Notes: Three dashboards — Customer, Vendor, Admin. Middleware gates._
    _Comment: Middleware classes: RoleMiddleware, VendorMiddleware_

    - [t3a] **Customer**
      - [t3a1] Browse shop by category, vendor, search
      - [t3a2] Add to cart, checkout, pay
      - [t3a3] Track order status in real-time
      - [t3a4] Leave reviews with photos
      - [t3a5] Manage profile, address, order history

    - [t3b] **Vendor**
      _Notes: Separate dashboard at /vendor_
      - [t3b1] Dashboard with sales chart + KPIs
      - [t3b2] Product CRUD with image upload
      - [t3b3] Order management: confirm, prepare, mark ready
      - [t3b4] Set open/closed status
      - [t3b5] View earnings, withdrawal requests

    - [t3c] **Admin**
      _Notes: Super-admin panel at /admin_
      - [t3c1] Dashboard: total users, vendors, orders, revenue
      - [t3c2] Vendor verification + suspension
      - [t3c3] Order overview with status filters
      - [t3c4] Review moderation (approve/reject)
      - [t3c5] Category management

  - [t4] **Japandi Design System**
    _Notes: Warm minimalism inspired by Japanese + Scandinavian design._
    _Comment: All colors use CSS custom properties_
    Markers: design

    - [t4a] **Color Palette**
      _Notes: Earth tones + muted accents. No pure black/white._
      _Comment: WCAG AA compliant contrast_
      - [t4a1] `--j-white`: #F5F0EB (warm off-white)
      - [t4a2] `--j-cream`: #EDE4D9 (cream)
      - [t4a3] `--j-stone`: #D4C9BC (stone gray)
      - [t4a4] `--j-clay`: #C4A882 (warm clay)
      - [t4a5] `--j-tea`: #8FAA8B (sage green)
      - [t4a6] `--j-ink`: #3A3226 (dark brown)
      - [t4a7] `--j-text`: #4A4238 (body text)
      - [t4a8] `--j-muted`: #8C8273 (muted text)
      - [t4a9] `--j-accent`: #C17F59 (terracotta accent)
      - [t4a10] `--j-border`: #DED5C8 (soft border)

    - [t4b] **Typography**
      _Notes: System font stack for performance. Japanese-ready._
      _Comment: 1.5 line-height, 75% max-width for readability_
      - [t4b1] Font: `"Inter", "Noto Sans JP", system-ui, sans-serif`
      - [t4b2] Scale: 0.75 / 0.875 / 1 / 1.125 / 1.25 / 1.5 / 2 / 2.5 (rem)
      - [t4b3] Heading weight: 400 (normal) — Japandi is understated
      - [t4b4] Body: 0.9375rem, line-height 1.7

    - [t4c] **Soft Borders**
      _Notes: All cards, modals, inputs use subtle border styling._
      _Comment: Border radius follows wabi-sabi organic feel_
      - [t4c1] `border-radius: 16px` on cards
      - [t4c2] `border: 1px solid var(--j-border)`
      - [t4c3] `box-shadow: 0 2px 12px rgba(58, 50, 38, 0.06)` (subtle ink shadow)
      - [t4c4] `box-shadow` on hover: 0 4px 20px rgba(58, 50, 38, 0.10)
      - [t4c5] Inputs: border-radius 12px, inner focus glow

    - [t4d] **Micro-animations**
      _Notes: Purposeful, gentle motion. 200-300ms, easing curves._
      _Comment: prefers-reduced-media respected_
      - [t4d1] Card hover: translateY(-2px) + shadow transition (200ms ease-out)
      - [t4d2] Button hover: background darken 5%, subtle scale(1.02)
      - [t4d3] Page transitions: fade-in + slide-up (300ms)
      - [t4d4] Loading skeleton: shimmer pulse animation
      - [t4d5] Cart badge: scale bounce on add (+50%, then spring back)
      - [t4d6] Toast notifications: slide-in from right, fade-out
      - [t4d7] Modal: backdrop fade (200ms) + content scale(0.95→1)
      - [t4d8] Accordion: max-height transition with smooth expand

    - [t4e] **Spacing**
      _Notes: Wabi-sabi — generous white space, breathing room._
      - [t4e1] Base unit: 0.25rem (4px)
      - [t4e2] Section padding: 4rem / 6rem on desktop
      - [t4e3] Card padding: 1.5rem
      - [t4e4] Grid gap: 1.5rem

  - [t5] **Page Specifications**
    _Notes: Complete page-by-page spec. Every view in the application._
    _Comment: 30+ pages across 4 role scopes — guest, customer, vendor, admin_
    Markers: pages

    - [t5a] **Guest Pages**
      _Notes: Pages accessible without authentication._

      - [t5a1] **Home / Landing Page**
        _Notes: Route: `home` | URL: `/` | Controller: `HomeController@index`_
        _Comment: First impression. Japandi hero, featured content, soft entrance animation._
        - **Hero section**: Full-width warm clay background. Store name + tagline "Discover Local, Delivered Fresh". CTA buttons: "Browse Vendors" / "Start Selling". Fade-in scale animation on load (300ms).
        - **Category grid**: 2×5 grid of category cards. Each card: circular icon + name + subtle hover lift (translateY(-3px), 200ms). Click → `/shop/category/{slug}`.
        - **Featured vendors**: Horizontal scroll section. 4 vendor cards showing logo, name, rating stars, location. "Open" badge if `is_open` true. Scroll snap, arrow buttons.
        - **How it works**: 3-step illustration: Browse → Order → Enjoy. Japandi line-art icons, centered text.
        - **Popular products**: 2×3 grid. Card: thumbnail (aspect-ratio 1:1), name, price, vendor name, min-order badge. Fade-in on scroll (IntersectionObserver, 200ms stagger).
        - **Footer CTA**: "Ready to grow your business?" → Vendor register link.
        - **Data loaded**: 8 categories (is_active), 6 vendors (verified, highest total_orders), 6 products (is_featured, active).
        - **Animations**: Hero scale-up 300ms ease-out. Cards stagger fade-up 100ms each. Scroll-triggered sections 200ms.

      - [t5a2] **Shop / Product Listing**
        _Notes: Route: `shop.index` | URL: `/shop` | Controller: `ShopController@index`_
        _Comment: Main discovery page. Filterable, sortable, paginated grid._
        - **Header**: Title "Shop" + result count + active filter chips.
        - **Sidebar filters** (desktop) / offcanvas (mobile):
          - Category: checkbox list with product count badge.
          - Price range: min/max input fields (IDR formatting).
          - City: dropdown (vendor cities).
          - Rating: clickable star rows (4+, 3+, etc).
          - Sort: dropdown — Relevance, Price Low→High, Price High→Low, Newest, Rating.
        - **Product grid**: 3-col desktop, 2-col tablet, 1-col mobile. Card:
          - Image (1:1 lazyload). Hover: subtle scale(1.03) + shadow deepen (200ms).
          - Wishlist heart icon (top-right, toggle).
          - Name, price (IDR formatted). Compare_price strikethrough if present.
          - Vendor name link, star rating.
          - Stock indicator: "In Stock" (green) / "Low Stock" (amber, <5) / "Out of Stock" (gray, muted).
          - "Add to Cart" button (appears on hover/always on mobile).
        - **Pagination**: Centered page numbers. Previous/Next disabled at bounds. Max 7 page slots with ellipsis.
        - **Empty state**: "No products found" illustration + clear filters button.
        - **Data loaded**: Paginated products (12 per page), categories with counts, city list, min/max price bounds from `marketplace.php`.
        - **Animations**: Sidebar slide-in (200ms). Card stagger 80ms. Filter chip fade 150ms. Page transition fade-slide 250ms.

      - [t5a3] **Product Detail**
        _Notes: Route: `shop.product.show` | URL: `/product/{product:slug}` | Controller: `ShopController@show`_
        _Comment: Full product page with gallery, variants, reviews, vendor card._
        - **Image gallery**: Main image (large, 4:3). Thumbnail row below. Click thumbnail → swap main with crossfade (200ms). Lightbox on main click.
        - **Product info** (right column):
          - Name, rating stars + count, total_sold.
          - Price (large, bold) + compare_price strikethrough if any.
          - Short description (max 3 lines, "read more" expand).
          - Variant selector: if variants JSON exists → pill buttons for each option (size, color, etc.). Selected state: `--j-tea` background, white text.
          - Addon checkboxes: optional extras + price. Selected → subtotal updates via Alpine.js.
          - Quantity input: minus/+ buttons with numeric display. Min/max enforced.
          - "Add to Cart" button (full width). Adds with bounce animation on cart icon.
          - Vendor info card: logo, store name, location, "View Store" link.
        - **Description tab**: Full description rendered from Markdown or plain text.
        - **Reviews section**:
          - Rating summary bar: 5-row horizontal bar chart (5★ to 1★) with % bars.
          - Review cards: avatar, name, date, rating, comment, optional images. Paginated (5 per page).
          - "Write Review" button (shown if order completed and not yet reviewed).
        - **Related products**: 4-card row from same vendor/category.
        - **Data loaded**: Single product with vendor, approved reviews with user, related products (limit 4).
        - **Animations**: Gallery crossfade 200ms. Variant selection scale(1.05) then back. Reviews stagger 100ms. Add-to-cart ripple on button.

      - [t5a4] **Vendor Listing**
        _Notes: Route: `vendors.index` | URL: `/vendors` | Controller: `ShopController@vendors`_
        _Comment: Browse all verified vendors. Search + filter._
        - **Header**: "Our Vendors" title + search input (search by store_name).
        - **Vendor cards**: 3-col grid. Card:
          - Cover image (16:9 aspect ratio, lazy load).
          - Logo (circle, 64px, centered overlap on cover bottom).
          - Store name, location (city), rating stars.
          - Product count + "View Store" link.
          - "Open" badge (green dot + text) if `is_open`.
        - **Search**: Live search with debounce 300ms. Results replace grid.
        - **Filters**: City dropdown, minimum rating.
        - **Pagination**: 20 per page.
        - **Empty state**: "No vendors match your search."
        - **Animations**: Card hover lift 200ms. Search results fade-replace 150ms.

      - [t5a5] **Vendor Store Page**
        _Notes: Route: `shop.vendor` | URL: `/shop/vendor/{vendor}` | Controller: `ShopController@byVendor`_
        _Comment: Single vendor storefront with all their products._
        - **Store header**:
          - Cover image (full width, 21:9, overlay gradient).
          - Logo circle (80px) + store name + rating + total orders badge.
          - Description (expandable).
          - "Open" / "Closed" status indicator.
          - Business hours accordion (JSON → readable weekday schedule).
          - Location with "Get Directions" (Google Maps link).
        - **Category tabs**: Row of category pills unique to this vendor's products. Active tab highlighted. Click → filter products.
        - **Product grid**: Same card as Shop listing (t5a2). Filtered by vendor.
        - **Sort by**: Price, rating, newest.
        - **Animation**: Tab switch fade 150ms. Product reflow smooth.

      - [t5a6] **Search Results**
        _Notes: Route: `shop.index?q=` | URL: `/shop?q=term` | Controller: `ShopController@index`_
        _Comment: Full-text search across products._
        - **Header**: "Search results for \"{term}\"" + count.
        - **Product grid**: Same as shop listing. Filtered by MATCH AGAINST in MySQL.
        - **Suggestion**: "Did you mean?" spelling correction (optional, Levenshtein).
        - **No results**: "No products found for \"{term}\". Try browsing categories." with category links.
        - **Animations**: Results fade-in 200ms.

      - [t5a7] **Login**
        _Notes: Route: `login` | URL: `/login` | Controller: `LoginController`_
        _Comment: Japandi-styled login card. Centered layout._
        - **Card**: Centered max-w-md card on `--j-cream` background.
        - **Logo + title**: App logo + "Welcome back" heading.
        - **Form fields**: Email (type=email, autocomplete), Password (toggle show/hide icon).
        - **Remember me**: Checkbox with Japandi toggle style.
        - **Submit**: Full-width button "Sign In". Loading spinner on submit.
        - **Links**: "Forgot password?", "Don't have an account? Register", "Become a vendor".
        - **Social login**: Google / Apple buttons (if Socialite configured). Divider "or".
        - **Animation**: Card fade-slide-up 300ms. Form fields stagger 80ms. Button loading state.

      - [t5a8] **Register**
        _Notes: Route: `register` | URL: `/register` | Controller: `RegisterController`_
        _Comment: Customer registration._
        - **Card**: Same layout as login.
        - **Form fields**: Name, Email, Phone, Password, Confirm Password.
        - **Terms**: Checkbox "I agree to Terms & Privacy Policy".
        - **Submit**: "Create Account" button.
        - **Link**: "Already have an account? Sign In".
        - **Validation**: Real-time inline error messages below fields. Server-side + client-side.
        - **Post-register**: Auto-login + redirect to home with welcome toast.
        - **Animation**: Card fade-slide-up 300ms.

      - [t5a9] **Vendor Registration**
        _Notes: Route: `vendor.register` | URL: `/vendor/register` | Controller: `VendorRegisterController`_
        _Comment: Two-step form. First create account, then apply as vendor._
        - **Step 1**: Same as customer Register (t5a8). If already logged in as customer, skip to step 2.
        - **Step 2**: Vendor details form:
          - Store name (input, slug preview).
          - Description (textarea, char count).
          - Phone, Email (pre-filled from account).
          - Address fields: address, city, province, postal_code.
          - Business license upload (file input, accept images+PDF, max 2MB).
          - Bank info: account name, account number, bank name (select dropdown).
          - Business hours: weekday toggles + open/close timepickers (Alpine.js dynamic rows).
        - **Submit**: "Apply as Vendor" button.
        - **Success**: "Application submitted! We'll review within 1-2 business days." with email confirmation note.
        - **Animation**: Step transition slide 250ms. Form field focus glow 200ms.

    - [t5b] **Customer Pages**
      _Notes: Accessible after login. Role: customer._

      - [t5b1] **Cart**
        _Notes: Route: `cart.index` | URL: `/cart` | Controller: `CartController@index`_
        _Comment: Full cart view. Grouped by vendor for multi-vendor checkout._
        - **Header**: "Your Cart" + item count + "Clear All" button (with confirm dialog).
        - **Group by vendor**: Each vendor section has:
          - Vendor name + logo (small) + link to store.
          - Items: image (48px square), name, variant selection text, price, quantity selector (minus/input/plus), line total, remove button (trash icon).
          - Quantity change: Alpine.js live update via PATCH `/cart/update/{cart}`. Debounce 300ms.
        - **Cart summary sidebar**: Subtotal, delivery fee estimate, service fee, tax, total.
        - **Empty state**: "Your cart is empty" illustration + "Browse Shop" button.
        - **Checkout button**: "Proceed to Checkout" full-width (disabled if cart empty). Redirects to `/checkout`.
        - **Animations**: Item remove slide-out 200ms + fade. Quantity badge bounce 150ms. Summary live-update fade 100ms.

      - [t5b2] **Checkout**
        _Notes: Route: `checkout.index` | URL: `/checkout` | Controller: `CheckoutController@index`_
        _Comment: Order review + address + payment selection._
        - **Left column (order summary)**:
          - Grouped by vendor. Each vendor section: collapsed accordion header showing vendor name + item count + subtotal. Expand to see items.
          - Product image (tiny), name, quantity, price per item.
        - **Right column (form)**:
          - **Shipping address**: Pre-filled from user profile. Editable inline. Fields: name, phone, address, city, province, postal_code. Map picker for lat/lng (Google Maps).
          - **Note to vendor**: Optional textarea per vendor section.
          - **Payment method**: Radio cards — "Cash on Delivery", "Bank Transfer", "Midtrans". Selected card has Japandi accent border. Midtrans shows client key info for Snap popup.
          - **Order summary**: Subtotal, delivery fee, service fee, tax, total (large, accent color).
        - **Submit**: "Place Order" button. Multiple clicks prevented. Loading state with spinner.
        - **Validation**: Address required. Payment method required. Stock re-checked on submit.
        - **Animation**: Accordion expand 250ms max-height. Payment card select border transition 150ms. Submit button ripple.

      - [t5b3] **Order Success**
        _Notes: Route: `checkout.success` | URL: `/checkout/success/{order}` | Controller: `CheckoutController@success`_
        _Comment: Post-payment confirmation._
        - **Success icon**: Large green checkmark circle (animated draw SVG 500ms).
        - **Order number**: "Order #{order_number}" (copy to clipboard button).
        - **Status tracker**: 4-step timeline (Confirmed → Prepared → In Delivery → Delivered). Current step highlighted. Pending steps muted.
        - **Order details**: Vendor name, items summary, total, delivery address.
        - **Action buttons**: "Track Order" (→ `/orders/{order}`), "Continue Shopping" (→ `/shop`).
        - **Animation**: Checkmark draw 500ms. Timeline step glow 300ms stagger. Card fade-in 200ms.

      - [t5b4] **Order History**
        _Notes: Route: `orders.index` | URL: `/orders` | Controller: `OrderController@index`_
        _Comment: All customer orders. Filterable by status._
        - **Header**: "My Orders" + status filter tabs: All, Pending, In Delivery, Completed, Cancelled.
        - **Order cards** (list, not grid):
          - Order number + date (top right).
          - Vendor name + logo.
          - Product thumbnails (up to 3, then "+N more" badge).
          - Total amount.
          - Status badge: colored pill (pending=amber, preparing=blue, delivered=green, cancelled=gray).
          - Actions: "View Details", "Track", "Review" (if completed and not reviewed).
        - **Pagination**: 10 per page.
        - **Empty state**: "No orders yet" + "Browse Shop" CTA.
        - **Animation**: Filter tab switch 150ms. Card list stagger 80ms.

      - [t5b5] **Order Detail**
        _Notes: Route: `orders.show` | URL: `/orders/{order}` | Controller: `OrderController@show`_
        _Comment: Full order with real-time status and tracking._
        - **Header**: Order number, date, status badge. Back button to order history.
        - **Status timeline**: Vertical timeline with all statuses. Current status highlighted with dot + text. Completed steps have checkmark. ETA shown if in delivery.
        - **Delivery tracking** (if `in_delivery` or beyond):
          - Map component (Google Maps) showing vendor location → driver location → delivery address. Polyline route.
          - Driver info: name, phone (call button), photo, vehicle type.
          - Live ETA countdown.
        - **Order items**: Table/image list with product name, quantity, price per item, subtotal.
        - **Payment info**: Method, amount, status badge.
        - **Address**: Shipping address card.
        - **Actions**:
          - "Cancel Order" button (only if status=pending, 30min window). Confirm dialog.
          - "Confirm Received" button (only if status=delivered). Completes the order.
          - "Write Review" link per product (only if status=completed and no review exists).
        - **Animation**: Timeline dot pulse 2s infinite (current status). Map fade-in 300ms.

      - [t5b6] **Profile**
        _Notes: Route: `profile.index` | URL: `/profile` | Controller: `ProfileController@index`_
        _Comment: User profile management._
        - **Avatar section**: Current avatar (large circle) + "Change" button (file upload, crop preview).
        - **Tabs**: "Profile" / "Password" / "Addresses".
        - **Profile tab**:
          - Name, Email (disabled), Phone, City, Province.
          - "Save Changes" button.
        - **Password tab**:
          - Current password, New password, Confirm password.
          - Password strength indicator (JS: weak/medium/strong).
        - **Addresses tab**:
          - List of saved addresses. Default badge. "Add New Address" button.
          - Each address: label (Home/Office), full address, edit/delete actions.
        - **Animation**: Tab switch 200ms fade. Avatar file preview 150ms.

      - [t5b7] **Review Form**
        _Notes: Route: embedded in order detail or product page | Controller: ReviewController_
        _Comment: Modal or inline form._
        - **Product info**: Small thumbnail + name at top.
        - **Rating**: 5 clickable stars. Hover preview (yellow). Click sets rating. Tooltip text per star ("Poor" to "Excellent").
        - **Comment**: Textarea with character counter (min 10, max 500).
        - **Photo upload**: Drag-drop zone or file input (max 3 images, 2MB each). Preview thumbnails with remove button.
        - **Submit**: "Submit Review" button. Rating required, comment optional (if min_characters met).
        - **Animation**: Star hover scale(1.2) 100ms. Upload preview fade-in 150ms. Modal backdrop 200ms.

    - [t5c] **Vendor Dashboard Pages**
      _Notes: All prefixed with `/vendor`. Role: vendor._

      - [t5c1] **Vendor Dashboard**
        _Notes: Route: `vendor.dashboard` | URL: `/vendor/dashboard` | Controller: `VendorDashboardController@index`_
        _Comment: KPI overview + sales chart + recent orders._
        - **Top navbar**: "Vendor Dashboard" title + store name + "View Store" link + open/closed toggle switch (Alpine.js live update via PATCH).
        - **KPI cards** (4 horizontal):
          - Today's Revenue (IDR, large number). Trend arrow (up/down) vs yesterday.
          - Total Orders (count). Trend.
          - Products (count). "Manage" link.
          - Average Rating (stars + number). "View Reviews" link.
          Each card: Japandi card with subtle icon, value, label, mini sparkline (CSS/SVG).
        - **Sales chart**: 7-day line chart (SVG or Chart.js). X-axis: days. Y-axis: revenue. Tooltip on hover. Gradient fill under line.
        - **Recent orders table**: Order number, customer name, total, status badge, date. Last 10. Click → order detail.
        - **Low stock alert**: Warning card if any product stock < 5. "View Products" link.
        - **Animations**: KPI count-up animation 500ms. Chart draw 600ms. Row hover bg 150ms.

      - [t5c2] **Product List**
        _Notes: Route: `vendor.products.index` | URL: `/vendor/products` | Controller: `VendorProductController@index`_
        _Comment: Manage own products. CRUD interface._
        - **Header**: "My Products" + "Add New Product" button (→ create).
        - **Search**: Search within own products.
        - **Filter tabs**: All / Active / Inactive / Draft.
        - **Table** (or card list on mobile):
          - Thumbnail (40px), Name, Category, Price, Stock, Status pill, Actions (Edit / Toggle / Delete).
          - Status toggle: switch click → Ajax update active/inactive.
          - Delete: confirm dialog with "Are you sure?" text.
        - **Pagination**: 10 per page.
        - **Empty state**: "You haven't added any products yet." + "Add Product" CTA.
        - **Animations**: Row fade-out on delete 200ms. Status toggle slide 150ms.

      - [t5c3] **Product Create**
        _Notes: Route: `vendor.products.create` | URL: `/vendor/products/create` | Controller: `VendorProductController@create`_
        _Comment: New product form._
        - **Form sections** (vertical stacked):
          - **Basic info**: Name, Slug (auto-generated from name, editable), Category (dropdown), Short description (textarea, 200 char limit), Full description (textarea, rich text optional).
          - **Pricing**: Price (IDR input), Compare price (optional, original price), Min order (number).
          - **Media**: Image upload — drag-drop zone + multiple files. Sortable thumbnails. First image = thumbnail. Max 5 images.
          - **Stock**: Stock quantity, Unit (select: pcs, kg, pack, box, etc.), Preparation time (text: "15-20 min").
          - **Variants** (optional, dynamic rows via Alpine.js):
            - Type (e.g. "Size", "Color") + options (text inputs + price adjustments). Add/remove rows.
          - **Addons** (optional, dynamic rows):
            - Name + price + max quantity. Add/remove rows.
          - **Status**: Active / Inactive / Draft radio.
        - **Submit**: "Save Product" button. Validation: name required, price > 0, stock >= 0.
        - **Animation**: Section scroll-into-view 150ms. Dynamic row add slide-down 200ms.

      - [t5c4] **Product Edit**
        _Notes: Route: `vendor.products.edit` | URL: `/vendor/products/{product}/edit` | Controller: `VendorProductController@edit`_
        _Comment: Edit existing product. Same form as create, pre-filled._
        - **Pre-filled**: All fields populated from product data.
        - **Existing images**: Displayed as thumbnails with delete button (X overlay).
        - **Variants/Addons**: Pre-filled dynamic rows.
        - **Delete product**: Red button at bottom "Delete Product" with confirmation.
        - **Submit**: "Update Product". Same validation.
        - **Animation**: Form fade-in 200ms.

      - [t5c5] **Vendor Order List**
        _Notes: Route: `vendor.orders.index` | URL: `/vendor/orders` | Controller: `VendorOrderController@index`_
        _Comment: Orders placed on this vendor's products._
        - **Header**: "Orders" + filter tabs: All, Pending, Processing, Ready, Delivered, Cancelled.
        - **Order cards** (list):
          - Order number + date.
          - Customer name + phone (clickable, tel: link).
          - Items summary (product name × quantity).
          - Total amount.
          - Status badge + last updated time.
          - Actions per status:
            - Pending: "Confirm Order" / "Cancel" buttons.
            - Confirmed: "Mark Preparing".
            - Preparing: "Mark Ready for Pickup".
            - Ready: assigned to driver automatically.
        - **Pagination**: 10 per page.
        - **Real-time**: Poll for new orders every 30s (Alpine.js `setInterval`). Toast notification on new.
        - **Animation**: Status transition badge color swap 200ms. New order card slide-in 300ms.

      - [t5c6] **Vendor Order Detail**
        _Notes: Route: `vendor.orders.show` | URL: `/vendor/orders/{order}` | Controller: `VendorOrderController@show`_
        _Comment: Single order detail for vendor._
        - **Header**: Order number, date, status badge. Back button.
        - **Status action buttons**: Contextual per current status (same as t5c5 actions).
        - **Items list**: Product name, variant text, quantity, price per item, subtotal.
        - **Customer info**: Name, phone (call button), delivery address.
        - **Note**: Customer's order note (if any).
        - **Order timeline**: Log of all status changes with timestamps.
        - **Animation**: Action button status update with brief success toast + badge refresh 150ms.

    - [t5d] **Admin Dashboard Pages**
      _Notes: All prefixed with `/admin`. Role: admin._

      - [t5d1] **Admin Dashboard**
        _Notes: Route: `admin.dashboard` | URL: `/admin/dashboard` | Controller: `AdminDashboardController@index`_
        _Comment: Platform-wide KPIs._
        - **KPI row** (4-6 cards):
          - Total Users, Total Vendors, Total Orders, Total Revenue (all time).
          - Each card: icon, value, label, % change vs last period.
        - **Revenue chart**: 30-day line chart. Daily revenue. Tooltip.
        - **Vendor verification queue**: List of pending vendors with "Review" button. Count badge on card.
        - **Recent orders**: Last 10 orders across all vendors. Order number, vendor, customer, total, status.
        - **System health**: Queue size, cache hit ratio, last backup time (from Pulse).
        - **Animation**: KPI count-up 600ms. Chart draw 700ms.

      - [t5d2] **Vendor Management**
        _Notes: Route: `admin.vendors.index` | URL: `/admin/vendors` | Controller: `AdminVendorController@index`_
        _Comment: List all vendors with verification actions._
        - **Header**: "Vendors" + search + status filter: All, Pending, Verified, Suspended, Rejected.
        - **Table**:
          - Store name + logo (small), Owner name, Email, City, Products count, Orders count, Status badge, Date registered.
          - Actions: "View" (→ detail), "Verify" (only for pending), "Suspend" (with reason modal), "Reinstate" (for suspended).
        - **Bulk actions**: Select checkboxes → "Verify Selected" / "Suspend Selected".
        - **Pagination**: 20 per page.
        - **Animation**: Status badge change 200ms. Row highlight on action 1.5s then fade.

      - [t5d3] **Vendor Detail**
        _Notes: Route: `admin.vendors.show` | URL: `/admin/vendors/{vendor}` | Controller: `AdminVendorController@show`_
        _Comment: Full vendor profile for admin review._
        - **Store card**: Logo, cover image, name, description, rating.
        - **Verification section** (for pending):
          - Uploaded business license (image/pdf viewer).
          - Submitted bank info.
          - Actions: "Approve" (with optional commission rate override) / "Reject" (with reason textarea).
        - **Stats**: Products count, Orders count, Total revenue, Commission earned.
        - **Products**: Mini table of vendor's products (5 per page).
        - **Recent orders**: Mini table of vendor's orders (5 per page).
        - **Danger zone**: "Suspend Vendor" button with confirmation + reason input.

      - [t5d4] **Admin Order Management**
        _Notes: Route: `admin.orders.index` | URL: `/admin/orders` | Controller: `AdminOrderController@index`_
        _Comment: All orders across all vendors._
        - **Header**: "Orders" + search (order number) + date range filter + status filter.
        - **Table**: Order number, Vendor, Customer, Total, Status badge, Payment status, Date.
        - **Click**: Row → order detail (`admin.orders.show`).
        - **Export**: "Export CSV" button (all filtered orders).
        - **Pagination**: 20 per page.

      - [t5d5] **Admin Order Detail**
        _Notes: Route: `admin.orders.show` | URL: `/admin/orders/{order}` | Controller: `AdminOrderController@show`_
        _Comment: Full order view with override actions._
        - **Same layout** as customer order detail (t5b5) plus:
        - **Admin overrides**: Force status change dropdown (any→any). Cancel order (with reason). Refund (with amount).
        - **Audit log**: Full order event timeline with timestamps + who performed action.
        - **Animation**: Audit log row fade-in 100ms stagger.

      - [t5d6] **Review Moderation**
        _Notes: Route: admin reviews page | URL: `/admin/reviews`_
        _Comment: Pending reviews awaiting approval._
        - **Filter tabs**: Pending / Approved / Rejected.
        - **Review cards**: Product name, reviewer name, rating stars, comment text, images (expandable), date.
        - **Actions**: "Approve" / "Reject" buttons. Reject opens reason modal (optional, not shown to user).
        - **Bulk**: Select + "Approve Selected".
        - **Animation**: Card dismiss slide 200ms (approve/reject).

      - [t5d7] **Category Management**
        _Notes: Route: admin categories | URL: `/admin/categories`_
        _Comment: CRUD for product categories._
        - **Table**: Name, Slug, Icon, Parent category, Sort order, Product count, Active toggle, Actions (Edit / Delete).
        - **Create/Edit modal**: Name, Slug (auto), Parent (select), Icon (icon picker), Image (upload), Sort order, Active toggle.
        - **Delete**: Confirmation + reassign products to another category option.
        - **Reorder**: Drag-and-drop sort (Alpine.js Sortable).
        - **Animation**: Modal slide-up 250ms. Reorder smooth 200ms.

    - [t5e] **Utility Pages**
      _Notes: Error pages and static content._

      - [t5e1] **403 Forbidden**
        _Notes: Route: no specific route | View: `errors/403.blade.php`_
        - **Content**: Centered card. Japandi illustration (person at closed gate). "Access Denied" heading, "You don't have permission to view this page." text. "Go Home" button. Soft border card, `--j-stone` background.

      - [t5e2] **404 Not Found**
        _Notes: View: `errors/404.blade.php`_
        - **Content**: Centered card. Japandi illustration (empty room / tea bowl). "Page Not Found" heading. "The page you're looking for doesn't exist or has been moved." text. Search input + "Go Home" button.

      - [t5e3] **419 Page Expired**
        _Notes: View: `errors/419.blade.php`_
        - **Content**: Centered card. "Session Expired" heading. "Your session has timed out. Please login again." text. "Go to Login" button.

      - [t5e4] **500 Server Error**
        _Notes: View: `errors/500.blade.php`_
        - **Content**: Centered card. "Something Went Wrong" heading. "Our team has been notified. Please try again later." text. "Refresh Page" + "Go Home" buttons.

  - [t6] **Core Flows**
    _Notes: Seven primary user journeys linking Page Specifications (t5) together._
    _Comment: Each flow maps to specific pages from t5_

    - [t6a]**Browse → Order** (t5a2→t5a3→t5b1→t5b2→t5b3)
    - [t6b]**Vendor Onboarding** (t5a9→admin t5d2→t5c1)
    - [t6c]**Order Fulfillment** (t5b4→t5c5→t5c6→t5b5)
    - [t6d]**Payment Settlement** (t5b2→payments table→vendor payout)
    - [t6e]**Search & Discovery** (t5a6→t5a2)
    - [t6f]**Review System** (t5b7→t5d6→product page)
    - [t6g]**Admin Oversight** (t5d1→t5d2→t5d4→t5d6)

  - [t7] **API Structure**
    _Notes: Laravel Sanctum SPA authentication (cookie-based)._
    _Comment: API routes in routes/api.php_

    - [t7a] **Auth Endpoints**
      - `POST /api/auth/login`
      - `POST /api/auth/register`
      - `POST /api/auth/logout`
      - `GET /api/auth/user`
      - `PUT /api/auth/profile`

    - [t7b] **Public Endpoints**
      - `GET /api/products` (paginated, filterable)
      - `GET /api/products/{slug}`
      - `GET /api/vendors` (verified only)
      - `GET /api/vendors/{slug}`
      - `GET /api/categories`
      - `GET /api/search?q=`

    - [t7c] **Protected Endpoints**
      - `GET /api/cart` / `POST /api/cart` / `DELETE /api/cart/{id}`
      - `POST /api/checkout`
      - `GET /api/orders` / `GET /api/orders/{id}`
      - `POST /api/orders/{id}/cancel`
      - `POST /api/reviews`

  - [t8] **Security Measures**
    _Notes: Defense in depth — Laravel best practices._
    _Comment: All applied in middleware stack_
    Markers: security

    - [t8a] **CSRF Protection** (Laravel default)
    - [t8b] **SQL Injection** (Eloquent parameter binding)
    - [t8c] **XSS** (Blade {{ }} auto-escape)
    - [t8d] **Rate Limiting** (throttle: 60req/min API)
    - [t8e] **Role Middleware** (admin/vendor routes gated)
    - [t8f] **File Upload Validation** (mimes, max size 2MB)
    - [t8g] **Password Hashing** (bcrypt, cost 12)
    - [t8h] **CORS** (Sanctum stateful domains)
    - [t8i] **Session Hijacking** (secure + httpOnly cookies)
    - [t8j] **Failed Login Throttling** (5 attempts, 1min lockout)

  - [t9] **Deployment Checklist**
    _Notes: Production-ready config for shared hosting or VPS._
    _Comment: Laravel Forge + Vapor supported_
    Markers: devops

    - [t9a] **Environment**
      - `APP_ENV=production`, `APP_DEBUG=false`
      - `SESSION_DRIVER=redis`, `CACHE_DRIVER=redis`
      - `QUEUE_CONNECTION=database` (or redis)
      - HTTPS enforced (Cloudflare or nginx)

    - [t9b] **Optimization**
      - `php artisan optimize`
      - `php artisan view:cache`
      - `php artisan route:cache`
      - `php artisan config:cache`
      - Vite build with code splitting

    - [t9c] **Monitoring**
      - Laravel Pulse (live metrics)
      - Error tracking (Flare or Sentry)
      - Uptime monitoring
      - Database backup (daily)

    - [t9d] **Scale**
      - Read replicas for MySQL
      - Redis for sessions + cache
      - Horizon for queue workers
      - CDN for static assets

  - [t10] **File Structure**
    _Notes: Full Laravel skeleton. Every view file maps to a Page Spec in t5._
    _Comment: Standard Laravel 11 convention_

    ```
    multivendor-delivery/
    ├── app/
    │   ├── Http/Controllers/
    │   │   ├── Admin/{Dashboard,Vendor,Order}Controller.php
    │   │   ├── Auth/{Login,Register,VendorRegister}Controller.php
    │   │   ├── Vendor/{Dashboard,Product,Order}Controller.php
    │   │   ├── {Home,Shop,Cart,Checkout,Order,Profile}Controller.php
    │   ├── Http/Middleware/{Admin,Vendor}Middleware.php
    │   ├── Http/Requests/{StoreProduct,UpdateProfile}Request.php
    │   └── Models/{User,Vendor,Category,Product,Order,OrderItem,Review,Cart,Payment,Delivery}.php
    ├── config/
    │   └── marketplace.php (vendor, delivery, currency, pagination, review, order settings)
    ├── database/
    │   ├── migrations/ (12 files — see t2 for schema)
    │   └── seeders/{Database,Category}Seeder.php
    ├── resources/views/
    │   ├── layouts/app.blade.php (Japandi chrome: nav, sidebar, footer)
    │   ├── partials/{header,footer,japandi-styles}.blade.php
    │   ├── auth/{login,register,vendor-register}.blade.php       → t5a7-9
    │   ├── home/index.blade.php                                   → t5a1
    │   ├── shop/{index,show,vendor}.blade.php                     → t5a2-5
    │   ├── cart/index.blade.php                                   → t5b1
    │   ├── checkout/{index,success}.blade.php                     → t5b2-3
    │   ├── orders/{index,show}.blade.php                          → t5b4-5
    │   ├── profile/index.blade.php                                → t5b6
    │   ├── components/review-form.blade.php                       → t5b7
    │   ├── vendor/{dashboard,products/*,orders*}.blade.php        → t5c1-6
    │   ├── admin/{dashboard,vendors*,orders*,reviews,categories}.blade.php → t5d1-7
    │   └── errors/{403,404,419,500}.blade.php                    → t5e1-4
    ├── routes/
    │   ├── web.php (70+ routes — maps every t5 page)
    │   └── api.php (REST endpoints — see t7)
    └── public/css/app.css (Japandi design system — see t4)

---

## Cross-links

- **t4 (Design)** → **t5 (Page Specs)** — Every page uses Japandi colors, borders, animations. Each page spec references specific animation timings.
- **t8 (Security)** → **t5b2 (Checkout)** — PCI compliance enforced on payment form. CSRF protects all POST routes.
- **t2 (Schema)** → **t5 (Page Specs)** — Every page spec lists its data queries. Maps directly to tables.
- **t3 (Roles)** → **t5c/d (Vendor/Admin pages)** — Middleware gates each role dashboard section.
- **t7 (API)** → **t5a2/a3 (Shop pages)** — Public API endpoints mirror shop page data load.
- **t10 (File Structure)** → **t5 (Page Specs)** — Each view file annotated with its t5 page spec ID.
- **t6 (Core Flows)** → **t5 (Page Specs)** — Each flow references specific page nodes. Navigation paths documented.
- **t9 (Deployment)** → **t10 (File Structure)** — Optimization commands reference compiled view files.

---

## Test Checklist

### Guest Pages (t5a)
- [ ] **Home (t5a1)**: Hero loads with animation. Category grid links work. Featured vendor horizontal scroll. Popular products grid renders. Footer CTA links to vendor register.
- [ ] **Shop listing (t5a2)**: Product grid paginates (12/page). Sidebar filters update results via Ajax. Sort dropdown reorders. Price range filter works. Empty state shows on no results.
- [ ] **Product detail (t5a3)**: Gallery thumbnails swap main image. Variant pills selectable. Addons update subtotal via Alpine.js. Quantity min/max enforced. Reviews load with pagination. Star summary bar renders correctly.
- [ ] **Vendor listing (t5a4)**: Cards render with cover/logo overlap. Live search debounces 300ms. City filter works. Pagination (20/page).
- [ ] **Vendor store (t5a5)**: Cover image with gradient overlay. Business hours accordion. Category tabs filter products. Product grid matches vendor scope.
- [ ] **Login (t5a7)**: Card centered. Form validates. Show/hide password toggle. Social login buttons visible if configured.
- [ ] **Register (t5a8)**: Form validates inline. Terms checkbox required. Post-register auto-login + toast.
- [ ] **Vendor register (t5a9)**: Two-step flow. Business license upload. Bank info form. Business hours dynamic rows. Success message after submit.

### Customer Pages (t5b)
- [ ] **Cart (t5b1)**: Items grouped by vendor section. Quantity update via PATCH debounce. Line total recalculates. Clear all with confirm. Empty state shows when no items.
- [ ] **Checkout (t5b2)**: Address pre-filled from profile. Map picker for lat/lng. Payment method radio cards with accent border. Stock re-checked on submit. Double-click prevention.
- [ ] **Order success (t5b3)**: Checkmark draw animation (500ms). Order number copy button. 4-step timeline shows current status. Action buttons link correctly.
- [ ] **Order history (t5b4)**: Status filter tabs switch content. Order cards show thumbnails + "+N more" badge. Action buttons per status.
- [ ] **Order detail (t5b5)**: Vertical timeline with current status dot pulse. Map loads for in_delivery orders. Driver info + call button. Cancel/Confirm buttons respect status rules.
- [ ] **Profile (t5b6)**: Tab switcher (Profile/Password/Addresses). Avatar upload with preview. Password strength indicator. Address CRUD.
- [ ] **Review form (t5b7)**: Star rating hover/click works. Image upload drag-drop. Character counter on textarea. Submit validates rating required.

### Vendor Pages (t5c)
- [ ] **Dashboard (t5c1)**: KPI count-up animation. 7-day chart renders. Open/closed toggle updates via Ajax. Recent orders table clickable. Low stock alert shows.
- [ ] **Product list (t5c2)**: Filter tabs (All/Active/Inactive/Draft). Status toggle Ajax. Delete with confirm. Search within own products.
- [ ] **Product create (t5c3)**: All form sections render. Image upload sortable. Variant/addon dynamic rows. Slug auto-generates from name.
- [ ] **Product edit (t5c4)**: Pre-filled form. Existing images deletable. Same validation as create.
- [ ] **Order list (t5c5)**: Status filter tabs. Action buttons per status. New order poll every 30s. Toast on new order.
- [ ] **Order detail (t5c6)**: Status action buttons contextual. Customer info with call link. Timeline log renders.

### Admin Pages (t5d)
- [ ] **Dashboard (t5d1)**: KPI count-up. Revenue chart 30-day. Vendor verification queue badge. Recent orders table. System health cards.
- [ ] **Vendor management (t5d2)**: Search + status filter. Bulk actions (verify/suspend). Status badge transition animation.
- [ ] **Vendor detail (t5d3)**: Store card with verification section. Business license viewer. Approve/Reject with reason. Danger zone for suspend.
- [ ] **Order management (t5d4)**: Search by order number. Date range filter. Status filter. CSV export. Pagination.
- [ ] **Order detail (t5d5)**: Admin override status dropdown. Refund with amount. Audit log timeline.
- [ ] **Review moderation (t5d6)**: Filter tabs. Approve/Reject with card dismiss animation. Bulk selection.
- [ ] **Category management (t5d7)**: CRUD modal. Drag-and-drop reorder. Reassign on delete.

### Utility & Cross-Cutting
- [ ] **Error pages (t5e1-4)**: 403, 404, 419, 500 all styled with Japandi card + illustration + action button.
- [ ] **Security**: Guest cannot access any /vendor or /admin route. CSRF token present on all POST forms. Rate limit returns 429 after 60 requests/min on API routes.
- [ ] **Responsive**: Mobile hamburger nav. Offcanvas filters on shop. Touch-friendly buttons (44px min tap target). 1-col mobile → 2-col tablet → 3-col desktop.
- [ ] **Animations**: Card hover lift (translateY -2px, 200ms). Page fade-slide-up (300ms). `prefers-reduced-motion` disables all animations.
- [ ] **Performance**: Images lazy-loaded. Vite build produces split chunks. Lighthouse score >85 all categories. Database queries N+1 free (eager loaded).
- [ ] **Queue**: Order confirmation email dispatched to queue. Vendor notification on new order. Horizon dashboard accessible.
- [ ] **Payment**: Midtrans Snap popup opens on "Pay Now". Success callback redirects to order success. Failed callback shows error toast. Payment status updates via webhook.
