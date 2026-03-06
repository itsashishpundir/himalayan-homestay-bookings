<?php
/**
 * Homestay SEO & Schema Manager
 *
 * Outputs:
 *  - <title> override for single homestay pages
 *  - <meta name="description"> for single + archive/taxonomy pages
 *  - JSON-LD: LodgingBusiness + AggregateRating (single page)
 *  - JSON-LD: ItemList (archive / taxonomy pages)
 *  - JSON-LD: BreadcrumbList (all homestay pages)
 *
 * Skills applied: seo-structure-architect, seo-meta-optimizer,
 *                 wordpress-plugin-development, wordpress-theme (skill 1-3)
 *
 * @package Himalayan\Homestay\Interface\Frontend
 */

namespace Himalayan\Homestay\Interface\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class HomestaySchemaManager {

	public static function init(): void {
		// Override <title> tag for single homestay pages.
		add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_title' ], 20 );

		// Output meta description + JSON-LD in <head>.
		add_action( 'wp_head', [ __CLASS__, 'output_head_tags' ], 5 );
	}

	// =========================================================================
	// Title Tag Override
	// =========================================================================

	public static function filter_title( string $title ): string {
		if ( is_singular( 'hhb_homestay' ) ) {
			$post_id       = get_the_ID();
			$property_name = get_the_title( $post_id );
			$locations     = get_the_terms( $post_id, 'hhb_location' );
			$location      = ( $locations && ! is_wp_error( $locations ) ) ? $locations[0]->name : '';
			$site_name     = get_bloginfo( 'name' );

			if ( $location ) {
				return sprintf( '%s in %s — Book Himalayan Homestay | %s', $property_name, $location, $site_name );
			}
			return sprintf( '%s — Himalayan Homestay | %s', $property_name, $site_name );
		}

		if ( is_post_type_archive( 'hhb_homestay' ) || is_tax( [ 'hhb_location', 'hhb_property_type' ] ) ) {
			$term      = get_queried_object();
			$site_name = get_bloginfo( 'name' );
			if ( $term instanceof \WP_Term ) {
				return sprintf( 'Homestays in %s — Find & Book | %s', $term->name, $site_name );
			}
			return sprintf( 'Himalayan Homestays — Find & Book | %s', $site_name );
		}

		return $title;
	}

	// =========================================================================
	// Head Tags: Meta + JSON-LD
	// =========================================================================

	public static function output_head_tags(): void {
		if ( is_singular( 'hhb_homestay' ) ) {
			self::output_single_homestay_tags();
		} elseif ( is_post_type_archive( 'hhb_homestay' ) || is_tax( [ 'hhb_location', 'hhb_property_type' ] ) ) {
			self::output_archive_tags();
		}
	}

	// =========================================================================
	// Single Homestay: Meta + LodgingBusiness + AggregateRating + Breadcrumb
	// =========================================================================

