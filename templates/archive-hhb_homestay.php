<?php
/**
 * Plugin Default Template: Homestay Archive / Listing Page
 *
 * Designed via Google Stitch — premium mountain-retreat aesthetic.
 * Theme can override by providing archive-hhb_homestay.php in theme root.
 *
 * @package Himalayan\Homestay
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

// ── Filter params from URL ────────────────────────────────────────────────────
$selected_location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
$selected_type     = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$selected_guests   = isset($_GET['guests']) ? absint($_GET['guests']) : 0;
$min_price         = isset($_GET['min_price']) ? absint($_GET['min_price']) : 0;
$max_price         = isset($_GET['max_price']) ? absint($_GET['max_price']) : 99999;
$amenities_filter  = isset($_GET['amenities']) ? (array) $_GET['amenities'] : [];

// ── Taxonomy options for filter selects ───────────────────────────────────────
$locations_terms = get_terms(['taxonomy' => 'hhb_location',      'hide_empty' => false]);
$type_terms      = get_terms(['taxonomy' => 'hhb_property_type', 'hide_empty' => false]);
$amenity_keys    = ['wifi' => 'WiFi', 'parking' => 'Parking', 'kitchen' => 'Kitchen', 'ac' => 'AC', 'pool' => 'Pool', 'fireplace' => 'Fireplace', 'garden' => 'Garden', 'hot_water' => 'Hot Water'];

$current_page  = max(1, get_query_var('paged'));
$archive_url   = get_post_type_archive_link('hhb_homestay');
$total_found   = $wp_query->found_posts;
?>

<!-- Google Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0&display=swap" rel="stylesheet">

<style>
  :root { --brand: #e85e30; --brand-light: #fef1ec; --bg: #f8f6f6; --border: #e2e8f0; --text: #1a1a2e; --muted: #64748b; }
  .hhb-archive * { box-sizing: border-box; font-family: 'Inter', sans-serif; }

  /* ── HERO ── */
  .hhb-arch-hero { position: relative; min-height: 500px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
  .hhb-arch-hero-bg { position: absolute; inset: 0; background: url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1920&q=80') center/cover no-repeat; }
  .hhb-arch-hero-overlay { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(24,14,7,0.65) 0%, rgba(30,18,8,0.4) 60%, rgba(248,246,246,0.15) 100%); }
  .hhb-arch-hero-content { position: relative; z-index: 1; text-align: center; padding: 60px 24px 100px; max-width: 720px; margin: 0 auto; }
  .hhb-arch-hero h1 { font-size: clamp(32px,5vw,60px); font-weight: 900; color: #fff; line-height: 1.1; margin: 0 0 16px; letter-spacing: -0.5px; }
  .hhb-arch-hero p { font-size: 18px; color: rgba(255,255,255,0.85); margin: 0 0 40px; font-weight: 400; }
  .hhb-arch-hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(232,94,48,0.2); color: rgba(255,255,255,0.9); border: 1px solid rgba(232,94,48,0.4); border-radius: 50px; padding: 6px 16px; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 20px; backdrop-filter: blur(8px); }

  /* ── FLOATING SEARCH BAR ── */
  .hhb-search-card { background: white; border-radius: 20px; box-shadow: 0 10px 48px rgba(0,0,0,0.18); padding: 20px 24px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; max-width: 860px; margin: 0 auto; }
  .hhb-search-field { flex: 1; min-width: 140px; }
  .hhb-search-field label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
  .hhb-search-field select, .hhb-search-field input { width: 100%; border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; font-size: 15px; font-family: 'Inter', sans-serif; color: var(--text); background: #f8fafc; outline: none; }
  .hhb-search-field select:focus, .hhb-search-field input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(232,94,48,0.1); }
  .hhb-search-btn { background: var(--brand); color: white; border: none; border-radius: 12px; padding: 13px 28px; font-weight: 700; font-size: 16px; cursor: pointer; font-family: 'Inter', sans-serif; display: flex; align-items: center; gap: 8px; white-space: nowrap; box-shadow: 0 4px 16px rgba(232,94,48,0.3); transition: all 0.2s; }
  .hhb-search-btn:hover { background: #c94d22; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(232,94,48,0.4); }

  /* ── MAIN LAYOUT ── */
  .hhb-arch-main { max-width: 1280px; margin: 0 auto; padding: 48px 24px 80px; display: grid; grid-template-columns: 270px 1fr; gap: 40px; align-items: start; }
  @media(max-width: 900px) { .hhb-arch-main { grid-template-columns: 1fr; } .hhb-arch-sidebar { display: none; } }

  /* ── SIDEBAR FILTERS ── */
  .hhb-arch-sidebar { position: sticky; top: 24px; background: white; border-radius: 20px; border: 1px solid var(--border); padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
  .hhb-arch-sidebar h3 { font-size: 16px; font-weight: 800; color: var(--text); margin: 0 0 20px; display: flex; align-items: center; gap: 8px; }
  .hhb-filter-section { margin-bottom: 28px; padding-bottom: 28px; border-bottom: 1px solid var(--border); }
  .hhb-filter-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
  .hhb-filter-label { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; display: block; }
  .hhb-filter-check { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #374151; cursor: pointer; }
  .hhb-filter-check input { width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; }
  .hhb-price-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
  .hhb-price-inputs input { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; font-size: 14px; font-family: 'Inter', sans-serif; color: var(--text); width: 100%; }
  .hhb-apply-btn { width: 100%; background: var(--brand); color: white; border: none; border-radius: 10px; padding: 12px; font-weight: 700; font-size: 14px; cursor: pointer; font-family: 'Inter', sans-serif; margin-top: 16px; transition: background 0.2s; }
  .hhb-apply-btn:hover { background: #c94d22; }

  /* ── RESULTS AREA ── */
  .hhb-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
  .hhb-results-count { font-size: 20px; font-weight: 700; color: var(--text); }
  .hhb-results-count span { color: var(--brand); }

  /* ── PROPERTY CARDS GRID ── */
  .hhb-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 24px; }

  /* Card */
  .hhb-prop-card { background: white; border-radius: 20px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.05); transition: all 0.3s; position: relative; }
  .hhb-prop-card:hover { transform: translateY(-6px); box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
  .hhb-card-img-wrap { position: relative; height: 220px; overflow: hidden; }
  .hhb-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
  .hhb-prop-card:hover .hhb-card-img { transform: scale(1.07); }
  .hhb-wishlist-btn { position: absolute; top: 14px; right: 14px; width: 36px; height: 36px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.12); border: none; transition: all 0.2s; z-index: 1; }
  .hhb-wishlist-btn:hover { background: var(--brand); color: white; }
  .hhb-wishlist-btn .material-symbols-outlined { font-size: 20px; color: #374151; }
  .hhb-wishlist-btn:hover .material-symbols-outlined { color: white; }
  .hhb-card-type-badge { position: absolute; bottom: 14px; left: 14px; background: rgba(255,255,255,0.9); backdrop-filter: blur(8px); border-radius: 8px; padding: 4px 12px; font-size: 12px; font-weight: 700; color: var(--brand); border: 1px solid rgba(232,94,48,0.2); }
  .hhb-card-body { padding: 20px; }
  .hhb-card-title { font-size: 17px; font-weight: 700; color: var(--text); margin: 0 0 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .hhb-card-location { display: flex; align-items: center; gap: 4px; color: var(--muted); font-size: 13px; margin-bottom: 10px; }
  .hhb-card-location .material-symbols-outlined { font-size: 15px; color: var(--brand); }
  .hhb-card-rating { display: flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 13px; }
  .hhb-card-stars { color: #f59e0b; font-size: 14px; }
  .hhb-card-review-count { color: var(--muted); }
  .hhb-card-stats { display: flex; gap: 16px; margin-bottom: 14px; }
  .hhb-card-stat { display: flex; align-items: center; gap: 5px; font-size: 13px; color: var(--muted); }
  .hhb-card-stat .material-symbols-outlined { font-size: 16px; color: var(--brand); }
  .hhb-card-price-row { display: flex; align-items: baseline; gap: 6px; border-top: 1px solid var(--border); padding-top: 14px; margin-top: 4px; }
  .hhb-card-price { font-size: 22px; font-weight: 800; color: var(--brand); }
  .hhb-card-price-unit { font-size: 13px; color: var(--muted); }
  .hhb-card-old-price { font-size: 14px; color: #94a3b8; text-decoration: line-through; }
  .hhb-card-cta { display: block; text-align: center; background: var(--brand-light); color: var(--brand); border: 1px solid rgba(232,94,48,0.2); border-radius: 10px; padding: 10px 16px; font-weight: 700; font-size: 14px; text-decoration: none; margin-top: 14px; transition: all 0.2s; }
  .hhb-card-cta:hover { background: var(--brand); color: white; }

  /* Empty state */
  .hhb-no-results { text-align: center; padding: 80px 24px; background: white; border-radius: 20px; border: 1px solid var(--border); }
  .hhb-no-results .material-symbols-outlined { font-size: 64px; color: #cbd5e1; margin-bottom: 16px; }
  .hhb-no-results h2 { color: var(--text); margin: 0 0 8px; font-size: 24px; }
  .hhb-no-results p { color: var(--muted); font-size: 16px; margin: 0; }

  /* Pagination */
  .hhb-pagination { display: flex; justify-content: center; gap: 8px; margin-top: 48px; flex-wrap: wrap; }
  .hhb-pagination a, .hhb-pagination span { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border); font-weight: 600; font-size: 14px; text-decoration: none; color: var(--text); background: white; transition: all 0.2s; font-family: 'Inter', sans-serif; }
  .hhb-pagination .current { background: var(--brand); color: white; border-color: var(--brand); }
  .hhb-pagination a:hover { background: var(--brand-light); border-color: var(--brand); color: var(--brand); }
</style>

<!-- ── HERO + SEARCH ── -->
<div class="hhb-arch-hero">
  <div class="hhb-arch-hero-bg"></div>
  <div class="hhb-arch-hero-overlay"></div>
  <div class="hhb-arch-hero-content">
    <div class="hhb-arch-hero-badge">
      <span class="material-symbols-outlined" style="font-size:14px;">landscape</span>
      Himalayan Escapes
    </div>
    <h1>Find Your Perfect<br>Himalayan Escape</h1>
    <p>Discover luxury mountain retreats, heritage homestays and exclusive wilderness stays</p>

    <!-- Search Bar -->
    <form method="GET" action="<?php echo esc_url($archive_url); ?>" class="hhb-search-card">
      <div class="hhb-search-field">
        <label>Location</label>
        <select name="location">
          <option value="">All Destinations</option>
          <?php if (!is_wp_error($locations_terms)) foreach ($locations_terms as $loc) : ?>
            <option value="<?php echo esc_attr($loc->slug); ?>" <?php selected($selected_location, $loc->slug); ?>><?php echo esc_html($loc->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="hhb-search-field">
        <label>Property Type</label>
        <select name="type">
          <option value="">Any Type</option>
          <?php if (!is_wp_error($type_terms)) foreach ($type_terms as $t) : ?>
            <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($selected_type, $t->slug); ?>><?php echo esc_html($t->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="hhb-search-field" style="max-width:140px;">
        <label>Guests</label>
        <input type="number" name="guests" placeholder="Any" min="1" max="20" value="<?php echo $selected_guests ?: ''; ?>">
      </div>
      <button type="submit" class="hhb-search-btn">
        <span class="material-symbols-outlined">search</span>
        Search
      </button>
    </form>
  </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="hhb-arch-main">

  <!-- LEFT — Sidebar Filters -->
  <aside class="hhb-arch-sidebar">
    <h3><span class="material-symbols-outlined">tune</span>Filters</h3>

    <form method="GET" action="<?php echo esc_url($archive_url); ?>" id="hhb-sidebar-filter-form">
      <!-- Keep existing search params -->
      <?php if ($selected_location) echo '<input type="hidden" name="location" value="' . esc_attr($selected_location) . '">'; ?>
      <?php if ($selected_type) echo '<input type="hidden" name="type" value="' . esc_attr($selected_type) . '">'; ?>
      <?php if ($selected_guests) echo '<input type="hidden" name="guests" value="' . esc_attr($selected_guests) . '">'; ?>

      <!-- Price Range -->
      <div class="hhb-filter-section">
        <span class="hhb-filter-label">Price Range / Night</span>
        <div class="hhb-price-inputs">
          <input type="number" name="min_price" placeholder="Min ₹" value="<?php echo $min_price ?: ''; ?>">
          <input type="number" name="max_price" placeholder="Max ₹" value="<?php echo $max_price < 99999 ? $max_price : ''; ?>">
        </div>
      </div>

      <!-- Property Type -->
      <?php if (!is_wp_error($type_terms) && !empty($type_terms)) : ?>
      <div class="hhb-filter-section">
        <span class="hhb-filter-label">Property Type</span>
        <?php foreach ($type_terms as $t) : ?>
          <label class="hhb-filter-check">
            <input type="checkbox" name="type_filter[]" value="<?php echo esc_attr($t->slug); ?>" <?php checked(in_array($t->slug, (array)($amenities_filter)), true); ?>>
            <?php echo esc_html($t->name); ?> (<?php echo $t->count; ?>)
          </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Amenities -->
      <div class="hhb-filter-section">
        <span class="hhb-filter-label">Amenities</span>
        <?php foreach ($amenity_keys as $key => $label) : ?>
          <label class="hhb-filter-check">
            <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $amenities_filter), true); ?>>
            <?php echo esc_html($label); ?>
          </label>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="hhb-apply-btn">Apply Filters</button>
    </form>
  </aside>

  <!-- RIGHT — Property Cards -->
  <div>
    <div class="hhb-results-header">
      <div class="hhb-results-count">
        <span><?php echo number_format($total_found); ?></span>
        <?php if (is_tax('hhb_location')) : ?>
          <?php 
            $queried = get_queried_object(); 
            $prefix  = get_theme_mod('himalayanmart_homestay_archive_prefix', 'Explore Homestays in ');
            $suffix  = get_theme_mod('himalayanmart_homestay_archive_suffix', '');
          ?>
          properties in <?php echo esc_html($prefix . ($queried->name ?? '') . $suffix); ?>
        <?php elseif (is_tax('hhb_property_type')) : ?>
          <?php 
            $queried = get_queried_object(); 
            $prefix  = get_theme_mod('himalayanmart_property_type_archive_prefix', 'Explore ');
            $suffix  = get_theme_mod('himalayanmart_property_type_archive_suffix', ' Stays');
          ?>
          properties matching <?php echo esc_html($prefix . ($queried->name ?? '') . $suffix); ?>
        <?php else : ?>
          properties found
        <?php endif; ?>
      </div>
    </div>

    <?php if (have_posts()) : ?>
      <div class="hhb-cards-grid">
        <?php while (have_posts()) : the_post();
          $pid          = get_the_ID();
          $thumb_url    = get_the_post_thumbnail_url($pid, 'medium_large') ?: 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80';
          $price        = get_post_meta($pid, 'base_price_per_night', true);
          $offer        = get_post_meta($pid, 'offer_price_per_night', true);
          $beds         = get_post_meta($pid, 'hhb_bedrooms', true) ?: get_post_meta($pid, 'room_count', true);
          $guests_cap   = get_post_meta($pid, 'max_guests', true);
          $avg_r        = (float)get_post_meta($pid, 'hhb_avg_rating', true);
          $rev_c        = (int)get_post_meta($pid, 'hhb_review_count', true);
          $prop_types   = get_the_terms($pid, 'hhb_property_type');
          $type_label   = ($prop_types && !is_wp_error($prop_types)) ? $prop_types[0]->name : 'Homestay';
          $locs         = get_the_terms($pid, 'hhb_location');
          $loc_label    = ($locs && !is_wp_error($locs)) ? $locs[0]->name : '';
          $is_wishlisted = false;
          if (is_user_logged_in()) {
            $wishlist = (array)get_user_meta(get_current_user_id(), 'hhb_wishlist', true);
            $is_wishlisted = in_array($pid, $wishlist);
          }
        ?>
          <article class="hhb-prop-card">

            <!-- Card Image -->
            <div class="hhb-card-img-wrap">
              <a href="<?php the_permalink(); ?>">
                <img class="hhb-card-img" src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
              </a>

              <!-- Wishlist -->
              <?php if (is_user_logged_in()) : ?>
                <button class="hhb-wishlist-btn hhb-wishlist-toggle"
                        data-id="<?php echo esc_attr($pid); ?>"
                        data-nonce="<?php echo wp_create_nonce('hhb_wishlist'); ?>"
                        title="<?php echo $is_wishlisted ? 'Remove from wishlist' : 'Save to wishlist'; ?>">
                  <span class="material-symbols-outlined" style="<?php echo $is_wishlisted ? 'color: #e85e30;' : ''; ?>">
                    <?php echo $is_wishlisted ? 'favorite' : 'favorite_border'; ?>
                  </span>
                </button>
              <?php endif; ?>

              <!-- Type Badge -->
              <span class="hhb-card-type-badge"><?php echo esc_html($type_label); ?></span>
            </div>

            <!-- Card Body -->
            <div class="hhb-card-body">
              <h2 class="hhb-card-title"><?php the_title(); ?></h2>

              <?php if ($loc_label) : ?>
                <div class="hhb-card-location">
                  <span class="material-symbols-outlined">location_on</span>
                  <?php echo esc_html($loc_label); ?>
                </div>
              <?php endif; ?>

              <div class="hhb-card-rating">
                <?php if ($avg_r > 0) : ?>
                  <div class="hhb-card-stars">★ <?php echo number_format($avg_r, 1); ?></div>
                  <span class="hhb-card-review-count">(<?php echo $rev_c; ?>)</span>
                <?php else : ?>
                  <span class="hhb-card-stars">★★★★★</span>
                  <span class="hhb-card-review-count">New</span>
                <?php endif; ?>
              </div>

              <div class="hhb-card-stats">
                <?php if ($beds) : ?>
                  <div class="hhb-card-stat"><span class="material-symbols-outlined">bed</span><?php echo esc_html($beds); ?> Bed<?php echo $beds != 1 ? 's' : ''; ?></div>
                <?php endif; ?>
                <?php if ($guests_cap) : ?>
                  <div class="hhb-card-stat"><span class="material-symbols-outlined">group</span><?php echo esc_html($guests_cap); ?> Guests</div>
                <?php endif; ?>
              </div>

              <div class="hhb-card-price-row">
                <?php if ($offer) : ?>
                  <div class="hhb-card-price">₹<?php echo number_format((float)$offer); ?></div>
                  <div class="hhb-card-price-unit">/night</div>
                  <div class="hhb-card-old-price">₹<?php echo number_format((float)$price); ?></div>
                <?php elseif ($price) : ?>
                  <div class="hhb-card-price">₹<?php echo number_format((float)$price); ?></div>
                  <div class="hhb-card-price-unit">/night</div>
                <?php else : ?>
                  <div class="hhb-card-price" style="font-size:16px;color:var(--muted);">Price on request</div>
                <?php endif; ?>
              </div>

              <a href="<?php the_permalink(); ?>" class="hhb-card-cta">View Details →</a>
            </div>
          </article>

        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <div class="hhb-pagination">
        <?php
        echo paginate_links([
          'base'      => get_pagenum_link(1) . '%_%',
          'format'    => 'page/%#%/',
          'current'   => $current_page,
          'total'     => $wp_query->max_num_pages,
          'prev_text' => '<span class="material-symbols-outlined">chevron_left</span>',
          'next_text' => '<span class="material-symbols-outlined">chevron_right</span>',
          'type'      => 'list',
        ]);
        ?>
      </div>

    <?php else : ?>
      <div class="hhb-no-results">
        <div><span class="material-symbols-outlined">landscape</span></div>
        <h2>No Properties Found</h2>
        <p>We couldn't find any homestays matching your search. Try adjusting your filters or <a href="<?php echo esc_url($archive_url); ?>" style="color:var(--brand);">clear all filters</a>.</p>
      </div>
    <?php endif; ?>

  </div><!-- END results -->
</div><!-- END .hhb-arch-main -->

<script>
// ── Wishlist toggle ──────────────────────────────────────────────────────────
document.querySelectorAll('.hhb-wishlist-toggle').forEach(btn => {
  btn.addEventListener('click', async function(e) {
    e.preventDefault();
    const form = new FormData();
    form.append('action', 'hhb_toggle_wishlist');
    form.append('property_id', this.dataset.id);
    form.append('security', this.dataset.nonce);
    try {
      const res  = await fetch('<?php echo esc_js(admin_url("admin-ajax.php")); ?>', { method:'POST', body: form });
      const data = await res.json();
      if (data.success) {
        const icon = this.querySelector('.material-symbols-outlined');
        if (data.data.action === 'added') {
          icon.textContent = 'favorite';
          icon.style.color = '#e85e30';
          this.title = 'Remove from wishlist';
        } else {
          icon.textContent = 'favorite_border';
          icon.style.color = '';
          this.title = 'Save to wishlist';
        }
      }
    } catch(err) {}
  });
});
</script>

<?php get_footer(); ?>
