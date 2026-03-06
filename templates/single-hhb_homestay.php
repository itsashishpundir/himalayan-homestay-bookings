<?php
/**
 * Plugin Default Template: Single Homestay Property Listing
 *
 * Designed via Google Stitch — premium mountain-retreat aesthetic.
 * Theme can override by providing single-hhb_homestay.php in the theme root.
 *
 * @package Himalayan\Homestay
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
the_post();

$post_id = get_the_ID();

// ── Meta ──────────────────────────────────────────────────────────────────────
$base_price   = get_post_meta( $post_id, 'base_price_per_night', true );
$offer_price  = get_post_meta( $post_id, 'offer_price_per_night', true );
$max_guests   = get_post_meta( $post_id, 'max_guests', true );
$bedrooms     = get_post_meta( $post_id, 'hhb_bedrooms', true ) ?: get_post_meta( $post_id, 'room_count', true );
$bathrooms    = get_post_meta( $post_id, 'hhb_bathrooms', true );
$currency_sym = '₹';

// ── Amenities ─────────────────────────────────────────────────────────────────
$amenities_meta = (array) get_post_meta( $post_id, 'hhb_amenities', true );
$amenity_icons  = [
    'wifi'            => ['WiFi',              'wifi'],
    'parking'         => ['Free Parking',      'local_parking'],
    'kitchen'         => ['Kitchen',           'kitchen'],
    'ac'              => ['Air Conditioning',  'ac_unit'],
    'tv'              => ['Smart TV',          'tv'],
    'washing_machine' => ['Washing Machine',   'local_laundry_service'],
    'hot_water'       => ['Hot Water',         'water_drop'],
    'garden'          => ['Garden',            'yard'],
    'balcony'         => ['Balcony / Deck',    'deck'],
    'fireplace'       => ['Fireplace',         'fireplace'],
    'gym'             => ['Gym',               'fitness_center'],
    'pool'            => ['Swimming Pool',     'pool'],
];

// ── Gallery ───────────────────────────────────────────────────────────────────
$cover_id     = get_post_thumbnail_id( $post_id );
$cover_url    = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : 'https://images.unsplash.com/photo-1629094934060-4c58cfb6e714?w=1200&q=80';
$gallery_ids  = (array) get_post_meta( $post_id, 'hhb_gallery_images', true );
$gallery_urls = array_map( fn($id) => wp_get_attachment_image_url( $id, 'medium_large' ), array_filter( $gallery_ids ) );

// ── Host ─────────────────────────────────────────────────────────────────────
$host_mode    = get_post_meta( $post_id, 'hhb_host_mode', true ) ?: 'user';
if ( $host_mode === 'user' ) {
    $host_uid   = get_post_meta( $post_id, 'hhb_host_user_id', true ) ?: get_post_field( 'post_author', $post_id );
    $host_name  = get_the_author_meta( 'display_name', $host_uid );
    $host_bio   = get_the_author_meta( 'description', $host_uid );
    $avatar_id  = get_user_meta( $host_uid, 'hhb_avatar_id', true );
    $host_avatar = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : get_avatar_url( $host_uid, ['size' => 80] );
} else {
    $host_name   = get_post_meta( $post_id, 'hhb_host_name', true ) ?: get_bloginfo('name');
    $host_bio    = get_post_meta( $post_id, 'hhb_host_bio', true );
    $host_avatar = get_post_meta( $post_id, 'hhb_host_avatar_url', true ) ?: get_avatar_url( 0 );
}

// ── Reviews (average) ─────────────────────────────────────────────────────────
$avg_rating   = (float) get_post_meta( $post_id, 'hhb_avg_rating', true );
$review_count = (int) get_post_meta( $post_id, 'hhb_review_count', true );

// ── Taxonomy ─────────────────────────────────────────────────────────────────
$locations      = get_the_terms( $post_id, 'hhb_location' );
$property_types = get_the_terms( $post_id, 'hhb_property_type' );
$location_name  = ( $locations && ! is_wp_error($locations) ) ? $locations[0]->name : '';
$type_name      = ( $property_types && ! is_wp_error($property_types) ) ? $property_types[0]->name : 'Homestay';

// ── Rules ─────────────────────────────────────────────────────────────────────
$dos        = array_filter( explode("\n", get_post_meta( $post_id, 'hhb_dos', true ) ?: '' ) );
$donts      = array_filter( explode("\n", get_post_meta( $post_id, 'hhb_donts', true ) ?: '' ) );
$attractions = (array) get_post_meta( $post_id, 'hhb_attractions', true );

$nonce = wp_create_nonce('hhb_booking');
$ajax_url = admin_url('admin-ajax.php');
?>

<!-- Google Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@400,0&display=swap" rel="stylesheet">

<style>
  :root { --brand: #e85e30; --brand-light: #fef1ec; --bg: #f8f6f6; --border: #e2e8f0; --text: #1a1a2e; --muted: #64748b; }
  .hhb-single * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
  .hhb-single { max-width: 1280px; margin: 0 auto; padding: 0 24px 80px; background: var(--bg); }

  /* ── GALLERY ── */
  .hhb-gallery { display: grid; grid-template-columns: 3fr 2fr; grid-template-rows: 280px 280px; gap: 8px; border-radius: 20px; overflow: hidden; margin-bottom: 40px; }
  .hhb-gallery-main { grid-row: 1 / 3; position: relative; }
  .hhb-gallery img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s; cursor: pointer; }
  .hhb-gallery img:hover { transform: scale(1.02); }
  .hhb-gallery-main { overflow: hidden; }
  .hhb-gallery-sub { overflow: hidden; position: relative; }
  .hhb-gallery-more { position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; cursor: pointer; }
  .hhb-gallery-more-btn { background: white; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 6px; }

  /* ── LAYOUT ── */
  .hhb-layout { display: grid; grid-template-columns: 1fr 380px; gap: 48px; align-items: start; }
  @media(max-width: 900px) { .hhb-layout { grid-template-columns: 1fr; } .hhb-gallery { grid-template-columns: 1fr 1fr; grid-template-rows: 220px 220px; } }

  /* ── MAIN CONTENT ── */
  .hhb-title { font-size: 36px; font-weight: 800; color: var(--text); margin: 0 0 12px; line-height: 1.2; }
  .hhb-meta-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
  .hhb-stars { color: #f59e0b; font-size: 15px; font-weight: 700; }
  .hhb-review-link { color: var(--muted); font-size: 14px; text-decoration: underline; cursor: pointer; }
  .hhb-location { display: flex; align-items: center; gap: 4px; color: var(--muted); font-size: 14px; }
  .hhb-location .material-symbols-outlined { font-size: 16px; color: var(--brand); }
  .hhb-type-badge { background: var(--brand-light); color: var(--brand); padding: 4px 12px; border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

  /* Pills */
  .hhb-pills { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
  .hhb-pill { display: flex; align-items: center; gap: 8px; background: white; border: 1px solid var(--border); border-radius: 50px; padding: 8px 16px; font-size: 14px; font-weight: 500; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
  .hhb-pill .material-symbols-outlined { font-size: 18px; color: var(--brand); }

  /* Sections */
  .hhb-divider { border: none; border-top: 1px solid var(--border); margin: 32px 0; }
  .hhb-section-title { font-size: 22px; font-weight: 700; color: var(--text); margin: 0 0 20px; }
  .hhb-description { color: #374151; line-height: 1.8; font-size: 16px; }

  /* Host card */
  .hhb-host-card { display: flex; align-items: center; gap: 20px; padding: 24px; background: white; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
  .hhb-host-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--brand); flex-shrink: 0; }
  .hhb-host-name { font-size: 18px; font-weight: 700; color: var(--text); margin: 0 0 4px; }
  .hhb-host-meta { font-size: 13px; color: var(--muted); }
  .hhb-superhost { background: var(--brand); color: white; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px; margin-top: 4px; }

  /* Amenities */
  .hhb-amenities { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
  .hhb-amenity-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: white; border: 1px solid var(--border); border-radius: 12px; font-size: 14px; font-weight: 500; color: var(--text); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
  .hhb-amenity-item .material-symbols-outlined { font-size: 20px; color: var(--brand); flex-shrink: 0; }

  /* Dos & Donts */
  .hhb-dos-donts { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .hhb-dos-box, .hhb-donts-box { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
  .hhb-dos-box h4 { color: #059669; display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; margin: 0 0 16px; }
  .hhb-donts-box h4 { color: #dc2626; display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; margin: 0 0 16px; }
  .hhb-dos-list li, .hhb-donts-list li { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 10px; font-size: 14px; color: #374151; list-style: none; padding: 0; }
  .hhb-dos-list { padding: 0; margin: 0; }
  .hhb-donts-list { padding: 0; margin: 0; }

  /* Attractions */
  .hhb-attractions-list { padding: 0; margin: 0; }
  .hhb-attractions-list li { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--border); font-size: 15px; color: #374151; list-style: none; }
  .hhb-attractions-list li:last-child { border-bottom: none; }
  .hhb-attractions-list .material-symbols-outlined { color: var(--brand); font-size: 20px; flex-shrink: 0; }

  /* Reviews */
  .hhb-reviews-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-top: 24px; }
  .hhb-review-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
  .hhb-reviewer { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
  .hhb-reviewer img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
  .hhb-reviewer-name { font-weight: 700; font-size: 15px; color: var(--text); }
  .hhb-reviewer-meta { font-size: 12px; color: var(--muted); }
  .hhb-review-text { color: #374151; font-size: 14px; line-height: 1.7; }
  .hhb-rating-summary { display: flex; align-items: center; gap: 12px; padding: 20px; background: var(--brand-light); border-radius: 16px; margin-bottom: 24px; }
  .hhb-rating-big { font-size: 48px; font-weight: 800; color: var(--brand); line-height: 1; }
  .hhb-rating-stars { color: #f59e0b; font-size: 22px; }

  /* Booking Widget sidebar */
  .hhb-booking-sidebar { position: sticky; top: 24px; }
  .hhb-booking-card { background: white; border-radius: 20px; border: 1px solid var(--border); padding: 28px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
  .hhb-price-display { display: flex; align-items: baseline; gap: 4px; margin-bottom: 20px; }
  .hhb-price-amount { font-size: 32px; font-weight: 800; color: var(--text); }
  .hhb-price-unit { font-size: 16px; color: var(--muted); }
  .hhb-date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .hhb-date-input { background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; font-size: 14px; font-family: 'Inter', sans-serif; color: var(--text); width: 100%; }
  .hhb-date-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(232,94,48,0.12); }
  .hhb-date-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--muted); margin-bottom: 4px; }
  .hhb-guests-row { display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; }
  .hhb-guests-ctrl { display: flex; align-items: center; gap: 14px; }
  .hhb-guests-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border); background: white; font-size: 18px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
  .hhb-guests-btn:hover { background: var(--brand); color: white; border-color: var(--brand); }
  .hhb-guests-count { font-size: 16px; font-weight: 700; min-width: 20px; text-align: center; }
  .hhb-price-breakdown { background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 20px; font-size: 14px; }
  .hhb-breakdown-row { display: flex; justify-content: space-between; margin-bottom: 8px; color: #374151; }
  .hhb-breakdown-row:last-child { margin-bottom: 0; padding-top: 10px; border-top: 1px solid var(--border); font-weight: 700; font-size: 16px; color: var(--text); }
  .hhb-book-btn { width: 100%; padding: 16px; background: var(--brand); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 4px 16px rgba(232,94,48,0.3); }
  .hhb-book-btn:hover { background: #c94d22; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(232,94,48,0.4); }
  .hhb-deposit-link { display: block; text-align: center; margin-top: 14px; color: var(--muted); font-size: 13px; cursor: pointer; text-decoration: underline; }
  .hhb-booking-msg { padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-top: 12px; display: none; }
  .hhb-booking-msg.success { background: #dcfce7; color: #166534; display: block; }
  .hhb-booking-msg.error { background: #fee2e2; color: #991b1b; display: block; }

  /* Lightbox overlay */
  .hhb-lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 9999; align-items: center; justify-content: center; }
  .hhb-lightbox.open { display: flex; }
  .hhb-lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 12px; object-fit: contain; }
  .hhb-lightbox-close { position: absolute; top: 24px; right: 24px; color: white; font-size: 32px; cursor: pointer; }
  .hhb-lightbox-prev, .hhb-lightbox-next { position: absolute; top: 50%; transform: translateY(-50%); color: white; font-size: 48px; cursor: pointer; padding: 16px; }
  .hhb-lightbox-prev { left: 16px; }
  .hhb-lightbox-next { right: 16px; }

  /* ── Review Form ── */
  .hhb-review-form { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 28px; margin-bottom: 32px; }
  .hhb-star-selector { display: flex; gap: 8px; margin-bottom: 16px; font-size: 32px; cursor: pointer; }
  .hhb-star-selector span { color: #d1d5db; transition: color 0.15s; }
  .hhb-star-selector span.active { color: #f59e0b; }
  .hhb-review-textarea { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 15px; resize: vertical; min-height: 100px; color: var(--text); }
  .hhb-review-textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(232,94,48,0.12); }
  .hhb-submit-review { background: var(--brand); color: white; border: none; border-radius: 10px; padding: 12px 28px; font-weight: 700; font-size: 15px; cursor: pointer; font-family: 'Inter', sans-serif; margin-top: 16px; transition: background 0.2s; }
  .hhb-submit-review:hover { background: #c94d22; }
</style>

<div class="hhb-single">

  <!-- ── GALLERY ── -->
  <div class="hhb-gallery" id="hhb-gallery-grid">
    <div class="hhb-gallery-main" onclick="hhbOpenLightbox(0)">
      <img src="<?php echo esc_url($cover_url); ?>" alt="<?php the_title_attribute(); ?> cover photo">
    </div>
    <?php
    $shown = array_slice($gallery_urls, 0, 4);
    $extra = count($gallery_urls) > 4 ? count($gallery_urls) - 3 : 0;
    foreach ($shown as $i => $gurl) :
        $is_last = ($i === 3 && $extra > 0);
    ?>
      <div class="hhb-gallery-sub" onclick="hhbOpenLightbox(<?php echo $i + 1; ?>)">
        <img src="<?php echo esc_url($gurl); ?>" alt="Gallery <?php echo $i + 1; ?>">
        <?php if ($is_last && $extra > 0) : ?>
          <div class="hhb-gallery-more">
            <button class="hhb-gallery-more-btn">
              <span class="material-symbols-outlined">grid_view</span>
              +<?php echo $extra; ?> Photos
            </button>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ── TWO-COLUMN LAYOUT ── -->
  <div class="hhb-layout">

    <!-- LEFT COLUMN -->
    <div>

      <!-- Title & Meta -->
      <h1 class="hhb-title"><?php the_title(); ?></h1>
      <div class="hhb-meta-row">
        <?php if ($avg_rating > 0) : ?>
          <div class="hhb-stars">★ <?php echo number_format($avg_rating, 1); ?></div>
          <a class="hhb-review-link" href="#reviews"><?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?></a>
          <span style="color: var(--border);">·</span>
        <?php endif; ?>
        <?php if ($location_name) : ?>
          <div class="hhb-location">
            <span class="material-symbols-outlined">location_on</span>
            <?php echo esc_html($location_name); ?>
          </div>
          <span style="color: var(--border);">·</span>
        <?php endif; ?>
        <span class="hhb-type-badge"><?php echo esc_html($type_name); ?></span>
      </div>

      <!-- Quick Stats Pills -->
      <div class="hhb-pills">
        <?php if ($bedrooms) : ?>
          <div class="hhb-pill"><span class="material-symbols-outlined">bed</span><?php echo esc_html($bedrooms); ?> Bedroom<?php echo $bedrooms != 1 ? 's' : ''; ?></div>
        <?php endif; ?>
        <?php if ($bathrooms) : ?>
          <div class="hhb-pill"><span class="material-symbols-outlined">bathtub</span><?php echo esc_html($bathrooms); ?> Bathroom<?php echo $bathrooms != 1 ? 's' : ''; ?></div>
        <?php endif; ?>
        <?php if ($max_guests) : ?>
          <div class="hhb-pill"><span class="material-symbols-outlined">group</span>Up to <?php echo esc_html($max_guests); ?> guests</div>
        <?php endif; ?>
        <div class="hhb-pill"><span class="material-symbols-outlined">home</span><?php echo esc_html($type_name); ?></div>
      </div>

      <hr class="hhb-divider">

      <!-- Host Card -->
      <div class="hhb-host-card">
        <img src="<?php echo esc_url($host_avatar); ?>" alt="<?php echo esc_attr($host_name); ?>" class="hhb-host-avatar">
        <div>
          <p class="hhb-host-name">Hosted by <?php echo esc_html($host_name); ?></p>
          <p class="hhb-host-meta"><?php echo esc_html($host_bio ?: 'Experienced host dedicated to making your stay perfect.'); ?></p>
          <span class="hhb-superhost"><span class="material-symbols-outlined" style="font-size:12px;">verified</span>Verified Host</span>
        </div>
      </div>

      <hr class="hhb-divider">

      <!-- Description -->
      <h2 class="hhb-section-title">About this space</h2>
      <div class="hhb-description"><?php the_content(); ?></div>

      <hr class="hhb-divider">

      <!-- Amenities -->
      <?php if (!empty($amenities_meta)) : ?>
        <h2 class="hhb-section-title">What this place offers</h2>
        <div class="hhb-amenities">
          <?php foreach ($amenities_meta as $key) :
            if (!isset($amenity_icons[$key])) continue;
            [$label, $icon] = $amenity_icons[$key];
          ?>
            <div class="hhb-amenity-item">
              <span class="material-symbols-outlined"><?php echo esc_html($icon); ?></span>
              <?php echo esc_html($label); ?>
            </div>
          <?php endforeach; ?>
        </div>
        <hr class="hhb-divider">
      <?php endif; ?>

      <!-- Dos & Don'ts -->
      <?php if (!empty($dos) || !empty($donts)) : ?>
        <h2 class="hhb-section-title">House Rules</h2>
        <div class="hhb-dos-donts">
          <?php if (!empty($dos)) : ?>
            <div class="hhb-dos-box">
              <h4><span class="material-symbols-outlined">check_circle</span>The Dos</h4>
              <ul class="hhb-dos-list">
                <?php foreach ($dos as $item) : ?>
                  <li><span style="color:#059669;font-size:16px;">✓</span> <?php echo esc_html(trim($item)); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!empty($donts)) : ?>
            <div class="hhb-donts-box">
              <h4><span class="material-symbols-outlined">cancel</span>Please Avoid</h4>
              <ul class="hhb-donts-list">
                <?php foreach ($donts as $item) : ?>
                  <li><span style="color:#dc2626;font-size:16px;">✗</span> <?php echo esc_html(trim($item)); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
        <hr class="hhb-divider">
      <?php endif; ?>

      <!-- Nearby Attractions -->
      <?php if (!empty($attractions)) : ?>
        <h2 class="hhb-section-title">Nearby Attractions</h2>
        <ul class="hhb-attractions-list">
          <?php foreach ($attractions as $attraction) :
            if (!trim($attraction)) continue; ?>
            <li>
              <span class="material-symbols-outlined">location_on</span>
              <?php echo esc_html(trim($attraction)); ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <hr class="hhb-divider">
      <?php endif; ?>

      <!-- Reviews Section -->
      <div id="reviews">
        <?php if ($avg_rating > 0) : ?>
          <div class="hhb-rating-summary">
            <div class="hhb-rating-big"><?php echo number_format($avg_rating, 1); ?></div>
            <div>
              <div class="hhb-rating-stars">
                <?php for ($s = 1; $s <= 5; $s++) echo $s <= round($avg_rating) ? '★' : '☆'; ?>
              </div>
              <div style="font-size:14px; color:var(--muted); margin-top:4px;"><?php echo $review_count; ?> reviews</div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Leave a Review -->
        <?php if (is_user_logged_in()) : ?>
          <h2 class="hhb-section-title">Leave a Review</h2>
          <div class="hhb-review-form">
            <div class="hhb-star-selector" id="hhb-star-selector">
              <span data-val="1">★</span>
              <span data-val="2">★</span>
              <span data-val="3">★</span>
              <span data-val="4">★</span>
              <span data-val="5">★</span>
            </div>
            <input type="hidden" id="hhb-rating-value" value="0">
            <textarea class="hhb-review-textarea" id="hhb-review-text" placeholder="Share your experience staying here..."></textarea>
            <button class="hhb-submit-review" onclick="hhbSubmitReview(<?php echo $post_id; ?>, '<?php echo $nonce; ?>')">
              Submit Review
            </button>
            <div id="hhb-review-msg" class="hhb-booking-msg"></div>
          </div>
        <?php endif; ?>

        <!-- Existing Reviews -->
        <div class="hhb-reviews-grid" id="hhb-reviews-container">
          <?php
          do_action('hhb_render_reviews', $post_id);
          ?>
        </div>
      </div>

    </div><!-- END LEFT COLUMN -->

    <!-- RIGHT COLUMN — Booking Widget Sidebar -->
    <div class="hhb-booking-sidebar">
      <div class="hhb-booking-card">

        <div class="hhb-price-display">
          <?php if ($offer_price) : ?>
            <span class="hhb-price-amount"><?php echo $currency_sym . number_format((float)$offer_price); ?></span>
            <span class="hhb-price-unit">/night</span>
            <span style="text-decoration:line-through;color:var(--muted);font-size:16px;margin-left:8px;"><?php echo $currency_sym . number_format((float)$base_price); ?></span>
          <?php else : ?>
            <span class="hhb-price-amount"><?php echo $currency_sym . number_format((float)($base_price ?: 0)); ?></span>
            <span class="hhb-price-unit">/night</span>
          <?php endif; ?>
        </div>

        <form id="hhb-booking-widget-form" onsubmit="return false;">
          <?php wp_nonce_field('hhb_booking', 'hhb_nonce'); ?>
          <input type="hidden" name="action" value="hhb_initiate_booking">
          <input type="hidden" name="homestay_id" value="<?php echo esc_attr($post_id); ?>">

          <div class="hhb-date-row">
            <div>
              <label class="hhb-date-label">Check-in</label>
              <input type="date" class="hhb-date-input" name="check_in" id="hhb-check-in" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
              <label class="hhb-date-label">Check-out</label>
              <input type="date" class="hhb-date-input" name="check_out" id="hhb-check-out" required>
            </div>
          </div>

          <div class="hhb-guests-row">
            <span style="font-size:14px;font-weight:600;color:var(--text);">Guests</span>
            <div class="hhb-guests-ctrl">
              <button type="button" class="hhb-guests-btn" id="hhb-guests-minus">−</button>
              <span class="hhb-guests-count" id="hhb-guests-val">1</span>
              <button type="button" class="hhb-guests-btn" id="hhb-guests-plus">+</button>
            </div>
          </div>
          <input type="hidden" name="guests" id="hhb-guests-input" value="1">

          <!-- Price Breakdown (updates via JS) -->
          <div class="hhb-price-breakdown" id="hhb-price-breakdown" style="display:none;">
            <div class="hhb-breakdown-row">
              <span id="bd-rate-label"></span>
              <span id="bd-rate-val"></span>
            </div>
            <div class="hhb-breakdown-row">
              <span>Taxes &amp; fees</span>
              <span id="bd-tax-val"></span>
            </div>
            <div class="hhb-breakdown-row">
              <span>Total</span>
              <span id="bd-total-val"></span>
            </div>
          </div>

          <button type="submit" class="hhb-book-btn" id="hhb-book-btn">Check Availability</button>
          <span id="hhb-deposit-link" class="hhb-deposit-link" style="display:none;">or pay deposit only</span>
        </form>

        <div id="hhb-booking-msg" class="hhb-booking-msg"></div>

        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
          <div style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;margin-bottom:8px;">
            <span class="material-symbols-outlined" style="font-size:16px;color:#059669;">verified_user</span>
            <span>Secure booking · SSL encrypted</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;">
            <span class="material-symbols-outlined" style="font-size:16px;color:#e85e30;">event_available</span>
            <span>Free cancellation before check-in</span>
          </div>
        </div>

      </div>
    </div><!-- END RIGHT COLUMN -->

  </div><!-- END .hhb-layout -->
</div><!-- END .hhb-single -->

<!-- ── Lightbox ── -->
<div class="hhb-lightbox" id="hhb-lightbox">
  <span class="material-symbols-outlined hhb-lightbox-close" onclick="hhbCloseLightbox()">close</span>
  <span class="material-symbols-outlined hhb-lightbox-prev" onclick="hhbLightboxNav(-1)">chevron_left</span>
  <img src="" alt="" id="hhb-lightbox-img">
  <span class="material-symbols-outlined hhb-lightbox-next" onclick="hhbLightboxNav(1)">chevron_right</span>
</div>

<script>
(function() {
  // ── Gallery & Lightbox ──────────────────────────────────────────────────────
  const images = [
    '<?php echo esc_js($cover_url); ?>',
    <?php foreach($gallery_urls as $gu) echo "'" . esc_js($gu) . "', "; ?>
  ];
  let currentImg = 0;

  window.hhbOpenLightbox = (idx) => {
    currentImg = idx;
    document.getElementById('hhb-lightbox-img').src = images[idx] || images[0];
    document.getElementById('hhb-lightbox').classList.add('open');
  };
  window.hhbCloseLightbox = () => document.getElementById('hhb-lightbox').classList.remove('open');
  window.hhbLightboxNav = (dir) => {
    currentImg = (currentImg + dir + images.length) % images.length;
    document.getElementById('hhb-lightbox-img').src = images[currentImg];
  };
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hhbCloseLightbox();
    if (e.key === 'ArrowRight') hhbLightboxNav(1);
    if (e.key === 'ArrowLeft') hhbLightboxNav(-1);
  });

  // ── Guest counter ──────────────────────────────────────────────────────────
  let guests = 1;
  const maxG = <?php echo (int)($max_guests ?: 10); ?>;
  const gVal = document.getElementById('hhb-guests-val');
  const gInput = document.getElementById('hhb-guests-input');

  document.getElementById('hhb-guests-plus').addEventListener('click', () => {
    if (guests < maxG) { guests++; gVal.textContent = guests; gInput.value = guests; recalcPrice(); }
  });
  document.getElementById('hhb-guests-minus').addEventListener('click', () => {
    if (guests > 1) { guests--; gVal.textContent = guests; gInput.value = guests; recalcPrice(); }
  });

  // ── Price calculation ──────────────────────────────────────────────────────
  const pricePerNight = <?php echo (float)($offer_price ?: $base_price ?: 0); ?>;
  const TAX_RATE = 0.12;
  const sym = '<?php echo esc_js($currency_sym); ?>';

  function recalcPrice() {
    const ci = document.getElementById('hhb-check-in').value;
    const co = document.getElementById('hhb-check-out').value;
    if (!ci || !co) return;
    const nights = Math.max(1, Math.round((new Date(co) - new Date(ci)) / 86400000));
    const base = nights * pricePerNight;
    const tax = base * TAX_RATE;
    const total = base + tax;
    document.getElementById('bd-rate-label').textContent = `${sym}${pricePerNight.toLocaleString()} × ${nights} night${nights > 1 ? 's' : ''}`;
    document.getElementById('bd-rate-val').textContent = sym + base.toLocaleString('en-IN');
    document.getElementById('bd-tax-val').textContent = sym + Math.round(tax).toLocaleString('en-IN');
    document.getElementById('bd-total-val').textContent = sym + Math.round(total).toLocaleString('en-IN');
    document.getElementById('hhb-price-breakdown').style.display = 'block';
    document.getElementById('hhb-deposit-link').style.display = 'block';
  }

  document.getElementById('hhb-check-in').addEventListener('change', function() {
    const d = new Date(this.value);
    d.setDate(d.getDate() + 1);
    document.getElementById('hhb-check-out').min = d.toISOString().split('T')[0];
    recalcPrice();
  });
  document.getElementById('hhb-check-out').addEventListener('change', recalcPrice);

  // ── Booking form submit ────────────────────────────────────────────────────
  document.getElementById('hhb-booking-widget-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('hhb-book-btn');
    const msg = document.getElementById('hhb-booking-msg');
    btn.textContent = 'Processing...';
    btn.disabled = true;

    try {
      const res = await fetch('<?php echo esc_js(admin_url("admin-ajax.php")); ?>', { method: 'POST', body: new FormData(this) });
      const data = await res.json();
      if (data.success) {
        msg.className = 'hhb-booking-msg success';
        msg.textContent = data.data.message || 'Booking request received!';
        if (data.data.redirect) setTimeout(() => window.location.href = data.data.redirect, 800);
      } else {
        msg.className = 'hhb-booking-msg error';
        msg.textContent = data.data || 'Unable to process. Please try again.';
        btn.textContent = 'Check Availability';
        btn.disabled = false;
      }
    } catch(err) {
      msg.className = 'hhb-booking-msg error';
      msg.textContent = 'Network error. Please try again.';
      btn.textContent = 'Check Availability';
      btn.disabled = false;
    }
  });

  // ── Star rating selector ───────────────────────────────────────────────────
  const stars = document.querySelectorAll('#hhb-star-selector span');
  stars.forEach(star => {
    star.addEventListener('mouseenter', () => {
      const val = +star.dataset.val;
      stars.forEach(s => s.classList.toggle('active', +s.dataset.val <= val));
    });
    star.addEventListener('click', () => {
      document.getElementById('hhb-rating-value').value = star.dataset.val;
    });
  });
  document.getElementById('hhb-star-selector')?.addEventListener('mouseleave', () => {
    const sel = +document.getElementById('hhb-rating-value').value;
    stars.forEach(s => s.classList.toggle('active', +s.dataset.val <= sel));
  });
})();

async function hhbSubmitReview(postId, nonce) {
  const rating = document.getElementById('hhb-rating-value').value;
  const text   = document.getElementById('hhb-review-text').value.trim();
  const msg    = document.getElementById('hhb-review-msg');
  if (!rating || rating < 1) { msg.className = 'hhb-booking-msg error'; msg.textContent = 'Please select a rating.'; return; }
  if (!text) { msg.className = 'hhb-booking-msg error'; msg.textContent = 'Please write a review.'; return; }

  const fd = new FormData();
  fd.append('action', 'hhb_submit_review');
  fd.append('security', nonce);
  fd.append('post_id', postId);
  fd.append('rating', rating);
  fd.append('review', text);

  const res  = await fetch('<?php echo esc_js(admin_url("admin-ajax.php")); ?>', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    msg.className = 'hhb-booking-msg success';
    msg.textContent = 'Thank you! Your review has been submitted.';
    document.getElementById('hhb-review-text').value = '';
    document.getElementById('hhb-rating-value').value = 0;
    document.querySelectorAll('#hhb-star-selector span').forEach(s => s.classList.remove('active'));
  } else {
    msg.className = 'hhb-booking-msg error';
    msg.textContent = data.data || 'Could not submit review.';
  }
}
</script>

<?php get_footer(); ?>