	private static function output_single_homestay_tags(): void {
		$post_id  = get_the_ID();
		if ( ! $post_id ) return;

		// --- Meta fields ---
		$property_name = get_the_title( $post_id );
		$description   = wp_trim_words( get_the_excerpt( $post_id ) ?: wp_strip_all_tags( get_the_content( null, false, $post_id ) ), 30, '...' );
		$price_min     = (float) get_post_meta( $post_id, 'hhb_price_min', true );
		$price_max     = (float) get_post_meta( $post_id, 'hhb_price_max', true );
		$currency      = 'INR';
		$max_guests    = (int) ( get_post_meta( $post_id, 'hhb_max_guests', true ) ?: 0 );
		$address       = get_post_meta( $post_id, 'hhb_address', true );
		$city          = get_post_meta( $post_id, 'hhb_city', true );
		$state         = get_post_meta( $post_id, 'hhb_state', true );
		$country       = get_post_meta( $post_id, 'hhb_country', true ) ?: 'India';
		$postal_code   = get_post_meta( $post_id, 'hhb_postal_code', true );
		$amenity_keys  = get_post_meta( $post_id, 'hhb_amenities', true ) ?: [];
		$url           = get_permalink( $post_id );

		// Taxonomy: location
		$locations     = get_the_terms( $post_id, 'hhb_location' );
		$location_term = ( $locations && ! is_wp_error( $locations ) ) ? $locations[0] : null;
		$location_name = $location_term ? $location_term->name : '';

		// Thumbnail / gallery for image schema.
		$thumb_id  = get_post_thumbnail_id( $post_id );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
		$gallery_raw = get_post_meta( $post_id, 'hhb_gallery', true );
		$gallery_ids = $gallery_raw ? array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) ) : [];
		$image_urls  = [];
		if ( $thumb_url ) { $image_urls[] = $thumb_url; }
		foreach ( $gallery_ids as $gid ) {
			$gurl = wp_get_attachment_image_url( $gid, 'large' );
			if ( $gurl && ! in_array( $gurl, $image_urls ) ) {
				$image_urls[] = $gurl;
			}
		}

		// Amenity label map.
		$amenity_labels = [
			'wifi'            => 'WiFi',
			'parking'         => 'Free Parking',
			'kitchen'         => 'Kitchen',
			'ac'              => 'Air Conditioning',
			'tv'              => 'TV',
			'washing_machine' => 'Washing Machine',
			'hot_water'       => 'Hot Water',
			'garden'          => 'Garden',
			'balcony'         => 'Balcony',
			'fireplace'       => 'Fireplace',
			'gym'             => 'Gym',
			'pool'            => 'Swimming Pool',
		];
		$schema_amenities = [];
		foreach ( (array) $amenity_keys as $key ) {
			if ( isset( $amenity_labels[ $key ] ) ) {
				$schema_amenities[] = $amenity_labels[ $key ];
			}
		}

		// --- Reviews from DB ---
		global $wpdb;
		$table_reviews = $wpdb->prefix . 'hhb_reviews';
		$review_data   = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM {$table_reviews} WHERE homestay_id = %d AND status = 'approved'",
			$post_id
		) );
		$review_count  = $review_data ? (int) $review_data->total : 0;
		$avg_rating    = $review_data ? round( (float) $review_data->avg_rating, 1 ) : 0;

		// --- Meta description ---
		$price_display = $price_min > 0
			? ( $price_max > $price_min
				? sprintf( '₹%s–₹%s', number_format( $price_min ), number_format( $price_max ) )
				: '₹' . number_format( $price_min ) )
			: '';

		$meta_desc = sprintf(
			'Book %s in %s.%s%s Authentic Himalayan homestay experience.',
			$property_name,
			$location_name ?: ( $city ?: 'the Himalayas' ),
			$price_display ? ' From ' . $price_display . '/night.' : '',
			$review_count > 0 ? sprintf( ' Rated %.1f/5 by %d guests.', $avg_rating, $review_count ) : ''
		);
		$meta_desc = mb_substr( $meta_desc, 0, 160 );

		// --- Schema: LodgingBusiness ---
		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'LodgingBusiness',
			'name'        => $property_name,
			'description' => $description,
			'url'         => $url,
		];

		if ( ! empty( $image_urls ) ) {
			$schema['image'] = count( $image_urls ) === 1 ? $image_urls[0] : $image_urls;
		}

		if ( $price_min > 0 ) {
			$schema['priceRange'] = $price_max > $price_min
				? sprintf( '₹%s – ₹%s / night', number_format( $price_min ), number_format( $price_max ) )
				: sprintf( '₹%s / night', number_format( $price_min ) );
		}

		if ( $max_guests > 0 ) {
			$schema['maximumAttendeeCapacity'] = $max_guests;
		}

		if ( ! empty( $schema_amenities ) ) {
			$schema['amenityFeature'] = array_map( function( $name ) {
				return [ '@type' => 'LocationFeatureSpecification', 'name' => $name, 'value' => true ];
			}, $schema_amenities );
		}

		// Structured PostalAddress from new address meta fields
		$postal_address = [ '@type' => 'PostalAddress', 'addressCountry' => 'IN' ];
		if ( $address )     { $postal_address['streetAddress']   = $address; }
		if ( $city )        { $postal_address['addressLocality'] = $city; }
		if ( $state )       { $postal_address['addressRegion']   = $state; }
		if ( $postal_code ) { $postal_address['postalCode']      = $postal_code; }
		if ( $location_name && ! $city ) {
			$postal_address['addressLocality'] = $location_name;
		}
		if ( count( $postal_address ) > 2 ) {
			$schema['address'] = $postal_address;
		}

		// AggregateRating — only if reviews exist.
		if ( $review_count >= 1 && $avg_rating > 0 ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => $avg_rating,
				'reviewCount' => $review_count,
				'bestRating'  => 5,
				'worstRating' => 1,
			];
		}

		// --- Breadcrumb ---
		$breadcrumb = self::build_breadcrumb_schema( [
			[ 'name' => 'Home',       'url' => home_url( '/' ) ],
			[ 'name' => 'Homestays',  'url' => get_post_type_archive_link( 'hhb_homestay' ) ],
		] );
		if ( $location_term ) {
			$breadcrumb['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $location_term->name,
				'item'     => get_term_link( $location_term ),
			];
			$breadcrumb['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => 4,
				'name'     => $property_name,
				'item'     => $url,
			];
		} else {
			$breadcrumb['itemListElement'][] = [
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $property_name,
				'item'     => $url,
			];
		}

		self::render_output( $meta_desc, $url, $image_urls[0] ?? '', [ $schema, $breadcrumb ] );
	}

	// =========================================================================
	// Archive / Taxonomy: Meta + ItemList + Breadcrumb
	// =========================================================================

	private static function output_archive_tags(): void {
		$term      = get_queried_object();
		$is_term   = ( $term instanceof \WP_Term );
		$term_name = $is_term ? $term->name : 'Himalayan Homestays';
		$url       = $is_term ? get_term_link( $term ) : get_post_type_archive_link( 'hhb_homestay' );

		$meta_desc = mb_substr( sprintf(
			'Discover the best stays in %s. Browse authentic Himalayan homestays with real guest reviews, transparent pricing, and instant booking.',
			$is_term ? $term_name : 'the Himalayas'
		), 0, 160 );

		// Build ItemList from current query.
		global $wp_query;
		$items = [];
		$pos   = 1;
		if ( $wp_query->have_posts() ) {
			foreach ( $wp_query->posts as $p ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $pos++,
					'url'      => get_permalink( $p->ID ),
					'name'     => get_the_title( $p->ID ),
				];
			}
		}

		$item_list = [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => 'Homestays in ' . $term_name,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		];

		// Breadcrumb.
		$breadcrumb_items = [
			[ 'name' => 'Home',      'url' => home_url( '/' ) ],
			[ 'name' => 'Homestays', 'url' => get_post_type_archive_link( 'hhb_homestay' ) ],
		];
		if ( $is_term ) {
			$breadcrumb_items[] = [ 'name' => $term_name, 'url' => (string) $url ];
		}
		$breadcrumb = self::build_breadcrumb_schema( $breadcrumb_items );

		self::render_output( $meta_desc, (string) $url, '', [ $item_list, $breadcrumb ] );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private static function build_breadcrumb_schema( array $items ): array {
		$list = [];
		foreach ( $items as $i => $item ) {
			$list[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			];
		}
		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		];
	}

	private static function render_output( string $desc, string $url, string $image, array $schemas ): void {
		// Meta description.
		printf(
			'<meta name="description" content="%s">' . "\n",
			esc_attr( $desc )
		);
		// Open Graph tags.
		printf( '<meta property="og:type" content="website">' . "\n" );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		if ( $image ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		}
		// JSON-LD blocks.
		foreach ( $schemas as $schema ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			);
		}
	}
}
